<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ServiceRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'visit_id',
        'encounter_id',
        'service_id',
        'request_type',
        'status_id',
        'ordered_at',
        'completed_at',
        'scheduled_at',
        'scheduled_for',
    ];

    protected $casts = [
        'ordered_at' => 'datetime',
        'completed_at' => 'datetime',
        'scheduled_at' => 'datetime',
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

    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class);
    }

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'status_id');
    }

    public function laboratoryRequest(): HasOne
    {
        return $this->hasOne(LaboratoryRequest::class);
    }

    public function imagingRequest(): HasOne
    {
        return $this->hasOne(ImagingRequest::class);
    }

    public function observations(): HasMany
    {
        return $this->hasMany(Observation::class);
    }

    // Scopes
    public function scopePending($query)
    {
        return $query->whereNull('completed_at');
    }

    public function scopeCompleted($query)
    {
        return $query->whereNotNull('completed_at');
    }

    public function scopeByType($query, string $type)
    {
        return $query->where('request_type', $type);
    }

    // Helper methods
    public function isCompleted(): bool
    {
        return !is_null($this->completed_at);
    }

    public function isPending(): bool
    {
        return is_null($this->completed_at);
    }
}