<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class ImagingRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'service_request_id',
        'modality_concept_id',
        'body_site_concept_id',
        'reason_for_study',
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
    public function serviceRequest(): BelongsTo
    {
        return $this->belongsTo(ServiceRequest::class);
    }

    public function modalityConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'modality_concept_id');
    }

    public function bodySiteConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'body_site_concept_id');
    }
}