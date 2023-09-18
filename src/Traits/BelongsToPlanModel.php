<?php

namespace Dominservice\PricingPlans\Models\Concerns;


trait BelongsToPlanModel
{
    /**
     * Get plan.
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
     * Scope by plan id.
     *
     * @param  \Illuminate\Database\Eloquent\Builder
     * @param  int $planId
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeByPlan($query, $planId)
    {
        return $query->where('plan_id', $planId);
    }
}
