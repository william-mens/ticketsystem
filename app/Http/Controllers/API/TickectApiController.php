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
use Exception;
use Log;


class TicketApiController extends Controller
{

    public function store(Request $request)
    {

        $ticket_id = $request->get('ticket_id');
        $event = Event::find(1);
        $ticket_price = 0;
        $attendee_first_name = $request->get('name');
        $attendee_last_name = $request->get('name');
        $attendee_email = $request->get('email');
        $event_id  = $request->get("event_id") ?? 1;
        $account_id = $request->get("account_id") ?? 1;

        DB::beginTransaction();

        try {

            /*
             * Create the order
             */
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
            $orderItem->quantity = 1;
            $orderItem->order_id = $order->id;
            $orderItem->unit_price = $ticket_price;
            $orderItem->save();

            /*
             * Update the event stats
             */
            $event_stats = new EventStats();
            $event_stats->updateTicketsSoldCount($event_id, 1);
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
            $attendee->expiry = ($request->get("ticket_type") === 'multi_entry') ? $request->get("expiry_date") : NULL;
            $attendee->status = "active";

            $attendee->save();

            DB::commit();
            return $this->success($attendee);
        } catch (Exception $e) {

            Log::error("an error occurred creating attendee tickets", [$e]);
            DB::rollBack();

            return $this->error();
        }
    }

    public function verify(Request $request) {}

    public function destroy(Request $request) {}
}
