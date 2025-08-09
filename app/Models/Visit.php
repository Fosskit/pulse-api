<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Visit extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'facility_id',
        'visit_type_id',
        'admission_type_id',
        'admitted_at',
        'discharged_at',
        'discharge_type_id',
        'visit_outcome_id',
    ];

    protected $casts = [
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function visitType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'visit_type_id');
    }

    public function admissionType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'admission_type_id');
    }

    public function dischargeType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'discharge_type_id');
    }

    public function visitOutcome(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'visit_outcome_id');
    }

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function medicationRequests(): HasMany
    {
        return $this->hasMany(MedicationRequest::class);
    }

    public function serviceRequests(): HasMany
    {
        return $this->hasMany(ServiceRequest::class);
    }

    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return is_null($this->discharged_at);
    }

    public function getDurationAttribute(): ?int
    {
        if (!$this->admitted_at) {
            return null;
        }

        $endDate = $this->discharged_at ?? now();
        return $this->admitted_at->diffInDays($endDate);
    }
}