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

    /**
     * The table associated with the model.
     */
    protected $table = 'subscriptions';

    /**
     * The primary key for the model.
     */
    protected $primaryKey = 'id';

    /**
     * The "type" of the primary key ID.
     */
    protected $keyType = 'string';

    /**
     * Indicates if the IDs are auto-incrementing.
     */
    public $incrementing = false;

    /**
     * The attributes that are mass assignable.
     */
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

    /**
     * The attributes that should be cast.
     */
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
        static::creating(function ($model) {
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

            // Set created_by and updated_by from authenticated user
            if (Auth::check()) {
                $model->created_by = Auth::id();
                $model->updated_by = Auth::id();
            }
        });

        static::updating(function ($model) {

            // Update expires_at if subscription is being reactivated
            if ($model->isDirty('is_active') && $model->is_active && empty($model->expires_at)) {
                $model->expires_at = now()->addDays(365);
            }
            // Reset expires_at if changing activation date
            elseif ($model->isDirty('activated_at') && !empty($model->activated_at)) {
                $model->expires_at = Carbon::parse($model->activated_at)->addDays(365);
            }

            if (Auth::check()) {
                $model->updated_by = Auth::id();
            }
        });

    }

    /**
     * Get the member that owns the subscription.
     */
    public function member()
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get the product account for this subscription.
     */
    public function productAccount()
    {
        return $this->belongsTo(ProductAccount::class);
    }

    /**
     * Get the insurance that owns the Subscription
     *
     * @return \Illuminate\Database\Eloquent\Relations\BelongsTo
     */
    public function insurance()
    {
        return $this->belongsTo(Insurance::class);
    }

    /**
     * Get the user who created the subscription.
     */
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Get the user who last updated the subscription.
     */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    /**
     * Scope a query to only include active subscriptions.
     */
    public function scopeActive($query)
    {
        return $query->where('expires_at', '>', now());
    }


    /**
     * Scope a query to only include expired subscriptions.
     */
    public function scopeExpired($query)
    {
        return $query->where('expires_at', '<=', now());
    }

    /**
     * Scope a query to only include future subscriptions.
     */
    public function scopeFuture($query)
    {
        return $query->where('activated_at', '>', now());
    }

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

    /**
     * Scope a query to filter by member.
     */
    public function scopeForMember($query, $memberId)
    {
        return $query->where('member_id', $memberId);
    }

    /**
     * Check if the subscription is currently active.
     */
    public function isActive(): bool
    {
        $now = now();
        return $this->activated_at <= $now && $this->expires_at > $now;
    }

    /**
     * Check if the subscription has expired.
     */
    public function isExpired(): bool
    {
        return $this->expires_at <= now();
    }

    /**
     * Check if the subscription is scheduled for future activation.
     */
    public function isFuture(): bool
    {
        return $this->activated_at > now();
    }

    /**
     * Get the number of days remaining until expiration.
     */
    public function daysRemaining(): int
    {
        if ($this->isExpired()) {
            return 0;
        }

        return now()->diffInDays($this->expires_at);
    }

    /**
     * Extend the subscription by a given number of days.
     */
    public function extendBy(int $days): bool
    {
        $this->expires_at = $this->expires_at->addDays($days);
        return $this->save();
    }

    /**
     * Get the subscription status.
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
