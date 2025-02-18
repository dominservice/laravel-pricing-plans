<?php

namespace Dominservice\PricingPlans\Models;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\Eloquent\Model;
use InvalidArgumentException;
use Dominservice\PricingPlans\Events\SubscriptionCanceled;
use Dominservice\PricingPlans\Events\SubscriptionPlanChanged;
use Dominservice\PricingPlans\Events\SubscriptionRenewed;
use Dominservice\PricingPlans\Period;
use Dominservice\PricingPlans\SubscriptionAbility;
use Dominservice\PricingPlans\SubscriptionUsageManager;
use Dominservice\PricingPlans\Models\Concerns\BelongsToPlanModel;
use LogicException;

/**
 * Class PlanSubscription
 * @package Dominservice\PricingPlans\Models
 * @property int $id
 * @property string $subscriber_type
 * @property int $subscriber_id
 * @property int $plan_id
 * @property string $name
 * @property bool $canceled_immediately
 * @property bool $over_use
 * @property \Carbon\Carbon $starts_at
 * @property \Carbon\Carbon $ends_at
 * @property \Carbon\Carbon $canceled_at
 * @property \Carbon\Carbon $trial_ends_at
 * @property \Carbon\Carbon $created_at
 * @property \Carbon\Carbon $updated_at
 * @property-read \Dominservice\PricingPlans\Models\Plan $plan
 */
class PlanSubscription extends Model
{
    use BelongsToPlanModel;

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = [
        'subscriber_type',
        'subscriber_id',
        'plan_id',
        'name',
        'canceled_immediately',
        'over_use',
        'trial_ends_at',
        'starts_at',
        'ends_at',
        'canceled_at'
    ];

    /**
     * The attributes that should be mutated to dates.
     *
     * @var array
     */
    protected $dates = [
        'created_at',
        'updated_at',
        'starts_at',
        'ends_at',
        'canceled_at',
        'trial_ends_at',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'over_use' => 'boolean'
    ];

    /**
     * @var array
     */
    protected $with = ['plan'];

    /**
     * Subscription Ability Manager instance.
     *
     * @var \Dominservice\PricingPlans\SubscriptionAbility
     */
    protected $ability;

    /**
     * Subscription Ability Manager instance.
     *
     * @var \Dominservice\PricingPlans\SubscriptionUsageManager
     */
    protected $usageManager;

