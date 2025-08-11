<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicationDispense extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'visit_id',
        'status_id',
        'medication_request_id',
        'dispenser_id',
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

    public function medicationRequest(): BelongsTo
    {
        return $this->belongsTo(MedicationRequest::class);
    }

    public function dispenser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'dispenser_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'unit_id');
    }

    // Accessors
    public function getIsDispensedAttribute(): bool
    {
        return $this->quantity > 0;
    }

    // Scopes
    public function scopeDispensed($query)
    {
        return $query->where('quantity', '>', 0);
    }

    public function scopeForVisit($query, $visitId)
    {
        return $query->where('visit_id', $visitId);
    }
}