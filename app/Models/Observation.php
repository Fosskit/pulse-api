<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Observation extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'parent_id',
        'patient_id',
        'encounter_id',
        'code',
        'observation_status_id',
        'concept_id',
        'body_site_id',
        'value_id',
        'value_string',
        'value_number',
        'value_text',
        'value_complex',
        'value_datetime',
        'observed_at',
        'observed_by',
    ];

    protected $casts = [
        'value_complex' => 'array',
        'value_datetime' => 'datetime',
        'observed_at' => 'datetime',
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

    public function observationConcept(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'concept_id');
    }

    public function observationStatus(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'observation_status_id');
    }

    public function bodySite(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'body_site_id');
    }

    public function parent(): BelongsTo
    {
        return $this->belongsTo(Observation::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Observation::class, 'parent_id');
    }

    // Scopes
    public function scopeByPatient($query, int $patientId)
    {
        return $query->where('patient_id', $patientId);
    }

    public function scopeByEncounter($query, int $encounterId)
    {
        return $query->where('encounter_id', $encounterId);
    }

    public function scopeByCode($query, string $code)
    {
        return $query->where('code', $code);
    }

    public function scopeVitalSigns($query)
    {
        return $query->whereHas('observationConcept', function ($q) {
            $q->whereHas('terminology', function ($terminology) {
                $terminology->where('name', 'vital_signs');
            });
        });
    }

    public function scopeChronological($query)
    {
        return $query->orderBy('observed_at');
    }

    // Accessors
    public function getFormattedValueAttribute(): string
    {
        if (!is_null($this->value_string)) {
            return $this->value_string;
        }
        
        if (!is_null($this->value_number)) {
            return (string) $this->value_number;
        }
        
        if (!is_null($this->value_text)) {
            return $this->value_text;
        }
        
        if (!is_null($this->value_datetime)) {
            return $this->value_datetime->format('Y-m-d H:i:s');
        }
        
        if (!is_null($this->value_complex)) {
            return json_encode($this->value_complex);
        }
        
        return '';
    }

    public function getValueTypeAttribute(): string
    {
        if (!is_null($this->value_string)) return 'string';
        if (!is_null($this->value_number)) return 'number';
        if (!is_null($this->value_text)) return 'text';
        if (!is_null($this->value_datetime)) return 'datetime';
        if (!is_null($this->value_complex)) return 'complex';
        
        return 'unknown';
    }
}
