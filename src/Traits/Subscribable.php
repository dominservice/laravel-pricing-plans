<?php

namespace Dominservice\PricingPlans\Models\Concerns;

use Illuminate\Support\Facades\Cache;
use Dominservice\PricingPlans\Models\Plan;
use Dominservice\PricingPlans\SubscriptionBuilder;

trait Subscribable
{
    /**
     * Get user plan subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\MorphMany
     */
    public function subscriptions()
    {
        return $this->morphMany(
            config('plans.models.PlanSubscription'),
            'subscriber'
        );
    }

    /**
     * Get a subscription by name.
     *
     * @param  string $name Subscription name
     * @return \Dominservice\PricingPlans\Models\PlanSubscription|null
     */
    public function subscription(string $name = 'main')
    {
        return Cache::remember(
            sprintf('plan_subscription_%s', $this->{$this->getKeyName()}),
            60 * 24,
            function() use ($name) {
                if ($this->relationLoaded('subscriptions')) {
                    return $this->subscriptions
                        ->sortByDesc(function ($subscription) {
                            return $subscription->created_at->getTimestamp();
                        })
                        ->first(function ($subscription) use ($name) {
                            return $subscription->name === $name;
                        });
                }

                return $this->subscriptions()
                    ->where('name', $name)
                    ->orderByDesc('created_at')
                    ->first();
            });
    }

    /**
     * Check if the user has a given subscription.
     *
     * @param  string $subscription Subscription name
     * @param  string|null $planCode
     * @return bool
     */
    public function subscribed(string $subscription, string $planCode = null): bool
    {
        $planSubscription = $this->subscription($subscription);

        if (is_null($planSubscription)) {
            return false;
        }

        if (is_null($planCode) || $planCode == $planSubscription->plan->code) {
            return $planSubscription->isActive();
        }

        return false;
    }

    /**
     * Subscribe user to a new plan.
     *
     * @param string $subscription Subscription name
     * @param \Dominservice\PricingPlans\Models\Plan $plan
     * @return \Dominservice\PricingPlans\SubscriptionBuilder
     */
    public function newSubscription(string $subscription, Plan $plan)
    {
        return new SubscriptionBuilder($this, $subscription, $plan);
    }

    /**
     * Get subscription usage manager instance.
     *
     * @param  string $subscription Subscription name
     * @return \Dominservice\PricingPlans\SubscriptionUsageManager
     */
    public function subscriptionUsage(string $subscription = 'main')
    {
        $subscription = $this->subscription($subscription);
        return $subscription ? $subscription->usageManager() : null;
    }
}
