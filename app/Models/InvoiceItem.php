<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoiceItem extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'invoice_id',
        'invoiceable_id',
        'invoiceable_type',
        'quantity',
        'price',
        'paid',
        'discount_type_id',
        'payment_type_id',
        'discount',
    ];

    protected $casts = [
        'quantity' => 'integer',
        'price' => 'decimal:2',
        'paid' => 'decimal:2',
        'discount' => 'decimal:2',
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
    public function invoice(): BelongsTo
    {
        return $this->belongsTo(Invoice::class);
    }

    public function invoiceable()
    {
        return $this->morphTo();
    }

    public function discountType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'discount_type_id');
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'payment_type_id');
    }

    // Calculate line total
    public function getLineTotalAttribute(): float
    {
        return $this->quantity * $this->price;
    }

    // Calculate line total after discount
    public function getLineTotalAfterDiscountAttribute(): float
    {
        return $this->line_total - $this->discount;
    }
}