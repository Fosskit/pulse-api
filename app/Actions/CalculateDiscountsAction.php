<?php

namespace App\Actions;

use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientIdentity;
use App\Models\Card;
use App\Models\Term;

class CalculateDiscountsAction
{
    /**
     * Calculate insurance coverage discounts for an invoice.
     *
     * @param Invoice $invoice
     * @param array $options Additional options for discount calculation
     * @return array Discount calculation details
     */
    public function execute(Invoice $invoice, array $options = []): array
    {
        $patient = $invoice->visit->patient;
        $subtotal = $invoice->calculateTotal();
        
        // Get insurance coverage details
        $insuranceCoverage = $this->getInsuranceCoverage($patient);
        
        if (!$insuranceCoverage) {
            return [
                'has_insurance' => false,
                'coverage_type' => null,
                'percentage_discount' => 0,
                'amount_discount' => 0,
                'total_discount' => 0,
                'final_amount' => $subtotal,
                'coverage_details' => null,
            ];
        }

        // Calculate discounts based on insurance coverage
        $discountCalculation = $this->calculateInsuranceDiscounts(
            $subtotal,
            $insuranceCoverage,
            $options
        );

        // Update invoice with calculated discounts
        $this->applyDiscountsToInvoice($invoice, $discountCalculation);

        return [
            'has_insurance' => true,
            'coverage_type' => $insuranceCoverage['coverage_type'],
            'percentage_discount' => $discountCalculation['percentage_discount'],
            'amount_discount' => $discountCalculation['amount_discount'],
            'total_discount' => $discountCalculation['total_discount'],
            'final_amount' => $discountCalculation['final_amount'],
            'coverage_details' => $insuranceCoverage,
            'calculation_breakdown' => $discountCalculation['breakdown'],
        ];
    }

    /**
     * Get patient's active insurance coverage details.
     */
    private function getInsuranceCoverage(Patient $patient): ?array
    {
        $activeInsurance = $patient->activeInsurance()->first();
        
        if (!$activeInsurance || !$activeInsurance->card) {
            return null;
        }

        $card = $activeInsurance->card;
        
        return [
            'identity_id' => $activeInsurance->id,
            'card_id' => $card->id,
            'card_number' => $activeInsurance->code,
            'coverage_type' => $this->determineCoverageType($card),
            'coverage_percentage' => $this->getCoveragePercentage($card),
            'maximum_coverage' => $this->getMaximumCoverage($card),
            'copay_amount' => $this->getCopayAmount($card),
            'valid_from' => $activeInsurance->start_date,
            'valid_until' => $activeInsurance->end_date,
            'beneficiary_type' => $this->getBeneficiaryType($activeInsurance),
        ];
    }

    /**
     * Determine coverage type based on card information.
     */
    private function determineCoverageType(Card $card): string
    {
        // This would typically be based on card type or insurance provider
        // For now, we'll use a simple mapping based on card name/type
        $cardName = strtolower($card->name ?? '');
        
        if (str_contains($cardName, 'government') || str_contains($cardName, 'public')) {
            return 'government';
        }
        
        if (str_contains($cardName, 'private') || str_contains($cardName, 'corporate')) {
            return 'private';
        }
        
        if (str_contains($cardName, 'nssf') || str_contains($cardName, 'social')) {
            return 'social_security';
        }
        
        return 'general';
    }

    /**
     * Get coverage percentage based on card type.
     */
    private function getCoveragePercentage(Card $card): float
    {
        $coverageType = $this->determineCoverageType($card);
        
        return match ($coverageType) {
            'government' => 90.0, // Government insurance covers 90%
            'social_security' => 80.0, // NSSF covers 80%
            'private' => 70.0, // Private insurance covers 70%
            'general' => 60.0, // General insurance covers 60%
            default => 50.0,
        };
    }

    /**
     * Get maximum coverage amount based on card type.
     */
    private function getMaximumCoverage(Card $card): ?float
    {
        $coverageType = $this->determineCoverageType($card);
        
        return match ($coverageType) {
            'government' => null, // No limit for government insurance
            'social_security' => 5000.0, // NSSF has $5000 annual limit
            'private' => 10000.0, // Private insurance has $10000 annual limit
            'general' => 2000.0, // General insurance has $2000 annual limit
            default => 1000.0,
        };
    }

    /**
     * Get copay amount based on card type.
     */
    private function getCopayAmount(Card $card): float
    {
        $coverageType = $this->determineCoverageType($card);
        
        return match ($coverageType) {
            'government' => 0.0, // No copay for government insurance
            'social_security' => 5.0, // $5 copay for NSSF
            'private' => 10.0, // $10 copay for private insurance
            'general' => 15.0, // $15 copay for general insurance
            default => 20.0,
        };
    }

    /**
     * Get beneficiary type based on patient identity.
     */
    private function getBeneficiaryType(PatientIdentity $identity): string
    {
        // This would typically be stored in the patient identity or card
        // For now, we'll determine based on identity code patterns
        $code = $identity->code ?? '';
        
        if (str_starts_with($code, 'GOV')) {
            return 'government_employee';
        }
        
        if (str_starts_with($code, 'NSSF')) {
            return 'social_security_member';
        }
        
        if (str_starts_with($code, 'DEP')) {
            return 'dependent';
        }
        
        return 'primary';
    }

