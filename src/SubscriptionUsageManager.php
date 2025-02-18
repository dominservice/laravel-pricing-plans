<?php

namespace Dominservice\PricingPlans;

use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Dominservice\PricingPlans\Models\Feature;
use Dominservice\PricingPlans\Models\PlanSubscription;
use Dominservice\PricingPlans\Models\PlanSubscriptionUsage;

class SubscriptionUsageManager
{
    /**
     * Subscription model instance.
     *
     * @var \Dominservice\PricingPlans\Models\PlanSubscription
     */
    protected $subscription;

    /**
     * Create new Subscription Usage Manager instance.
     *
     * @param \Dominservice\PricingPlans\Models\PlanSubscription $subscription
     */
    public function __construct(PlanSubscription $subscription)
    {
        $this->subscription = $subscription;
    }

    /**
     * Record usage.
     *
     * This will create or update a usage record.
     *
     * @param string $featureCode
     * @param int $uses
     * @param bool $incremental
     * @return \Dominservice\PricingPlans\Models\PlanSubscriptionUsage
     * @throws \Throwable
     */
    public function record(string $featureCode, $uses = 1, $incremental = true)
    {
        /** @var \Dominservice\PricingPlans\Models\Feature $feature */
        $feature = Feature::code($featureCode)->first();

        /** @var PlanSubscriptionUsage $usage */
        $usage = $this->subscription->usage()->firstOrNew([
            'feature_code' => $feature->code,
        ]);

        try {
            DB::beginTransaction();
            if ($feature->isResettable()) {
                // Set expiration date when the usage record is new or doesn't have one.
                if (is_null($usage->valid_until)) {
                    // Set date from subscription creation date so the reset period match the period specified
                    // by the subscription's plan.
                    $usage->valid_until = $feature->getResetTime($this->subscription->starts_at);
                } elseif ($usage->isExpired()) {
                    // If the usage record has been expired, let's assign
                    // a new expiration date and reset the uses to zero.
                    $usage->valid_until = $feature->getResetTime($this->subscription->starts_at);
                    $usage->used = 0;
                }
            }

            $usage->used = max($incremental ? $usage->used + $uses : $uses, 0);

            $usage->saveOrFail();
            DB::commit();
        } catch (\Exception $e) {
            DB::rollBack();
            throw new $e;
        }
        return $usage;
    }

    /**
     * Reduce usage.
     *
     * @param int $featureId
     * @param int $uses
     * @return \Dominservice\PricingPlans\Models\PlanSubscriptionUsage
     * @throws \Throwable
     */
    public function reduce($featureId, $uses = 1)
    {
        return $this->record($featureId, -$uses);
    }

    /**
     * Save usage data.
     *
     * @param PlanSubscriptionUsage|null $planSubscriptionUsage
     *
     * @return self
     * @throws \Throwable
     */
    public function saveHistory(PlanSubscriptionUsage $planSubscriptionUsage = null)
    {
        if ($planSubscriptionUsage) {
            $features = $planSubscriptionUsage->feature()->get();
        } else {
            $features = $this->subscription->plan->features;
        }

        foreach ($features as $feature) {
            $hired = $this->subscription->ability()->value($feature->code, 0);
            // remove value with not count from save history
            if (!is_numeric($hired) || in_array(strtoupper($hired), config('plans.positive_words'))) {
                continue;
            }

            $history = $this->subscription->history()->firstOrNew([
                'feature_code' => $feature->code,
                'starts_at'    => $this->subscription->starts_at->toDateString(),
                'plan_id'      => $this->subscription->plan_id
            ]);
            $history->ends_at = Carbon::now()->toDateString();
            $history->used    = $this->subscription->ability()->consumed($feature->code);
            $history->hired   = $this->subscription->ability()->value($feature->code, 0);
            $history->saveOrFail();
        }

        return $this;
    }

    /**
     * Clear usage data.
     *
     * @return self
     */
    public function clear()
    {
        $this->subscription->usage()->delete();

        return $this;
    }
}
