<?php

namespace Dominservice\PricingPlans\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;
use Dominservice\PricingPlans\Models\Concerns\BelongsToPlanModel;

/**
 * Class PlanFeature
 * @package Dominservice\PricingPlans\Models
 * @property int $id
 * @property int $plan_id
 * @property int $feature_id
 * @property int|string $value
 * @property string $note
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 */
class PlanFeature extends Pivot
{
    use BelongsToPlanModel;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'plan_id',
        'feature_id',
        'value',
        'note',
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
        return config('plans.tables.plan_features');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function feature()
    {
        return $this->belongsTo(
            config('plans.models.Feature'),
            'feature_id',
            'id'
        );
    }
}
