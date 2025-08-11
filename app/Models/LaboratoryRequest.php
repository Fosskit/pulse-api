<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class LaboratoryRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'service_request_id',
        'test_concept_id',
        'specimen_type_concept_id',
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

    public function testConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'test_concept_id');
    }

    public function specimenTypeConcept(): BelongsTo
    {
        return $this->belongsTo(Concept::class, 'specimen_type_concept_id');
    }
}