<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Card extends Model
{
    use HasFactory;

    public $timestamps = false;

    protected $fillable = [
        'ulid',
        'code',
        'card_type_id',
        'issue_date',
        'expiry_date',
    ];

    protected $casts = [
        'issue_date' => 'date',
        'expiry_date' => 'date',
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
    public function cardType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'card_type_id');
    }

    public function patientIdentities(): HasMany
    {
        return $this->hasMany(PatientIdentity::class);
    }

    // Accessors
    public function getIsActiveAttribute(): bool
    {
        return $this->expiry_date >= now()->toDateString();
    }

    public function getIsExpiredAttribute(): bool
    {
        return $this->expiry_date < now()->toDateString();
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('expiry_date', '>=', now()->toDateString());
    }

    public function scopeExpired($query)
    {
        return $query->where('expiry_date', '<', now()->toDateString());
    }
}