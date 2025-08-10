<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Encounter extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'visit_id',
        'encounter_type_id',
        'encounter_form_id',
        'is_new',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
        'is_new' => 'boolean',
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

    public function encounterType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'encounter_type_id');
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    public function clinicalFormTemplate(): BelongsTo
    {
        return $this->belongsTo(ClinicalFormTemplate::class, 'encounter_form_id');
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->whereNull('ended_at');
    }

    public function scopeByType($query, int $encounterTypeId)
    {
        return $query->where('encounter_type_id', $encounterTypeId);
    }

    public function scopeChronological($query)
    {
        return $query->orderBy('started_at');
    }

    public function scopeForVisit($query, int $visitId)
    {
        return $query->where('visit_id', $visitId);
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return is_null($this->ended_at);
    }

    public function getDurationMinutesAttribute(): ?int
    {
        if (!$this->started_at) {
            return null;
        }

        $endTime = $this->ended_at ?? now();
        return $this->started_at->diffInMinutes($endTime);
    }

    public function getStatusAttribute(): string
    {
        return $this->is_active ? 'active' : 'completed';
    }
}