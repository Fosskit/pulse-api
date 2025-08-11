<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Visit;
use App\Models\Service;
use App\Models\MedicationRequest;
use App\Models\Term;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateInvoiceAction
{
    /**
     * Generate an invoice for a visit based on services provided and patient insurance status.
     *
     * @param Visit $visit
     * @param array $options Additional options for invoice generation
     * @return Invoice
     */
    public function execute(Visit $visit, array $options = []): Invoice
    {
        return DB::transaction(function () use ($visit, $options) {
            // Determine payment type based on patient insurance status
            $paymentTypeId = $this->determinePaymentTypeId($visit);
            
            // Get invoice category (default to general if not specified)
            $invoiceCategoryId = $options['invoice_category_id'] ?? $this->getDefaultInvoiceCategoryId();
            
            // Create the invoice
            $invoice = Invoice::create([
                'visit_id' => $visit->id,
                'invoice_category_id' => $invoiceCategoryId,
                'payment_type_id' => $paymentTypeId,
                'date' => $options['date'] ?? now(),
                'total' => 0, // Will be calculated after adding items
                'percentage_discount' => $options['percentage_discount'] ?? 0,
                'amount_discount' => $options['amount_discount'] ?? 0,
                'discount' => 0, // Will be calculated
                'received' => 0,
                'remark' => $options['remark'] ?? null,
            ]);

            // Generate invoice code if not provided
            if (empty($invoice->code)) {
                $invoice->code = $this->generateInvoiceCode($invoice);
                $invoice->save();
            }

            // Add service items to invoice
            $this->addServiceItemsToInvoice($invoice, $visit, $options);
            
            // Add medication items to invoice
            $this->addMedicationItemsToInvoice($invoice, $visit, $options);

            // Calculate totals and discounts
            $this->calculateInvoiceTotals($invoice);

            return $invoice->fresh(['invoiceItems', 'visit.patient']);
        });
    }

    /**
     * Determine payment type ID based on patient insurance status.
     */
    private function determinePaymentTypeId(Visit $visit): int
    {
        $patient = $visit->patient;
        
        // Use patient's method to determine payment type
        $paymentTypeId = $patient->getPaymentTypeId();
        
        if ($paymentTypeId) {
            return $paymentTypeId;
        }

        // Fallback to default payment type (self-pay)
        return $this->getDefaultPaymentTypeId();
    }

    /**
     * Get default invoice category ID.
     */
    private function getDefaultInvoiceCategoryId(): int
    {
        $category = Term::where('name', 'General Invoice')
            ->where('category', 'invoice_category')
            ->first();
            
        if (!$category) {
            // Create default category if it doesn't exist
            $category = Term::create([
                'name' => 'General Invoice',
                'category' => 'invoice_category',
                'description' => 'General medical services invoice',
            ]);
        }
        
        return $category->id;
    }

    /**
     * Get default payment type ID (self-pay).
     */
    private function getDefaultPaymentTypeId(): int
    {
        $paymentType = Term::where('name', 'Self Pay')
            ->where('category', 'payment_type')
            ->first();
            
        if (!$paymentType) {
            // Create default payment type if it doesn't exist
            $paymentType = Term::create([
                'name' => 'Self Pay',
                'category' => 'payment_type',
                'description' => 'Patient pays out of pocket',
            ]);
        }
        
        return $paymentType->id;
    }

    /**
     * Generate invoice code.
     */
    private function generateInvoiceCode(Invoice $invoice): string
    {
        $date = $invoice->date ?? now();
        $prefix = 'INV-' . $date->format('Ymd');
        $sequence = str_pad($invoice->id, 4, '0', STR_PAD_LEFT);
        
        return $prefix . '-' . $sequence;
    }

    /**
     * Add service items to invoice based on visit encounters and service requests.
     */
    private function addServiceItemsToInvoice(Invoice $invoice, Visit $visit, array $options): void
    {
        // Get services from encounters (consultation fees, procedures, etc.)
        $encounterServices = $this->getEncounterServices($visit);
        
        // Get services from service requests (lab tests, imaging, procedures)
        $serviceRequestServices = $this->getServiceRequestServices($visit);
        
        // Combine all services
        $allServices = $encounterServices->merge($serviceRequestServices);
        
        foreach ($allServices as $serviceData) {
            $this->createInvoiceItem($invoice, $serviceData);
        }
    }

    /**
     * Get services from visit encounters.
     */
    private function getEncounterServices(Visit $visit)
    {
        $services = collect();
        
        foreach ($visit->encounters as $encounter) {
            // Add consultation fee based on encounter type
            $consultationService = $this->getConsultationServiceForEncounter($encounter);
            if ($consultationService) {
                $services->push([
                    'service' => $consultationService,
                    'quantity' => 1,
                    'price' => $this->getServicePrice($consultationService),
                ]);
            }
        }
        
        return $services;
    }

    /**
     * Get services from service requests.
     */
    private function getServiceRequestServices(Visit $visit)
    {
        $services = collect();
        
        foreach ($visit->serviceRequests as $serviceRequest) {
            $service = $this->getServiceForServiceRequest($serviceRequest);
            if ($service) {
                $services->push([
                    'service' => $service,
                    'quantity' => 1,
                    'price' => $this->getServicePrice($service),
                ]);
            }
        }
        
        return $services;
    }

    /**
     * Get consultation service for encounter type.
     */
    private function getConsultationServiceForEncounter($encounter): ?Service
    {
        // This would typically map encounter types to consultation services
        // For now, return a default consultation service
        return Service::where('name', 'LIKE', '%consultation%')
            ->orWhere('name', 'LIKE', '%visit%')
            ->first();
    }

    /**
     * Get service for service request.
     */
    private function getServiceForServiceRequest($serviceRequest): ?Service
    {
        // Map service request to actual billable service
        // This could be based on the service request type and specific request
        if ($serviceRequest->laboratory_request) {
            return Service::where('name', 'LIKE', '%laboratory%')
                ->orWhere('name', 'LIKE', '%lab%')
                ->first();
        }
        
        if ($serviceRequest->imaging_request) {
            return Service::where('name', 'LIKE', '%imaging%')
                ->orWhere('name', 'LIKE', '%radiology%')
                ->first();
        }
        
        if ($serviceRequest->procedure) {
            return Service::where('name', 'LIKE', '%procedure%')
                ->first();
        }
        
        return null;
    }

    /**
     * Get service price (this would typically come from a pricing table).
     */
    private function getServicePrice(Service $service): float
    {
        // In a real system, this would come from a pricing table
        // For now, return a default price based on service type
        $serviceName = strtolower($service->name);
        
        if (str_contains($serviceName, 'consultation') || str_contains($serviceName, 'visit')) {
            return 50.00; // Default consultation fee
        }
        
        if (str_contains($serviceName, 'laboratory') || str_contains($serviceName, 'lab')) {
            return 25.00; // Default lab test fee
        }
        
        if (str_contains($serviceName, 'imaging') || str_contains($serviceName, 'radiology')) {
            return 100.00; // Default imaging fee
        }
        
        if (str_contains($serviceName, 'procedure')) {
            return 200.00; // Default procedure fee
        }
        
        return 30.00; // Default service fee
    }

    /**
     * Add medication items to invoice.
     */
    private function addMedicationItemsToInvoice(Invoice $invoice, Visit $visit, array $options): void
    {
        foreach ($visit->medicationRequests as $medicationRequest) {
            // Only bill for dispensed medications
            foreach ($medicationRequest->dispenses as $dispense) {
                $this->createInvoiceItem($invoice, [
                    'medication_request' => $medicationRequest,
                    'quantity' => $dispense->quantity,
                    'price' => $this->getMedicationPrice($medicationRequest),
                ]);
            }
        }
    }

    /**
     * Get medication price.
     */
    private function getMedicationPrice($medicationRequest): float
    {
        // In a real system, this would come from a medication pricing table
        // For now, return a default price
        return 10.00; // Default medication price per unit
    }

    /**
     * Create an invoice item.
     */
    private function createInvoiceItem(Invoice $invoice, array $itemData): InvoiceItem
    {
        if (isset($itemData['service'])) {
            return InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'invoiceable_id' => $itemData['service']->id,
                'invoiceable_type' => Service::class,
                'quantity' => $itemData['quantity'],
                'price' => $itemData['price'],
                'paid' => 0,
                'discount' => 0,
            ]);
        }
        
        if (isset($itemData['medication_request'])) {
            return InvoiceItem::create([
                'invoice_id' => $invoice->id,
                'invoiceable_id' => $itemData['medication_request']->id,
                'invoiceable_type' => MedicationRequest::class,
                'quantity' => $itemData['quantity'],
                'price' => $itemData['price'],
                'paid' => 0,
                'discount' => 0,
            ]);
        }
        
        throw new \InvalidArgumentException('Invalid item data provided');
    }

    /**
     * Calculate invoice totals and discounts.
     */
    private function calculateInvoiceTotals(Invoice $invoice): void
    {
        $subtotal = $invoice->calculateTotal();
        $discount = $invoice->calculateDiscount();
        
        $invoice->update([
            'total' => $subtotal,
            'discount' => $discount,
        ]);
    }
}