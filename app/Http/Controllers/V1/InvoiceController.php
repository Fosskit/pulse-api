<?php

namespace App\Http\Controllers\V1;

use App\Actions\GenerateInvoiceAction;
use App\Http\Controllers\Controller;
use App\Http\Requests\V1\GenerateInvoiceRequest;
use App\Http\Resources\V1\InvoiceResource;
use App\Models\Invoice;
use App\Models\Visit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class InvoiceController extends Controller
{
    public function __construct(
        private GenerateInvoiceAction $generateInvoiceAction
    ) {}

    /**
     * Generate an invoice for a visit.
     */
    public function generateForVisit(GenerateInvoiceRequest $request, Visit $visit): JsonResponse
    {
        $invoice = $this->generateInvoiceAction->execute($visit, $request->validated());

        return response()->json([
            'message' => 'Invoice generated successfully',
            'data' => new InvoiceResource($invoice)
        ], 201);
    }

    /**
     * Display the specified invoice.
     */
    public function show(Invoice $invoice): JsonResponse
    {
        $invoice->load(['invoiceItems.invoiceable', 'visit.patient', 'invoiceCategory', 'paymentType']);

        return response()->json([
            'data' => new InvoiceResource($invoice)
        ]);
    }

    /**
     * Get invoices for a specific visit.
     */
    public function getVisitInvoices(Visit $visit): JsonResponse
    {
        $invoices = $visit->invoices()
            ->with(['invoiceItems.invoiceable', 'invoiceCategory', 'paymentType'])
            ->orderBy('date', 'desc')
            ->get();

        return response()->json([
            'data' => InvoiceResource::collection($invoices)
        ]);
    }

    /**
     * Get billing history for a patient.
     */
    public function getPatientBillingHistory(Request $request, int $patientId): JsonResponse
    {
        $invoices = Invoice::whereHas('visit', function ($query) use ($patientId) {
                $query->where('patient_id', $patientId);
            })
            ->with(['invoiceItems.invoiceable', 'visit', 'invoiceCategory', 'paymentType'])
            ->orderBy('date', 'desc')
            ->paginate($request->get('per_page', 15));

        return response()->json([
            'data' => InvoiceResource::collection($invoices->items()),
            'meta' => [
                'current_page' => $invoices->currentPage(),
                'last_page' => $invoices->lastPage(),
                'per_page' => $invoices->perPage(),
                'total' => $invoices->total(),
            ]
        ]);
    }
}