<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Department extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'facility_id',
        'code',
        'name',
        'created_by',
        'updated_by',
        'deleted_by',
    ];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    /**
     * Get the facility that owns the department.
     */
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    /**
     * Get the rooms for the department.
     */
    public function rooms(): HasMany
    {
        return $this->hasMany(Room::class);
    }

    /**
     * Get the services for the department.
     */
    public function services(): HasMany
    {
        return $this->hasMany(Service::class, 'ward_id');
    }

    /**
     * Scope a query to only include departments for a specific facility.
     */
    public function scopeForFacility($query, $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }
}