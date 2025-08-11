<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class RecordPaymentAction
{
    /**
     * Record a payment transaction for an invoice.
     *
     * @param Invoice $invoice
     * @param array $paymentData Payment transaction details
     * @return InvoicePayment
     */
    public function execute(Invoice $invoice, array $paymentData): InvoicePayment
    {
        return DB::transaction(function () use ($invoice, $paymentData) {
            // Validate payment amount
            $this->validatePaymentAmount($invoice, $paymentData['amount']);
            
            // Create payment record
            $payment = $this->createPaymentRecord($invoice, $paymentData);
            
            // Update invoice received amount
            $this->updateInvoiceReceivedAmount($invoice, $paymentData['amount']);
            
            // Update invoice items if partial payment allocation is specified
            if (isset($paymentData['item_allocations'])) {
                $this->allocatePaymentToItems($invoice, $paymentData['item_allocations']);
            }
            
            return $payment->fresh(['invoice', 'paymentMethod']);
        });
    }

    /**
     * Validate payment amount against invoice balance.
     */
    private function validatePaymentAmount(Invoice $invoice, float $amount): void
    {
        if ($amount <= 0) {
            throw new \InvalidArgumentException('Payment amount must be greater than zero');
        }

        $remainingBalance = $invoice->remaining_balance;
        
        if ($amount > $remainingBalance) {
            throw new \InvalidArgumentException(
                "Payment amount ({$amount}) cannot exceed remaining balance ({$remainingBalance})"
            );
        }
    }

    /**
     * Create payment record.
     */
    private function createPaymentRecord(Invoice $invoice, array $paymentData): InvoicePayment
    {
        // Ensure invoice_payments table exists or create the model
        return InvoicePayment::create([
            'ulid' => \Illuminate\Support\Str::ulid(),
            'invoice_id' => $invoice->id,
            'amount' => $paymentData['amount'],
            'payment_method_id' => $paymentData['payment_method_id'],
            'payment_date' => $paymentData['payment_date'] ?? now(),
            'reference_number' => $paymentData['reference_number'] ?? null,
            'notes' => $paymentData['notes'] ?? null,
            'processed_by' => $paymentData['processed_by'] ?? auth()->id(),
        ]);
    }

    /**
     * Update invoice received amount.
     */
    private function updateInvoiceReceivedAmount(Invoice $invoice, float $paymentAmount): void
    {
        $newReceivedAmount = $invoice->received + $paymentAmount;
        
        $invoice->update([
            'received' => $newReceivedAmount,
        ]);
    }

    /**
     * Allocate payment to specific invoice items.
     */
    private function allocatePaymentToItems(Invoice $invoice, array $itemAllocations): void
    {
        foreach ($itemAllocations as $allocation) {
            $item = $invoice->invoiceItems()->find($allocation['item_id']);
            
            if ($item) {
                $newPaidAmount = $item->paid + $allocation['amount'];
                $maxPayable = $item->line_total_after_discount;
                
                // Ensure we don't overpay an item
                $newPaidAmount = min($newPaidAmount, $maxPayable);
                
                $item->update([
                    'paid' => $newPaidAmount,
                ]);
            }
        }
    }

    /**
     * Process refund for an invoice payment.
     */
    public function processRefund(InvoicePayment $payment, array $refundData): InvoicePayment
    {
        return DB::transaction(function () use ($payment, $refundData) {
            $refundAmount = $refundData['amount'];
            
            // Validate refund amount
            if ($refundAmount <= 0 || $refundAmount > $payment->amount) {
                throw new \InvalidArgumentException('Invalid refund amount');
            }
            
            // Create refund payment record (negative amount)
            $refund = InvoicePayment::create([
                'ulid' => \Illuminate\Support\Str::ulid(),
                'invoice_id' => $payment->invoice_id,
                'amount' => -$refundAmount,
                'payment_method_id' => $refundData['payment_method_id'] ?? $payment->payment_method_id,
                'payment_date' => $refundData['refund_date'] ?? now(),
                'reference_number' => $refundData['reference_number'] ?? null,
                'notes' => $refundData['notes'] ?? 'Refund for payment #' . $payment->id,
                'processed_by' => $refundData['processed_by'] ?? auth()->id(),
                'original_payment_id' => $payment->id,
            ]);
            
            // Update invoice received amount
            $invoice = $payment->invoice;
            $invoice->update([
                'received' => $invoice->received - $refundAmount,
            ]);
            
            return $refund->fresh(['invoice', 'paymentMethod']);
        });
    }

    /**
     * Get payment summary for an invoice.
     */
    public function getPaymentSummary(Invoice $invoice): array
    {
        $payments = $invoice->payments()->orderBy('payment_date')->get();
        
        $totalPaid = $payments->where('amount', '>', 0)->sum('amount');
        $totalRefunded = abs($payments->where('amount', '<', 0)->sum('amount'));
        $netPaid = $totalPaid - $totalRefunded;
        
        return [
            'invoice_id' => $invoice->id,
            'invoice_total' => $invoice->calculateFinalAmount(),
            'total_paid' => $totalPaid,
            'total_refunded' => $totalRefunded,
            'net_paid' => $netPaid,
            'remaining_balance' => $invoice->remaining_balance,
            'is_fully_paid' => $invoice->is_fully_paid,
            'payment_status' => $this->getPaymentStatus($invoice),
            'payments' => $payments->map(function ($payment) {
                return [
                    'id' => $payment->id,
                    'amount' => $payment->amount,
                    'payment_date' => $payment->payment_date,
                    'payment_method' => $payment->paymentMethod?->name,
                    'reference_number' => $payment->reference_number,
                    'notes' => $payment->notes,
                    'type' => $payment->amount > 0 ? 'payment' : 'refund',
                ];
            }),
        ];
    }

    /**
     * Get payment status for an invoice.
     */
    private function getPaymentStatus(Invoice $invoice): string
    {
        if ($invoice->received <= 0) {
            return 'unpaid';
        }
        
        if ($invoice->is_fully_paid) {
            return 'paid';
        }
        
        return 'partial';
    }

    /**
     * Get billing history with totals and balances for a patient.
     */
    public function getBillingHistory(int $patientId, array $options = []): array
    {
        $query = Invoice::whereHas('visit', function ($q) use ($patientId) {
            $q->where('patient_id', $patientId);
        })->with(['visit', 'invoiceItems', 'payments']);

        // Apply date filters if provided
        if (isset($options['from_date'])) {
            $query->where('date', '>=', $options['from_date']);
        }
        
        if (isset($options['to_date'])) {
            $query->where('date', '<=', $options['to_date']);
        }

        // Apply payment status filter if provided
        if (isset($options['payment_status'])) {
            switch ($options['payment_status']) {
                case 'paid':
                    $query->whereRaw('received >= (total - discount)');
                    break;
                case 'unpaid':
                    $query->where('received', 0);
                    break;
                case 'partial':
                    $query->whereRaw('received > 0 AND received < (total - discount)');
                    break;
            }
        }

        $invoices = $query->orderBy('date', 'desc')->get();
        
        // Calculate totals
        $totalBilled = $invoices->sum(function ($invoice) {
            return $invoice->calculateFinalAmount();
        });
        
        $totalPaid = $invoices->sum('received');
        $totalOutstanding = $totalBilled - $totalPaid;
        
        return [
            'patient_id' => $patientId,
            'summary' => [
                'total_invoices' => $invoices->count(),
                'total_billed' => $totalBilled,
                'total_paid' => $totalPaid,
                'total_outstanding' => $totalOutstanding,
                'payment_rate' => $totalBilled > 0 ? ($totalPaid / $totalBilled) * 100 : 0,
            ],
            'invoices' => $invoices->map(function ($invoice) {
                return [
                    'id' => $invoice->id,
                    'code' => $invoice->code,
                    'date' => $invoice->date,
                    'visit_id' => $invoice->visit_id,
                    'total_amount' => $invoice->calculateFinalAmount(),
                    'amount_paid' => $invoice->received,
                    'balance_due' => $invoice->remaining_balance,
                    'payment_status' => $this->getPaymentStatus($invoice),
                    'services_count' => $invoice->invoiceItems->count(),
                ];
            }),
        ];
    }
}