<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientAddress extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'province_id',
        'district_id',
        'commune_id',
        'village_id',
        'street_address',
        'is_current',
        'address_type_id',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function addressType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'address_type_id');
    }

    public function province(): BelongsTo
    {
        return $this->belongsTo(Gazetteer::class, 'province_id');
    }

    public function district(): BelongsTo
    {
        return $this->belongsTo(Gazetteer::class, 'district_id');
    }

    public function commune(): BelongsTo
    {
        return $this->belongsTo(Gazetteer::class, 'commune_id');
    }

    public function village(): BelongsTo
    {
        return $this->belongsTo(Gazetteer::class, 'village_id');
    }

    // Accessors
    public function getFullAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street_address,
            $this->village?->name,
            $this->commune?->name,
            $this->district?->name,
            $this->province?->name,
        ]);

        return implode(', ', $parts);
    }

    // Scopes
    public function scopeCurrent($query)
    {
        return $query->where('is_current', true);
    }

    /**
     * Scope to search addresses by gazetteer names
     */
    public function scopeSearchByAddress($query, string $searchTerm)
    {
        return $query->whereHas('province', function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%");
        })->orWhereHas('district', function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%");
        })->orWhereHas('commune', function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%");
        })->orWhereHas('village', function ($q) use ($searchTerm) {
            $q->where('name', 'LIKE', "%{$searchTerm}%");
        })->orWhere('street_address', 'LIKE', "%{$searchTerm}%");
    }

    /**
     * Scope to filter by province
     */
    public function scopeInProvince($query, int $provinceId)
    {
        return $query->where('province_id', $provinceId);
    }

    /**
     * Scope to filter by district
     */
    public function scopeInDistrict($query, int $districtId)
    {
        return $query->where('district_id', $districtId);
    }

    /**
     * Scope to filter by commune
     */
    public function scopeInCommune($query, int $communeId)
    {
        return $query->where('commune_id', $communeId);
    }

    /**
     * Scope to filter by village
     */
    public function scopeInVillage($query, int $villageId)
    {
        return $query->where('village_id', $villageId);
    }

    /**
     * Validate the address hierarchy using gazetteer relationships
     */
    public function validateAddressHierarchy(): array
    {
        $errors = [];

        // Check if district belongs to province
        if ($this->district && $this->district->parent_id !== $this->province_id) {
            $errors['district_id'] = 'District does not belong to the selected province.';
        }

        // Check if commune belongs to district
        if ($this->commune && $this->commune->parent_id !== $this->district_id) {
            $errors['commune_id'] = 'Commune does not belong to the selected district.';
        }

        // Check if village belongs to commune
        if ($this->village && $this->village->parent_id !== $this->commune_id) {
            $errors['village_id'] = 'Village does not belong to the selected commune.';
        }

        return [
            'valid' => empty($errors),
            'errors' => $errors
        ];
    }

    /**
     * Get the hierarchical address path
     */
    public function getAddressPathAttribute(): array
    {
        return [
            'province' => $this->province?->name,
            'district' => $this->district?->name,
            'commune' => $this->commune?->name,
            'village' => $this->village?->name,
            'street' => $this->street_address,
        ];
    }

    /**
     * Get formatted address with gazetteer names
     */
    public function getFormattedAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street_address,
            $this->village?->name,
            $this->commune?->name,
            $this->district?->name,
            $this->province?->name,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Get address in Khmer if available
     */
    public function getKhmerAddressAttribute(): string
    {
        $parts = array_filter([
            $this->street_address, // Street address typically stays in original language
            $this->village?->name_kh ?? $this->village?->name,
            $this->commune?->name_kh ?? $this->commune?->name,
            $this->district?->name_kh ?? $this->district?->name,
            $this->province?->name_kh ?? $this->province?->name,
        ]);

        return implode(', ', $parts);
    }

    /**
     * Create address validation rules
     */
    public static function getValidationRules(): array
    {
        return [
            'patient_id' => 'required|exists:patients,id',
            'province_id' => 'required|exists:gazetteers,id',
            'district_id' => 'required|exists:gazetteers,id',
            'commune_id' => 'required|exists:gazetteers,id',
            'village_id' => 'required|exists:gazetteers,id',
            'street_address' => 'required|string|max:255',
            'is_current' => 'boolean',
            'address_type_id' => 'required|exists:terms,id',
        ];
    }

    /**
     * Boot method to add model events
     */
    protected static function boot()
    {
        parent::boot();

        // Validate address hierarchy before saving
        static::saving(function ($address) {
            $validation = $address->validateAddressHierarchy();
            if (!$validation['valid']) {
                throw new \InvalidArgumentException(
                    'Invalid address hierarchy: ' . implode(', ', $validation['errors'])
                );
            }
        });

        // Set only one current address per patient
        static::saving(function ($address) {
            if ($address->is_current) {
                static::where('patient_id', $address->patient_id)
                    ->where('id', '!=', $address->id)
                    ->update(['is_current' => false]);
            }
        });
    }

    /**
     * Check if address is complete (has all required gazetteer levels)
     */
    public function isComplete(): bool
    {
        return !empty($this->province_id) &&
               !empty($this->district_id) &&
               !empty($this->commune_id) &&
               !empty($this->village_id) &&
               !empty($this->street_address);
    }

    /**
     * Get missing address components
     */
    public function getMissingComponents(): array
    {
        $missing = [];

        if (empty($this->province_id)) {
            $missing[] = 'province';
        }
        if (empty($this->district_id)) {
            $missing[] = 'district';
        }
        if (empty($this->commune_id)) {
            $missing[] = 'commune';
        }
        if (empty($this->village_id)) {
            $missing[] = 'village';
        }
        if (empty($this->street_address)) {
            $missing[] = 'street_address';
        }

        return $missing;
    }

    /**
     * Get address coordinates if available (placeholder for future GPS integration)
     */
    public function getCoordinatesAttribute(): ?array
    {
        // Placeholder for future GPS coordinate integration
        // This could be extended to include latitude/longitude fields
        return null;
    }
}
