<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Member extends Model
{
    use HasFactory, HasUuids;

    protected $table = 'members';

    protected $keyType = 'string';
    public $incrementing = false;

    protected $fillable = [
        'cid',
        'branch_number',
        'first_name',
        'last_name',
        'middle_name',
        'suffix',
        'birth_date',
        'birth_place',
        'email',
        'contact_number',
        'gender',
        'marital_status',
        'sss_gsis',
        'tin',
        'house_number',
        'street',
        'barangay',
        'city',
        'province',
        'zipcode',
        'occupation',
        'name_of_employer',
        'employment_status',
        'office_contact_number',
        'office_address',
        'is_active',
        'remark',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'gender' => 'string',
        'marital_status' => 'string',
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
    ];

    protected $appends = [
        'full_name',
        'full_address',
        'age',
        'gender_label',
        'marital_status_label',
        'formatted_birth_date',
        'employer_info',
        'contact_summary',
    ];

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
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_number', 'branch_number');
    }

    public function productAccounts(): HasMany
    {
        return $this->hasMany(ProductAccount::class, 'member_id', 'id');
    }


    // Accessors

    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->first_name,
            $this->middle_name,
            $this->last_name,
            $this->suffix,
        ]);

        return implode(' ', $parts);
    }

    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->house_number,
            $this->street,
            $this->barangay,
            $this->city,
            $this->province,
            $this->zipcode,
        ]);

        return implode(', ', $parts);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birth_date ? $this->birth_date->age : null;
    }

    public function getGenderLabelAttribute(): ?string
    {
        return match (strtolower($this->gender)) {
            'm', 'male' => 'Male',
            'f', 'female' => 'Female',
            'o', 'other' => 'Other',
            default => null,
        };
    }

    public function getMaritalStatusLabelAttribute(): ?string
    {
        return match (strtolower($this->marital_status)) {
            's', 'single' => 'Single',
            'm', 'married' => 'Married',
            'w', 'widowed' => 'Widowed',
            'd', 'divorced' => 'Divorced',
            default => null,
        };
    }

    public function getFormattedBirthDateAttribute(): ?string
    {
        return $this->birth_date ? $this->birth_date->format('F d, Y') : null;
    }

    public function getEmployerInfoAttribute(): ?string
    {
        if (!$this->name_of_employer) return null;

        $address = $this->office_address ? " ({$this->office_address})" : '';
        return "{$this->name_of_employer}{$address}";
    }

    public function getContactSummaryAttribute(): string
    {
        $mobile = $this->contact_number ?? 'N/A';
        $office = $this->office_contact_number ?? 'N/A';

        return "Mobile: $mobile, Office: $office";
    }
}
