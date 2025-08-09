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
}
