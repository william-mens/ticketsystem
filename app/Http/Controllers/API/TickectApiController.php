<?php

namespace App\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Models\Event;
use Illuminate\Http\Request;
use DB;
use App\Models\Order;
use App\Services\Order as OrderService;
use App\Models\Ticket;
use App\Models\OrderItem;
use App\Models\EventStats;
use App\Models\Attendee;
use App\TextMessaging;
use Exception;
use Carbon\Carbon;
use Log;


class TicketApiController extends Controller
{

    public function store(Request $request)
    {
        $event_id  = $request->get("event_id") ?? 1;
        $account_id = $request->get("account_id") ?? 1;
        $name = $request->get('name');
        $nameParts = preg_split('/\s+/', $name, 2);


        $ticket_id = $request->get('ticket_id');
        $event = Event::find($event_id);
        $attendee_first_name = $nameParts[0] ?? '';
        $attendee_last_name = $nameParts[1] ?? '';
        $attendee_email = $request->get('email');

        DB::beginTransaction();

        try {

            /*
             * Create the order
             */

            $ticket_price = $request->get('ticket_price');

            $order = new Order();
            $order->first_name = $attendee_first_name;
            $order->last_name = $attendee_last_name;
            $order->email = $attendee_email;
            $order->order_status_id = config('attendize.order.complete');
            $order->amount = $ticket_price;
            $order->account_id = $account_id;
            $order->event_id = $event_id;

            // Calculating grand total including tax
            $orderService = new OrderService($ticket_price, 0, $event);
            $orderService->calculateFinalCosts();
            $order->taxamt = $orderService->getTaxAmount();

            if ($orderService->getGrandTotal() == 0) {
                $order->is_payment_received = 1;
            }

            $order->save();

            /*
             * Update qty sold
             */
            $ticket = Ticket::scope()->find($ticket_id);

            $ticket->increment('quantity_sold');
            $ticket->increment('sales_volume', $ticket_price);

            /*
             * Insert order item
             */
            $orderItem = new OrderItem();
            $orderItem->title = $ticket->title;
            $orderItem->quantity = $request->get('ticket_quantity');
            $orderItem->order_id = $order->id;
            $orderItem->unit_price = $ticket_price;
            $orderItem->save();

            /*
             * Update the event stats
             */
            $event_stats = new EventStats();
            $event_stats->updateTicketsSoldCount($event_id, $request->get('ticket_quantity'));
            $event_stats->updateTicketRevenue($ticket_id, $ticket_price);

            /*
             * Create the attendee
             */
            $attendee = new Attendee();
            $attendee->first_name = $attendee_first_name;
            $attendee->last_name = $attendee_last_name;
            $attendee->email = $attendee_email;
            $attendee->event_id = $event_id;
            $attendee->order_id = $order->id;
            $attendee->ticket_id = $ticket_id;
            $attendee->account_id = $account_id;
            $attendee->reference_index = 1;
            $attendee->ticket_type = $request->get("ticket_type") ?? "single";
            $attendee->expiry = ($request->get("ticket_type") === 'multi_entry')
                ?  Carbon::createFromFormat('Y-m-d H:i:s', $request->get('expiry'))->toDateTimeString()
                : NULL;
            $attendee->phone_number = $request->get("phone_number");
            $attendee->status = "active";

            $attendee->save();

            DB::commit();

            $smsMessage = config('app.sms.customSMSMessage');
            $ticketUrl = route('showOrderWithAttendeeRef', ['order_reference' => $order->order_reference]);
            $companyName = "Attendize";
            $sendMessage = str_replace(
                ['${customerName}', '${ticketURL}', '${companyName}'],
                [$request->get("name"), $ticketUrl, $companyName],
                $smsMessage
            );

            \Log::info("inspecting message to be drafted before sending it", [$sendMessage]);
            //todo send sms later would make its a job
            (new TextMessaging)->sendTextMessage($sendMessage, $request->get("phone_number"));
            return $this->success($attendee);
        } catch (Exception $e) {

            Log::error("an error occurred creating attendee tickets", [$e]);
            DB::rollBack();

            return $this->error();
        }
    }

    public function verify(Request $request)
    {
        try {
            $privateRefNumber = $request->get("private_reference_number");

            $verifyAttendee = Attendee::where("private_reference_number", $privateRefNumber)->first();


            if (!$verifyAttendee) {
                return $this->validationFailed([], "InvalidRefNumber");
            }
            \Log::info("attendee status here", [$verifyAttendee]);

            // Handle based on the ticket type
            if ($verifyAttendee->ticket_type === 'single') {
                return $this->handleSingleTicket($verifyAttendee);
            }

            if ($verifyAttendee->ticket_type === 'multi_entry') {
                return $this->handleMultiEntryTicket($verifyAttendee);
            }

            // Fallback if no valid ticket type is found
            return $this->validationFailed([], "Invalid ticket type");
        } catch (Exception $e) {
            Log::error("an error occurred verifying  tickets", [$e]);
            throw $e;
            return $this->error();
        }
    }
    private function handleMultiEntryTicket($verifyAttendee)
    {
        \Log::info("expiry_date", [$verifyAttendee->expiry]);

        $expiry = Carbon::createFromFormat('Y-m-d H:i:s', $verifyAttendee->expiry);
        \Log::info("checking expiry infomation", [$expiry]);

        // If the ticket has expired, mark it as inactive
        if ($expiry->isPast()) {
            $verifyAttendee->status = "inactive";
            $verifyAttendee->save();
            return $this->validationFailed([], "Ticket has expired");
        }

        // Allow the use of the multi-entry ticket without changing its status
        return $this->success($verifyAttendee);
    }

    private function handleSingleTicket($verifyAttendee)
    {
        // Check if the ticket is already used
        if ($verifyAttendee->status === 'inactive') {
            return $this->validationFailed([], "Ticket has already been used");
        }

        // Mark the single-use ticket as inactive
        $verifyAttendee->status = "inactive";
        $verifyAttendee->save();

        return $this->success($verifyAttendee);
    }
    public function destroy(Request $request) {}
}
