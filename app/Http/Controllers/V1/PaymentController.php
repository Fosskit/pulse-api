<?php

namespace App\Http\Controllers\V1;

use App\Actions\RecordPaymentAction;
use App\Actions\CalculateDiscountsAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\RecordPaymentRequest;
use App\Http\Requests\V1\ProcessRefundRequest;
use App\Http\Resources\V1\InvoicePaymentResource;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private RecordPaymentAction $recordPaymentAction,
        private CalculateDiscountsAction $calculateDiscountsAction
    ) {}

    /**
     * Record a payment for an invoice.
     */
    public function recordPayment(RecordPaymentRequest $request, Invoice $invoice): JsonResponse
    {
        $payment = $this->recordPaymentAction->execute($invoice, $request->validated());

        return response()->json([
            'message' => 'Payment recorded successfully',
            'data' => new InvoicePaymentResource($payment)
        ], 201);
    }

    /**
     * Process a refund for a payment.
     */
    public function processRefund(ProcessRefundRequest $request, InvoicePayment $payment): JsonResponse
    {
        $refund = $this->recordPaymentAction->processRefund($payment, $request->validated());

        return response()->json([
            'message' => 'Refund processed successfully',
            'data' => new InvoicePaymentResource($refund)
        ], 201);
    }

    /**
     * Get payment summary for an invoice.
     */
    public function getPaymentSummary(Invoice $invoice): JsonResponse
    {
        $summary = $this->recordPaymentAction->getPaymentSummary($invoice);

        return response()->json([
            'data' => $summary
        ]);
    }

    /**
     * Calculate insurance discounts for an invoice.
     */
    public function calculateDiscounts(Request $request, Invoice $invoice): JsonResponse
    {
        $options = $request->only(['additional_discount']);
        $discountCalculation = $this->calculateDiscountsAction->execute($invoice, $options);

        return response()->json([
            'data' => $discountCalculation
        ]);
    }

    /**
     * Generate insurance claim for an invoice.
     */
    public function generateInsuranceClaim(Invoice $invoice): JsonResponse
    {
        try {
            $claimData = $this->calculateDiscountsAction->generateInsuranceClaim($invoice);

            return response()->json([
                'message' => 'Insurance claim generated successfully',
                'data' => $claimData
            ]);
        } catch (\InvalidArgumentException $e) {
            return response()->json([
                'error' => [
                    'code' => 'NO_INSURANCE_COVERAGE',
                    'message' => $e->getMessage()
                ]
            ], 400);
        }
    }

    /**
     * Get billing history for a patient.
     */
    public function getBillingHistory(Request $request, int $patientId): JsonResponse
    {
        $options = $request->only(['from_date', 'to_date', 'payment_status']);
        $billingHistory = $this->recordPaymentAction->getBillingHistory($patientId, $options);

        return response()->json([
            'data' => $billingHistory
        ]);
    }

    /**
     * Get all payments for an invoice.
     */
    public function getInvoicePayments(Invoice $invoice): JsonResponse
    {
        $payments = $invoice->payments()
            ->with(['paymentMethod', 'processedBy'])
            ->orderBy('payment_date', 'desc')
            ->get();

        return response()->json([
            'data' => InvoicePaymentResource::collection($payments)
        ]);
    }
}