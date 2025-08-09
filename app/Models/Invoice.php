<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Invoice extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'visit_id',
        'payment_type_id',
        'percentage_discount',
        'amount_discount',
        'received',
    ];

    protected $casts = [
        'percentage_discount' => 'decimal:2',
        'amount_discount' => 'decimal:2',
        'received' => 'decimal:2',
    ];

    // Relationships
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'payment_type_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }
}