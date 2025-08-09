<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientDemographic extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'patient_id',
        'name',
        'birthdate',
        'telecom',
        'address',
        'sex',
        'nationality_id',
        'telephone',
        'died_at',
    ];

    protected $casts = [
        'name' => 'array',
        'telecom' => 'array',
        'address' => 'array',
        'birthdate' => 'date',
        'died_at' => 'datetime',
    ];

    // Relationships
    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function nationality(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'nationality_id');
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        if (is_array($this->name)) {
            $given = $this->name['given'] ?? [];
            $family = $this->name['family'] ?? '';
            
            if (is_array($given)) {
                $given = implode(' ', $given);
            }
            
            return trim($family . ' ' . $given);
        }
        
        return $this->name ?? '';
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birthdate ? $this->birthdate->age : null;
    }

    public function getIsDeceasedAttribute(): bool
    {
        return !is_null($this->died_at);
    }

    // Helper methods for name handling
    public function getGivenNameAttribute(): string
    {
        if (is_array($this->name) && isset($this->name['given'])) {
            return is_array($this->name['given']) 
                ? implode(' ', $this->name['given'])
                : $this->name['given'];
        }
        return '';
    }

    public function getFamilyNameAttribute(): string
    {
        if (is_array($this->name) && isset($this->name['family'])) {
            return $this->name['family'];
        }
        return '';
    }
}
