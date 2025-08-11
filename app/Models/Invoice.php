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
        'ulid',
        'code',
        'visit_id',
        'invoice_category_id',
        'payment_type_id',
        'date',
        'total',
        'percentage_discount',
        'amount_discount',
        'discount',
        'received',
        'remark',
    ];

    protected $casts = [
        'date' => 'datetime',
        'total' => 'decimal:2',
        'percentage_discount' => 'decimal:2',
        'amount_discount' => 'decimal:2',
        'discount' => 'decimal:2',
        'received' => 'decimal:2',
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
    public function visit(): BelongsTo
    {
        return $this->belongsTo(Visit::class);
    }

    public function invoiceCategory(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'invoice_category_id');
    }

    public function paymentType(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'payment_type_id');
    }

    public function invoiceItems(): HasMany
    {
        return $this->hasMany(InvoiceItem::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(InvoicePayment::class);
    }

    // Calculate total from invoice items
    public function calculateTotal(): float
    {
        return $this->invoiceItems->sum(function ($item) {
            return $item->quantity * $item->price;
        });
    }

    // Calculate discount amount
    public function calculateDiscount(): float
    {
        $subtotal = $this->calculateTotal();
        $percentageDiscount = ($subtotal * $this->percentage_discount) / 100;
        return $percentageDiscount + $this->amount_discount;
    }

    // Calculate final amount after discount
    public function calculateFinalAmount(): float
    {
        return $this->calculateTotal() - $this->calculateDiscount();
    }

    // Calculate remaining balance
    public function getRemainingBalanceAttribute(): float
    {
        return $this->calculateFinalAmount() - $this->received;
    }

    // Check if invoice is fully paid
    public function getIsFullyPaidAttribute(): bool
    {
        return $this->remaining_balance <= 0;
    }
}