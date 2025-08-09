<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class PatientAddress extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'patient_id',
        'address_type_id',
        'gazetteer_id',
        'street_address',
        'is_current',
    ];

    protected $casts = [
        'is_current' => 'boolean',
    ];

    public function patient(): BelongsTo
    {
        return $this->belongsTo(Patient::class);
    }

    public function addressType(): BelongsTo
    {
        return $this->belongsTo(TaxonomyValue::class, 'address_type_id');
    }
}
