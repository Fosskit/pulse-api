<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicationAdministration extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'visit_id',
        'medication_request_id',
        'status_id',
        'administrator_id',
        'dose_given',
        'dose_unit_id',
        'administered_at',
        'notes',
        'vital_signs_before',
        'vital_signs_after',
        'adverse_reactions',
    ];

    protected $casts = [
        'dose_given' => 'decimal:2',
        'administered_at' => 'datetime',
        'vital_signs_before' => 'array',
        'vital_signs_after' => 'array',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = \Illuminate\Support\Str::ulid();
            }
        });
    }

    // Relationships
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function medicationRequest(): BelongsTo
    {
        return $this->belongsTo(MedicationRequest::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'status_id');
    }

    public function administrator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'administrator_id');
    }

    public function doseUnit(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'dose_unit_id');
    }

    // Accessors
    public function getIsAdministeredAttribute(): bool
    {
        return $this->dose_given > 0;
    }

    public function getHasAdverseReactionsAttribute(): bool
    {
        return !empty($this->adverse_reactions);
    }

    public function getPatientAttribute()
    {
        return $this->visit->patient ?? null;
    }

    // Scopes
    public function scopeAdministered($query)
    {
        return $query->where('dose_given', '>', 0);
    }

    public function scopeForVisit($query, $visitId)
    {
        return $query->where('visit_id', $visitId);
    }

    public function scopeForPatient($query, $patientId)
    {
        return $query->whereHas('visit', function ($q) use ($patientId) {
            $q->where('patient_id', $patientId);
        });
    }

    public function scopeWithAdverseReactions($query)
    {
        return $query->whereNotNull('adverse_reactions');
    }

    public function scopeToday($query)
    {
        return $query->whereDate('administered_at', today());
    }

    public function scopeInDateRange($query, $startDate, $endDate)
    {
        return $query->whereBetween('administered_at', [$startDate, $endDate]);
    }
}