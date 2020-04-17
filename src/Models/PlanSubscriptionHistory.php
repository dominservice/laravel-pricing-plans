<?php

namespace Laravel\PricingPlans\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Config;

/**
 * Class PlanSubscriptionUsage
 * @package Laravel\PricingPlans\Models
 * @property int $id
 * @property int $subscription_id
 * @property string $feature_code
 * @property int $used
 * @property \Carbon\Carbon $valid_until
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
        'starts_at',
        'ends_at',
        'used',
        'hired',
        'feature_code',
        'plan_id'
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
     * Plan constructor.
     *
     * @param array $attributes
     */
    public function __construct(array $attributes = [])
    {
        parent::__construct($attributes);

        $this->setTable(Config::get('plans.tables.plan_subscription_history'));
    }

    /**
     * Get feature.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function feature()
    {
        return $this->belongsTo(
            Config::get('plans.models.Feature'),
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
            Config::get('plans.models.PlanSubscription'),
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
            Config::get('plans.models.Plan'),
            'plan_id',
            'id'
        );
    }

    /**
     * Scope by feature code.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param string|\Laravel\PricingPlans\Models\Feature $feature
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
     * @param string|\Laravel\PricingPlans\Models\Feature $planSubscription
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
