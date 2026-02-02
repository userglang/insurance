<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class Subscription extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'subscriptions';

    protected $primaryKey = 'id';

    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'member_id',
        'insurance_id',
        'product_account_id',
        'amount',
        'payment_date',
        'activated_at',
        'expires_at',
        'remark',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'activated_at' => 'date',
        'expires_at' => 'date',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $model): void {
            // Check if the member already has an active subscription
            $hasActiveSubscription = Subscription::query()
                ->where('member_id', $model->member_id)
                ->where('expires_at', '>=', now())
                ->exists();

            if ($hasActiveSubscription) {
                // If the member already has an active subscription, prevent creation
                throw new \Exception('Member already has an active subscription.');
            }

            // Continue with the existing logic
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }

            if (!empty($model->activated_at) && empty($model->expires_at)) {
                $model->expires_at = Carbon::parse($model->activated_at)->addDays(365);
            }

            if (empty($model->product_account_id) || $model->product_account_id == 0) {
                $productAccount = ProductAccount::create([
                    'member_id'      => $model->member_id,
                    'product_name'   => 'CASH',
                    'account_number' => 0,
                ]);

                $model->product_account_id = $productAccount->id;
            }

            $model->setUserstamps();
        });

        static::updating(function (self $model): void {
            // If subscription is being reactivated and expires_at is empty
            if ($model->isDirty('activated_at') && !empty($model->activated_at)) {
                $model->expires_at = Carbon::parse($model->activated_at)->addDays(365);
            }

            // Only extend expires_at if subscription is active now and expires_at is empty
            if ($model->isActive() && empty($model->expires_at)) {
                $model->expires_at = now()->addDays(365);
            }

            $model->setUserstamps('updated_by');
        });
    }


    /**
     * Set created_by and/or updated_by from authenticated user.
     *
     * @param string|null $field
     */
    protected function setUserstamps(string $field = null): void
    {
        if (Auth::check()) {
            if ($field === 'updated_by') {
                $this->updated_by = Auth::id();
            } else {
                $this->created_by = Auth::id();
                $this->updated_by = Auth::id();
            }
        }
    }

    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    public function productAccount(): BelongsTo
    {
        return $this->belongsTo(ProductAccount::class);
    }

    public function insurance(): BelongsTo
    {
        return $this->belongsTo(Insurance::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }

    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    public function scopeFuture($query)
    {
        return $query->where('activated_at', '>', now());
    }

    /**
     * Scope subscriptions expiring in the next 30 days and latest per member.
     */
    public function scopeExpiringSoon($query)
    {
        return $query->whereIn('id', function ($subquery) {
            $subquery->selectRaw('id')
                ->from('subscriptions as s1')
                ->whereBetween('s1.expires_at', [now(), now()->addDays(30)])
                ->whereRaw('s1.activated_at = (
                    SELECT MAX(s2.activated_at)
                    FROM subscriptions as s2
                    WHERE s2.member_id = s1.member_id
                )');
        });
    }

    public function scopeForMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Returns true if subscription is currently active.
     */
    public function isActive(): bool
    {
        $now = now();
        return $this->activated_at !== null
            && $this->expires_at !== null
            && $this->activated_at <= $now
            && $this->expires_at > $now;
    }

    /**
     * Returns true if subscription has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at <= now();
    }

    /**
     * Returns true if subscription activates in the future.
     */
    public function isFuture(): bool
    {
        return $this->activated_at !== null && $this->activated_at > now();
    }

    /**
     * Get days remaining until expiration.
     */
    public function daysRemaining(): int
    {
        if ($this->expires_at === null || $this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->expires_at);
    }

    /**
     * Extend subscription by specified number of days.
     */
    public function extendBy(int $days): bool
    {
        if ($this->expires_at === null) {
            $this->expires_at = now();
        }

        $this->expires_at = $this->expires_at->addDays($days);

        return $this->save();
    }

    /**
     * Get subscription status attribute.
     */
    public function getStatusAttribute(): string
    {
        if ($this->isFuture()) {
            return 'future';
        }

        if ($this->isActive()) {
            return 'active';
        }

        return 'expired';
    }
}
