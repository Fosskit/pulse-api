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
        'visit_id',
        'encounter_type_id',
        'encounter_form_id',
        'started_at',
        'ended_at',
    ];

    protected $casts = [
        'started_at' => 'datetime',
        'ended_at' => 'datetime',
    ];

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
}