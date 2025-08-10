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
}