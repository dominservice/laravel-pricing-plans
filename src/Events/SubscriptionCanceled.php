<?php

namespace Dominservice\PricingPlans\Events;

use Dominservice\PricingPlans\Models\PlanSubscription;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class SubscriptionCanceled
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    /**
     * Create a new event instance.
     *
     * @param  \Dominservice\PricingPlans\Models\PlanSubscription $subscription
     */
    public function __construct(public PlanSubscription $subscription)
    {
        //
    }
}
