<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Support\Str;

class Branch extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'branches';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'branch_number',
        'branch_name',
        'address',
        'code',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Automatically generate UUID for the primary key.
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

    // Relationships
    public function users()
    {
        return $this->hasMany(User::class, 'branch_id', 'id');
    }

    public function branches()
    {
        return $this->hasMany(Branch::class, 'branch_number', 'branch_number');
    }

    public function members()
    {
        return $this->hasMany(Member::class, 'branch_number', 'branch_number');
    }
}
