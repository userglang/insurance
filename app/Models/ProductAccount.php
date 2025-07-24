<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class ProductAccount extends Model
{
    /**
     * The primary key type is UUID.
     */
    protected $keyType = 'string';
    public $incrementing = false;

    /**
     * Mass assignable attributes.
     */
    protected $fillable = [
        'id',
        'member_id',
        // 'product_id',
        'product_name',
        'account_number',
    ];

    /**
     * Automatically boot UUID for primary key.
     */
    protected static function booted(): void
    {
        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get the member that owns this product account.
     */
    public function member(): BelongsTo
    {
        return $this->belongsTo(Member::class);
    }

    /**
     * Get all of the subscription for the ProductAccount
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function subscriptions()
    {
        return $this->hasMany(Subscription::class);
    }

}
