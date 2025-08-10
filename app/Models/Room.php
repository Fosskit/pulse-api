<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Room extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'department_id',
        'room_type_id',
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
     * Get the department that owns the room.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Get the room type.
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'room_type_id');
    }

    /**
     * Get the facility through the department.
     */
    public function facility(): BelongsTo
    {
        return $this->department()->facility();
    }

    /**
     * Scope a query to only include rooms for a specific department.
     */
    public function scopeForDepartment($query, $departmentId)
    {
        return $query->where('department_id', $departmentId);
    }

    /**
     * Scope a query to only include available rooms.
     * This is a basic implementation - more complex availability logic can be added later.
     */
    public function scopeAvailable($query)
    {
        return $query->whereNull('deleted_at');
    }
}