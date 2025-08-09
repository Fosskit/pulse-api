<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Patient extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
        'surname',
        'name',
        'sex',
        'birthdate',
        'phone',
        'nationality_id',
        'occupation_id',
        'marital_status_id',
        'facility_id',
        'death_at',
    ];

    protected $casts = [
        'birthdate' => 'date',
        'death_at' => 'datetime',
    ];

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function nationality(): BelongsTo
    {
        return $this->belongsTo(TaxonomyValue::class, 'nationality_id');
    }

    public function occupation(): BelongsTo
    {
        return $this->belongsTo(TaxonomyValue::class, 'occupation_id');
    }

    public function maritalStatus(): BelongsTo
    {
        return $this->belongsTo(TaxonomyValue::class, 'marital_status_id');
    }

    public function identities(): HasMany
    {
        return $this->hasMany(PatientIdentity::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PatientAddress::class);
    }

    public function currentAddress(): HasMany
    {
        return $this->hasMany(PatientAddress::class)
            ->where('is_current', true);
    }

    public function disabilities(): HasMany
    {
        return $this->hasMany(PatientDisability::class);
    }

    // Accessors
    public function getFullNameAttribute(): string
    {
        return trim($this->surname . ' ' . $this->name);
    }

    public function getAgeAttribute(): ?int
    {
        return $this->birthdate ? $this->birthdate->age : null;
    }

    public function getIsDeceasedAttribute(): bool
    {
        return !is_null($this->death_at);
    }
}
