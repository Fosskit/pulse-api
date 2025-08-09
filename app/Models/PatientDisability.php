<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientDisability extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'disability_id',
        'start_date',
    ];

    protected $casts = [
        'start_date' => 'date',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function disability(): BelongsTo
    {
        return $this->belongsTo(TaxonomyValue::class, 'disability_id');
    }
}
