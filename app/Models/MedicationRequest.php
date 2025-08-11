<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicationRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'visit_id',
        'status_id',
        'intent_id',
        'medication_id',
        'requester_id',
        'instruction_id',
        'quantity',
        'unit_id',
    ];

    protected $casts = [
        'quantity' => 'integer',
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

    public function status(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'status_id');
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'intent_id');
    }

    public function medication(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'medication_id');
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    public function instruction(): BelongsTo
    {
        return $this->belongsTo(MedicationInstruction::class, 'instruction_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'unit_id');
    }

    public function dispenses(): HasMany
    {
        return $this->hasMany(MedicationDispense::class);
    }

    public function administrations(): HasMany
    {
        return $this->hasMany(MedicationAdministration::class);
    }

    /**
     * Get all invoice items for this medication request.
     */
    public function invoiceItems()
    {
        return $this->morphMany(InvoiceItem::class, 'invoiceable');
    }

    // Accessors
    public function getPatientAttribute()
    {
        return $this->visit->patient ?? null;
    }

    public function getTotalDispensedAttribute(): int
    {
        return $this->dispenses->sum('quantity') ?? 0;
    }

    public function getRemainingQuantityAttribute(): int
    {
        return max(0, $this->quantity - $this->total_dispensed);
    }

    public function getIsFullyDispensedAttribute(): bool
    {
        return $this->total_dispensed >= $this->quantity;
    }

    // Scopes
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

    public function scopeActive($query)
    {
        return $query->whereHas('status', function ($q) {
            $q->where('name', 'active');
        });
    }

    public function scopePending($query)
    {
        return $query->whereRaw('quantity > (SELECT COALESCE(SUM(quantity), 0) FROM medication_dispenses WHERE medication_request_id = medication_requests.id)');
    }
}