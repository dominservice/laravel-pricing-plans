<?php

namespace Dominservice\PricingPlans\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

/**
 * Class PlanSubscriptionHistory
 * @package Dominservice\PricingPlans\Models
 * @property int $id
 * @property int $subscription_id
 * @property int $plan_id
 * @property string $feature_code
 * @property int $used
 * @property int $hired
 * @property \Carbon\Carbon $starts_at
 * @property \Carbon\Carbon $ends_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PlanSubscriptionHistory extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subscription_id',
        'plan_id',
        'feature_code',
        'used',
        'hired',
        'starts_at',
        'ends_at',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'starts_at',
        'ends_at',
        'created_at',
        'updated_at',
    ];

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('plans.tables.plan_subscription_history');
    }

    /**
     * Get feature.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function feature()
    {
        return $this->belongsTo(
            config('plans.models.Feature'),
            'feature_code',
            'code'
        );
    }

    /**
     * Get subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscription()
    {
        return $this->belongsTo(
            config('plans.models.PlanSubscription'),
            'subscription_id',
            'id'
        );
    }

    /**
     * Get subscription.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function plan()
    {
        return $this->belongsTo(
            config('plans.models.Plan'),
            'plan_id',
            'id'
        );
    }

    /**
     * Scope by feature code.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|\Dominservice\PricingPlans\Models\Feature $feature
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByFeature($query, $feature)
    {
        return $query->where('feature_code', $feature instanceof Feature ? $feature->code : $feature);
    }

    /**
     * Scope by feature code.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|\Dominservice\PricingPlans\Models\Feature $planSubscription
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySubscription($query, $planSubscription)
    {
        return $query->where(
            'subscription_id',
            $planSubscription instanceof PlanSubscription ? $planSubscription->subscriber_id : $planSubscription
        );
    }
}
