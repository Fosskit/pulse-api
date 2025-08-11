<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicationInstruction extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'method_id',
        'unit_id',
        'morning',
        'afternoon',
        'evening',
        'night',
        'days',
        'quantity',
        'note',
    ];

    protected $casts = [
        'morning' => 'decimal:2',
        'afternoon' => 'decimal:2',
        'evening' => 'decimal:2',
        'night' => 'decimal:2',
        'quantity' => 'decimal:2',
        'days' => 'integer',
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
    public function method(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'method_id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'unit_id');
    }

    public function medicationRequests(): HasMany
    {
        return $this->hasMany(MedicationRequest::class, 'instruction_id');
    }

    // Accessors
    public function getTotalDailyDoseAttribute(): float
    {
        return (float) ($this->morning + $this->afternoon + $this->evening + $this->night);
    }

    public function getTotalQuantityNeededAttribute(): float
    {
        return $this->total_daily_dose * $this->days;
    }

    // Methods
    public function getDosageSchedule(): array
    {
        return [
            'morning' => (float) $this->morning,
            'afternoon' => (float) $this->afternoon,
            'evening' => (float) $this->evening,
            'night' => (float) $this->night,
        ];
    }

    public function hasActiveDoses(): bool
    {
        return $this->total_daily_dose > 0;
    }
}