<?php

namespace Dominservice\PricingPlans\Events;

use Dominservice\PricingPlans\Models\PlanSubscription;

class SubscriptionRenewed
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
