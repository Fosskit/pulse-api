<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Service extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ward_id',
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
     * Get the department (ward) that owns the service.
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class, 'ward_id');
    }

    /**
     * Alias for department relationship for clarity.
     */
    public function ward(): BelongsTo
    {
        return $this->department();
    }

    /**
     * Get all invoice items for this service.
     */
    public function invoiceItems()
    {
        return $this->morphMany(InvoiceItem::class, 'invoiceable');
    }
}