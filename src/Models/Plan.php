<?php

namespace Dominservice\PricingPlans\Models;

use Illuminate\Database\Eloquent\Model;
use Dominservice\PricingPlans\Models\Concerns\HasCode;
use Dominservice\PricingPlans\Models\Concerns\Resettable;

/**
 * Class Plan
 * @package Dominservice\PricingPlans\Models
 * @property int $id
 * @property string $name
 * @property string $code
 * @property string $description
 * @property float $price
 * @property string $interval_unit
 * @property int $interval_count
 * @property int $trial_period_days
 * @property int $sort_order
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class Plan extends Model
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
        'price',
        'interval_unit',
        'interval_count',
        'trial_period_days',
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
     * Boot function for using with User Events.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        // Default interval is 1 month
        static::saving(function ($model) {
            if (!$model->interval_unit) {
                $model->interval_unit = 'month';
            }

            if (!$model->interval_count) {
                $model->interval_count = 1;
            }
        });
    }

    /**
     * Get the table associated with the model.
     *
     * @return string
     */
    public function getTable()
    {
        return config('plans.tables.plans');
    }

    /**
     * Get plan features.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function features()
    {
        return $this
            ->belongsToMany(
                config('plans.models.Feature'),
                config('plans.tables.plan_features'),
                'plan_id',
                'feature_id'
            )
            ->using(config('plans.models.PlanFeature'))
            ->withPivot(['value', 'note'])
            ->withTimestamps()
            ->orderBy('sort_order');
    }

    /**
     * Get plan subscriptions.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(
            config('plans.models.PlanSubscription'),
            'plan_id',
            'id'
        );
    }

    /**
     * Check if plan is free.
     *
     * @return bool
     */
    public function isFree(): bool
    {
        return ((float)$this->price <= 0.00);
    }

    /**
     * Check if plan has trial.
     *
     * @return bool
     */
    public function hasTrial(): bool
    {
        return (is_numeric($this->trial_period_days) and $this->trial_period_days > 0);
    }
}
