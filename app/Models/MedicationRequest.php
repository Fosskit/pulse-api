<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class MedicationRequest extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'visit_id',
        'status_id',
        'intent_id',
    ];

    // Relationships
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'status_id');
    }

    public function intent(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'intent_id');
    }
}