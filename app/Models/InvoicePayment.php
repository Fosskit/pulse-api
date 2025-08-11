<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class InvoicePayment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'ulid',
        'invoice_id',
        'amount',
        'payment_method_id',
        'payment_date',
        'reference_number',
        'notes',
        'processed_by',
        'original_payment_id',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'datetime',
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

    public function paymentMethod(): BelongsTo
    {
        return $this->belongsTo(Term::class, 'payment_method_id');
    }

    public function processedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'processed_by');
    }

    public function originalPayment(): BelongsTo
    {
        return $this->belongsTo(InvoicePayment::class, 'original_payment_id');
    }

    public function refunds()
    {
        return $this->hasMany(InvoicePayment::class, 'original_payment_id');
    }

    // Accessors
    public function getIsRefundAttribute(): bool
    {
        return $this->amount < 0;
    }

    public function getAbsoluteAmountAttribute(): float
    {
        return abs($this->amount);
    }

    public function getPaymentTypeAttribute(): string
    {
        return $this->amount > 0 ? 'payment' : 'refund';
    }
}