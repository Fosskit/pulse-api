<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientIdentity extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'patient_id',
        'card_id',
        'start_date',
        'end_date',
        'detail',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'detail' => 'array',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function card(): BelongsTo
    {
        return $this->belongsTo(Card::class);
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        $now = now()->toDateString();
        return $this->start_date <= $now && 
               (is_null($this->end_date) || $this->end_date >= $now);
    }

    public function getIsExpiredAttribute(): bool
    {
        return !is_null($this->end_date) && $this->end_date < now()->toDateString();
    }

    // Scopes
    public function scopeActive($query)
    {
        $now = now()->toDateString();
        return $query->where('start_date', '<=', $now)
                    ->where(function ($q) use ($now) {
                        $q->whereNull('end_date')
                          ->orWhere('end_date', '>=', $now);
                    });
    }

    public function scopeExpired($query)
    {
        return $query->whereNotNull('end_date')
                    ->where('end_date', '<', now()->toDateString());
    }
}