    /**
     * Calculate insurance discounts based on coverage details.
     */
    private function calculateInsuranceDiscounts(
        float $subtotal,
        array $insuranceCoverage,
        array $options = []
    ): array {
        $coveragePercentage = $insuranceCoverage['coverage_percentage'];
        $maximumCoverage = $insuranceCoverage['maximum_coverage'];
        $copayAmount = $insuranceCoverage['copay_amount'];
        
        // Calculate percentage-based discount
        $percentageDiscount = ($subtotal * $coveragePercentage) / 100;
        
        // Apply maximum coverage limit if applicable
        if ($maximumCoverage && $percentageDiscount > $maximumCoverage) {
            $percentageDiscount = $maximumCoverage;
        }
        
        // Calculate additional amount discount (if any)
        $amountDiscount = $options['additional_discount'] ?? 0;
        
        // Total discount
        $totalDiscount = $percentageDiscount + $amountDiscount;
        
        // Calculate final amount after discount but before copay
        $amountAfterDiscount = $subtotal - $totalDiscount;
        
        // Add copay to final amount
        $finalAmount = $amountAfterDiscount + $copayAmount;
        
        // Ensure final amount is not negative
        $finalAmount = max(0, $finalAmount);
        
        return [
            'subtotal' => $subtotal,
            'coverage_percentage' => $coveragePercentage,
            'percentage_discount' => $percentageDiscount,
            'amount_discount' => $amountDiscount,
            'total_discount' => $totalDiscount,
            'copay_amount' => $copayAmount,
            'final_amount' => $finalAmount,
            'savings' => $subtotal - $finalAmount,
            'breakdown' => [
                'original_amount' => $subtotal,
                'insurance_coverage' => $percentageDiscount,
                'additional_discount' => $amountDiscount,
                'total_discount_applied' => $totalDiscount,
                'amount_after_discount' => $amountAfterDiscount,
                'copay_required' => $copayAmount,
                'patient_responsibility' => $finalAmount,
                'insurance_pays' => $totalDiscount,
            ],
        ];
    }

    /**
     * Apply calculated discounts to the invoice.
     */
    private function applyDiscountsToInvoice(Invoice $invoice, array $discountCalculation): void
    {
        $invoice->update([
            'percentage_discount' => ($discountCalculation['percentage_discount'] / $discountCalculation['subtotal']) * 100,
            'amount_discount' => $discountCalculation['amount_discount'],
            'discount' => $discountCalculation['total_discount'],
            'total' => $discountCalculation['subtotal'],
        ]);
    }

    /**
     * Generate insurance claims data for external processing.
     */
    public function generateInsuranceClaim(Invoice $invoice): array
    {
        $patient = $invoice->visit->patient;
        $insuranceCoverage = $this->getInsuranceCoverage($patient);
        
        if (!$insuranceCoverage) {
            throw new \InvalidArgumentException('Patient does not have active insurance coverage');
        }

        return [
            'claim_id' => $this->generateClaimId($invoice),
            'invoice_id' => $invoice->id,
            'invoice_code' => $invoice->code,
            'patient_information' => [
                'patient_id' => $patient->id,
                'patient_code' => $patient->code,
                'full_name' => $patient->full_name,
                'demographics' => $patient->demographics?->toArray(),
            ],
            'insurance_information' => [
                'card_number' => $insuranceCoverage['card_number'],
                'coverage_type' => $insuranceCoverage['coverage_type'],
                'beneficiary_type' => $insuranceCoverage['beneficiary_type'],
                'valid_from' => $insuranceCoverage['valid_from'],
                'valid_until' => $insuranceCoverage['valid_until'],
            ],
            'visit_information' => [
                'visit_id' => $invoice->visit->id,
                'visit_ulid' => $invoice->visit->ulid,
                'admission_date' => $invoice->visit->admitted_at,
                'discharge_date' => $invoice->visit->discharged_at,
                'facility_id' => $invoice->visit->facility_id,
            ],
            'billing_information' => [
                'invoice_date' => $invoice->date,
                'total_amount' => $invoice->total,
                'discount_applied' => $invoice->discount,
                'insurance_portion' => $invoice->discount,
                'patient_portion' => $invoice->remaining_balance,
                'services_provided' => $this->getServicesForClaim($invoice),
            ],
            'claim_status' => 'pending',
            'generated_at' => now(),
        ];
    }

    /**
     * Generate unique claim ID.
     */
    private function generateClaimId(Invoice $invoice): string
    {
        $date = $invoice->date ?? now();
        $prefix = 'CLM-' . $date->format('Ymd');
        $sequence = str_pad($invoice->id, 6, '0', STR_PAD_LEFT);
        
        return $prefix . '-' . $sequence;
    }

    /**
     * Get services provided for insurance claim.
     */
    private function getServicesForClaim(Invoice $invoice): array
    {
        $services = [];
        
        foreach ($invoice->invoiceItems as $item) {
            $services[] = [
                'item_id' => $item->id,
                'service_type' => $item->invoiceable_type,
                'service_id' => $item->invoiceable_id,
                'service_name' => $this->getServiceName($item),
                'quantity' => $item->quantity,
                'unit_price' => $item->price,
                'total_price' => $item->line_total,
                'discount_applied' => $item->discount,
            ];
        }
        
        return $services;
    }

    /**
     * Get service name for claim.
     */
    private function getServiceName($item): string
    {
        if ($item->invoiceable) {
            return $item->invoiceable->name ?? 'Unknown Service';
        }
        
        return 'Service Not Found';
    }
}