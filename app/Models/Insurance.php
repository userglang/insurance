<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Insurance extends Model
{
    use HasFactory;

    /**
     * The table associated with the model.
     */
    protected $table = 'insurances';

    /**
     * The primary key type.
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
        'id',
        'insurance_name',
        'insurance_type',
        'description',
        'amount',
        'is_active',
    ];

    /**
     * The attributes that should be cast.
     */
    protected $casts = [
        'amount' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Boot method for the model.
     * Automatically sets UUID on creating.
     */
    protected static function boot()
    {
        parent::boot();

        static::creating(function ($model) {
            if (empty($model->id)) {
                $model->id = (string) Str::uuid();
            }
        });
    }

    /**
     * Get all of the subscription for the Insurance
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */

    public function subscriptions()
    {
        return $this->hasMany(Subscription::class, 'insurance_id', 'id');
    }
}
