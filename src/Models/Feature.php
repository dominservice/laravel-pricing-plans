<?php

namespace Dominservice\PricingPlans\Models;

use Illuminate\Database\Eloquent\Model;
use Dominservice\PricingPlans\Models\Concerns\HasCode;
use Dominservice\PricingPlans\Models\Concerns\Resettable;

/**
 * Class Feature
 * @package Dominservice\PricingPlans\Models
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $description
 * @property string $interval_unit
 * @property int $interval_count
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Feature extends Model
{
    use Resettable, HasCode;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'name',
        'code',
        'description',
        'interval_unit',
        'interval_count',
        'sort_order',
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
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
        return config('plans.tables.features');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function plans()
    {
        return $this->belongsToMany(
            config('plans.models.Plan'),
            config('plans.tables.plan_features'),
            'feature_id',
            'plan_id'
        )->using(config('plans.models.PlanFeature'))
        ->withTimestamps();
    }

    /**
     * Get feature usage.
     *
     * This will return all related subscriptions usages.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function usage()
    {
        return $this->hasMany(
            config('plans.models.PlanSubscriptionUsage'),
            'feature_code',
            'code'
        );
    }
}
