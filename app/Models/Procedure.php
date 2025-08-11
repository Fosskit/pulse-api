<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Procedure extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'patient_id',
        'encounter_id',
        'procedure_concept_id',
        'outcome_id',
        'body_site_id',
        'performed_at',
        'performed_by',
    ];

    protected $casts = [
        'performed_at' => 'datetime',
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
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function procedureConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'procedure_concept_id');
    }

    public function outcome(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'outcome_id');
    }

    public function bodySite(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'body_site_id');
    }
}