<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Auth;
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
        'status', // ← Added status to fillable
        'remark',
        'created_at',
    ];

    protected $casts = [
        'birth_date' => 'date',
        'is_active' => 'boolean',
        'gender' => 'string',
        'marital_status' => 'string',
        'status' => 'string', // ← Cast status as string
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

            if (Auth::check()) {
                $model->status = 'accepted'; // Automatically accept if created by an authenticated user
            }
        });
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }
    public function scopeArchive($query)
    {
        return $query->where('is_active', false);
    }

    public function scopeNewThisMonth($query)
    {
        return $query->where('created_at', '>=', now()->startOfMonth());
    }

    public function scopeStatus($query, string $status)
    {
        return $query->where('status', $status);
    }

    public function scopePending($query)
    {
        return $query->status('pending');
    }

    public function scopeAccepted($query)
    {
        return $query->status('accepted');
    }

    public function scopeDeclined($query)
    {
        return $query->status('declined');
    }

    // Relationships
    public function branch(): BelongsTo
    {
        return $this->belongsTo(Branch::class, 'branch_number', 'branch_number');
    }

    public function productAccounts(): HasMany
    {
        return $this->hasMany(ProductAccount::class);
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    public function latestSubscription()
    {
        return $this->hasOne(Subscription::class)->latestOfMany('expires_at');
    }

    public function latestPayment()
    {
        return $this->hasOne(Subscription::class)->latestOfMany('payment_date');
    }


    public function latestActiveSubscription()
    {
        return $this->hasOne(Subscription::class)
            ->active()
            ->latest('expires_at');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        $parts = array_filter([
            $this->last_name,
            ', ',
            $this->first_name,
            $this->middle_name,
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

    /**
     * Advanced similarity checking - handles null middle names gracefully
     */
    public static function findSimilarMembers($firstName, $lastName, $middleName = null, $birthDate = null, $excludeId = null)
    {
        // Base query: only active members with matching first & last name
        $baseQuery = static::query()
            ->where('is_active', true)
            ->where('first_name', $firstName)
            ->where('last_name', $lastName);

        if ($birthDate) {
            $baseQuery->where('birth_date', $birthDate);
        }

        if ($excludeId) {
            $baseQuery->where('id', '!=', $excludeId);
        }

        // Exact match: middle name must match exactly or be null if not provided
        $exactMatch = (clone $baseQuery);
        if ($middleName !== null) {
            $exactMatch->where('middle_name', $middleName);
        } else {
            $exactMatch->whereNull('middle_name');
        }

        // Similar match: middle name contains, or is null (for broader matching)
        $similarMatch = (clone $baseQuery);
        if ($middleName !== null) {
            $similarMatch->where(function ($q) use ($middleName) {
                $q->where('middle_name', 'LIKE', "%$middleName%")
                    ->orWhereNull('middle_name');
            });
        }

        return [
            'exact' => $exactMatch->get(),
            'similar' => $similarMatch->get(),
        ];
    }

}