    /**
     * Boot function for using with User Events.
     *
     * @return void
     */
    protected static function boot()
    {
        parent::boot();

        static::saving(function ($model) {
            // Set period if it wasn't set
            if (!$model->ends_at) {
                $model->setNewPeriod();
            }
        });

        static::saved(function ($model) {
            /** @var PlanSubscription $model */
            if ($model->getOriginal('plan_id') && $model->getOriginal('plan_id') !== $model->plan_id) {
                SubscriptionPlanChanged::dispatch($model);
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
        return config('plans.tables.plan_subscriptions');
    }

    public function status(): Attribute
    {
        $that = $this;
        return Attribute::make(
            get: function($value) use($that) {
                if ($that->isCanceled()) {
                    return __('plans::messages.canceled');
                }

                if ($that->isEnded()) {
                    return __('plans::messages.ended');
                }

                if ($that->isActive()) {
                    return __('plans::messages.active');
                }

                return null;
            },
            set: fn ($value) => $value,
        );
    }

    /**
     * Get subscriber.
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function subscriber()
    {
        return $this->morphTo();
    }

    /**
     * Get subscription usage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function usage()
    {
        return $this->hasMany(
            config('plans.models.PlanSubscriptionUsage'),
            'subscription_id',
            'id'
        );
    }

    /**
     * Get subscription usage.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function history()
    {
        return $this->hasMany(
            config('plans.models.PlanSubscriptionHistory'),
            'subscription_id',
            'id'
        );
    }

    /**
     * Check if subscription is active.
     *
     * @return bool
     */
    public function isActive(): bool
    {
        if (!$this->isEnded() || $this->onTrial()) {
            return true;
        }

        return false;
    }

    /**
     * Check if subscription is trialling.
     *
     * @return bool
     */
    public function onTrial(): bool
    {
        if (!is_null($trialEndsAt = $this->trial_ends_at)) {
            return Carbon::now()->lt(Carbon::instance($trialEndsAt));
        }

        return false;
    }

    /**
     * Check if subscription is canceled.
     *
     * @return bool
     */
    public function isCanceled(): bool
    {
        return !is_null($this->canceled_at);
    }

    /**
     * Check if subscription is canceled immediately.
     *
     * @return bool
     */
    public function isCanceledImmediately(): bool
    {
        return !is_null($this->canceled_at) && $this->canceled_immediately === true;
    }


    /**
     * Check if subscription can over use contracted plan.
     *
     * @return bool
     */
    public function canOverUse(): bool
    {
        return (boolean) $this->over_use;
    }

    /**
     * Check if subscription period has ended.
     *
     * @return bool
     */
    public function isEnded(): bool
    {
        $endsAt = Carbon::instance($this->ends_at);

        return Carbon::now()->gte($endsAt);
    }

    /**
     * Cancel subscription.
     *
     * @param bool $immediately
     * @return PlanSubscription
     * @throws \Throwable
     */
    public function cancel($immediately = false)
    {
        Cache::forget(sprintf('plan_subscription_%s', $this->subscriber_id));

        if (config('plans.save_history_usage', true)) {
            $this->usageManager()->saveHistory();
        }

        $this->canceled_at = now();

        if ($immediately) {
            $this->canceled_immediately = true;
            $this->ends_at = $this->canceled_at;
        }

        $this->saveOrFail();

        SubscriptionCanceled::dispatch($this);

        return $this;
    }

    /**
     * Change subscription plan.
     *
     * @param int|\Dominservice\PricingPlans\Models\Plan $plan Plan Id or Plan Model Instance
     *
     * @return PlanSubscription|false
     * @throws InvalidArgumentException
     * @throws \Throwable
     */
    public function changePlan($plan)
    {
        if (!($plan instanceof Plan)) {
            // Try find by Plan ID
            $plan = App::make(config('plans.models.Plan'))->find($plan);

            if (!$plan) {
                // Try find by Plan Code
                $plan = App::make(config('plans.models.Plan'))->findByCode($plan);
            }
        }

        if (is_null($plan) || !($plan instanceof Plan)) {
            throw new InvalidArgumentException('Invalid plan instance');
        }

        if (config('plans.save_history_usage', true)) {
            $this->usageManager()->saveHistory();
        }

        // If plans doesn't have the same billing frequency (e.g., interval
        // and interval_count) we will update the billing dates starting
        // today... and since we are basically creating a new billing cycle,
        // the usage data will be cleared.
        if (is_null($this->plan) ||
            $this->plan->interval_unit !== $plan->interval_unit ||
            $this->plan->interval_count !== $plan->interval_count
        ) {
            // Set period
            $this->setNewPeriod($plan->interval_unit, $plan->interval_count);

            // Clear usage data
            $this->usageManager()->clear();
        }

        // Attach new plan to subscription
        $this->plan_id = $plan->id;
        Cache::forget(sprintf('plan_subscription_%s', $this->subscriber_id));
        return $this;
    }

    /**
     * Renew subscription period.
     *
     * @param bool $force
     *
     * @return self
     */
    public function renew($force = false)
    {
        if ($this->isEnded() and $this->isCanceled() and !$force) {
            throw new LogicException(
                'Unable to renew canceled ended subscription.'
            );
        }

        $subscription = $this;

        DB::transaction(function () use ($subscription) {
            // Clear usage data
            if (config('plans.save_history_usage', true)) {
                $this->usageManager()->saveHistory();
            }
            $this->usageManager()->clear();

            // Renew period
            $subscription->setNewPeriod();
            $subscription->canceled_at = null;
            $subscription->canceled_immediately = null;
            $subscription->save();
        });

        SubscriptionRenewed::dispatch($this);
        Cache::forget(sprintf('plan_subscription_%s', $this->subscriber_id));
        return $this;
    }

    /**
     * Get Subscription Ability instance.
     *
     * @return \Dominservice\PricingPlans\SubscriptionAbility
     */
    public function ability()
    {
        if (is_null($this->ability)) {
            return  $this->ability = new SubscriptionAbility($this);
        }

        return $this->ability;
    }

    /**
     * Get Subscription Ability instance.
     *
     * @return \Dominservice\PricingPlans\SubscriptionUsageManager
     */
    public function usageManager()
    {
        if (is_null($this->usageManager)) {
            return $this->usageManager = new SubscriptionUsageManager($this);
        }

        return $this->usageManager;
    }

    /**
     * Find by user id.
     *
     * @param  \Illuminate\Database\Eloquent\Builder
     * @param  \Dominservice\PricingPlans\Contracts\Subscriber $subscriber
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeBySubscriber($query, $subscriber)
    {
        return $query->where('subscriber_id', $subscriber->getKey())
            ->where('subscriber_type', get_class($subscriber));
    }

    /**
     * Find subscription with an ending trial.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $dayRange
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEndingTrial($query, $dayRange = 3)
    {
        $from = Carbon::now();
        $to = Carbon::now()->addDays($dayRange)->endOfDay();

        return $query->whereBetween('trial_ends_at', [$from, $to]);
    }

    /**
     * Find subscription with an ended trial.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEndedTrial($query)
    {
        return $query->where('trial_ends_at', '<=', Carbon::now());
    }

    /**
     * Find ending subscriptions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @param int $dayRange
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEndingPeriod($query, $dayRange = 3)
    {
        $from = Carbon::now();
        $to = Carbon::now()->addDays($dayRange)->endOfDay();

        return $query->whereBetween('ends_at', [$from, $to]);
    }

    /**
     * Find ended subscriptions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeEndedPeriod($query)
    {
        return $query->where('ends_at', '<=', Carbon::now());
    }

    /**
     * Find ending subscriptions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeCanceled($query)
    {
        return $query->whereNotNull('canceled_at')->where('canceled_at', '<=', Carbon::now());
    }

    /**
     * Find ending subscriptions.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     * @return \Illuminate\Database\Eloquent\Builder
     */
    public function scopeActive($query)
    {
        return $query->whereNull('canceled_at');
    }

    /**
     * Set subscription period.
     *
     * @param string                    $intervalUnit
     * @param int                       $intervalCount
     * @param null|int|string|\DateTime $startAt Start time
     *
     * @return  PlanSubscription
     * @throws \Exception
     */
    protected function setNewPeriod(string $intervalUnit = '', int $intervalCount = 0, $startAt = null)
    {
        if (empty($intervalUnit)) {
            $intervalUnit = $this->plan->interval_unit;
        }

        if (empty($intervalCount)) {
            $intervalCount = $this->plan->interval_count;
        }

        $period = new Period($intervalUnit, $intervalCount, $startAt);

        $this->starts_at = $period->getStartAt();
        $this->ends_at = $period->getEndAt();
        Cache::forget(sprintf('plan_subscription_%s', $this->subscriber_id));
        return $this;
    }
}
