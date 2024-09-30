<?php

namespace App\Jobs;

use App\Models\Order;

/**
 * Generate all the tickets in 1 order
 */
class GenerateTicketsJob extends GenerateTicketsJobBase
{
    public $order;
    public $attendees;
    public $event;
    public $file_name;


    /**
     * Create a new job instance.
     *
     * @return void
     */
    public function __construct(Order $order)
    {
        $this->attendees = $order->attendees;
        $this->event = $order->event;
        $this->file_name = $order->order_reference;
        $this->order = $order;
        \Log::info("passed generate TicketsJobs");
    }
}
