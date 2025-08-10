<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Facility extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'code',
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
     * Get the departments for the facility.
     */
    public function departments(): HasMany
    {
        return $this->hasMany(Department::class);
    }

    /**
     * Get all rooms through departments.
     */
    public function rooms()
    {
        return $this->hasManyThrough(Room::class, Department::class);
    }

    /**
     * Get the patients for the facility.
     */
    public function patients(): HasMany
    {
        return $this->hasMany(Patient::class);
    }

    /**
     * Get the visits for the facility.
     */
    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }
}
