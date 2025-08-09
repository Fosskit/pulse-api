<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'facility_id',
    ];

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function demographics(): HasOne
    {
        return $this->hasOne(PatientDemographic::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PatientAddress::class);
    }

    public function currentAddress(): HasOne
    {
        return $this->hasOne(PatientAddress::class)->where('is_current', true);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(PatientIdentity::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    // Active insurance method for current coverage determination
    public function activeInsurance()
    {
        return $this->identities()
            ->active()
            ->with(['card' => function ($query) {
                $query->active();
            }])
            ->whereHas('card', function ($query) {
                $query->active();
            });
    }

    // Get the current active insurance card
    public function getActiveInsuranceAttribute()
    {
        return app(\App\Services\PatientInsuranceService::class)->getActiveInsurance($this);
    }

    // Check if patient has active insurance
    public function hasActiveInsurance(): bool
    {
        return app(\App\Services\PatientInsuranceService::class)->getActiveInsurance($this) !== null;
    }

    // Get payment type based on active insurance using service
    public function getPaymentTypeId(): ?int
    {
        return app(\App\Services\PatientInsuranceService::class)->determinePaymentTypeId($this);
    }

    // Check if patient is a beneficiary (has active insurance)
    public function isBeneficiary(): bool
    {
        return app(\App\Services\PatientInsuranceService::class)->isBeneficiary($this);
    }

    // Get comprehensive beneficiary status
    public function getBeneficiaryStatus(): array
    {
        return app(\App\Services\PatientInsuranceService::class)->getBeneficiaryStatus($this);
    }

    // Get all active insurances (for patients with multiple valid cards)
    public function getAllActiveInsurances()
    {
        return app(\App\Services\PatientInsuranceService::class)->getAllActiveInsurances($this);
    }

    // Get insurance history
    public function getInsuranceHistory()
    {
        return app(\App\Services\PatientInsuranceService::class)->getInsuranceHistory($this);
    }

    // Get insurance summary for invoice generation
    public function getInsuranceSummaryForInvoice(): array
    {
        return app(\App\Services\PatientInsuranceService::class)->getInsuranceSummaryForInvoice($this);
    }

    // Search scopes
    public function scopeByCode($query, string $code)
    {
        return $query->where('code', 'LIKE', "%{$code}%");
    }

    public function scopeByDemographics($query, array $demographics)
    {
        return $query->whereHas('demographics', function ($q) use ($demographics) {
            foreach ($demographics as $field => $value) {
                if (in_array($field, ['surname', 'name', 'phone'])) {
                    $q->where($field, 'LIKE', "%{$value}%");
                } elseif ($field === 'sex') {
                    $q->where('sex', $value);
                } elseif ($field === 'birthdate') {
                    $q->whereDate('birthdate', $value);
                }
            }
        });
    }

    public function scopeByIdentityCode($query, string $identityCode)
    {
        return $query->whereHas('identities', function ($q) use ($identityCode) {
            $q->where('code', 'LIKE', "%{$identityCode}%");
        });
    }

    public function scopeByAddress($query, array $addressFilters)
    {
        return $query->whereHas('addresses', function ($q) use ($addressFilters) {
            if (isset($addressFilters['province_id'])) {
                $q->where('province_id', $addressFilters['province_id']);
            }
            if (isset($addressFilters['district_id'])) {
                $q->where('district_id', $addressFilters['district_id']);
            }
            if (isset($addressFilters['commune_id'])) {
                $q->where('commune_id', $addressFilters['commune_id']);
            }
            if (isset($addressFilters['village_id'])) {
                $q->where('village_id', $addressFilters['village_id']);
            }
            if (isset($addressFilters['street_address'])) {
                $q->where('street_address', 'LIKE', "%{$addressFilters['street_address']}%");
            }
        });
    }

    // Comprehensive search scope combining all search criteria
    public function scopeSearch($query, array $filters)
    {
        if (isset($filters['code'])) {
            $query->byCode($filters['code']);
        }

        if (isset($filters['demographics'])) {
            $query->byDemographics($filters['demographics']);
        }

        if (isset($filters['identity_code'])) {
            $query->byIdentityCode($filters['identity_code']);
        }

        if (isset($filters['address'])) {
            $query->byAddress($filters['address']);
        }

        return $query;
    }

    // Accessors
    public function getFullNameAttribute(): ?string
    {
        return $this->demographics?->full_name;
    }

    public function getAgeAttribute(): ?int
    {
        return $this->demographics?->age;
    }

    public function getIsDeceasedAttribute(): bool
    {
        return $this->demographics?->is_deceased ?? false;
    }
}
