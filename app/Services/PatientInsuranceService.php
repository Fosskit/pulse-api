<?php

namespace App\Services;

use App\Models\Patient;
use App\Models\PatientIdentity;
use App\Models\Card;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Collection;

class PatientInsuranceService
{
    /**
     * Determine payment type ID based on active insurance cards
     * This implements the business logic for mapping card types to payment types
     */
    public function determinePaymentTypeId(Patient $patient): ?int
    {
        $activeInsurance = $this->getActiveInsurance($patient);
        
        if (!$activeInsurance) {
            // No active insurance - return default payment type (cash/self-pay)
            return $this->getDefaultPaymentTypeId();
        }

        // Get the card type and map it to payment type
        $cardType = $activeInsurance->card->cardType;
        
        if (!$cardType) {
            return $this->getDefaultPaymentTypeId();
        }

        // Business logic for mapping card types to payment types
        return $this->mapCardTypeToPaymentType($cardType->code);
    }

    /**
     * Get active insurance for a patient with comprehensive validation
     */
    public function getActiveInsurance(Patient $patient): ?PatientIdentity
    {
        $now = Carbon::now()->toDateString();
        
        return $patient->identities()
            ->with(['card.cardType'])
            ->where('start_date', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $now);
            })
            ->whereHas('card', function ($query) use ($now) {
                $query->where('expiry_date', '>=', $now);
            })
            ->orderBy('start_date', 'desc')
            ->first();
    }

    /**
     * Get all active insurances for a patient (in case of multiple valid cards)
     */
    public function getAllActiveInsurances(Patient $patient): Collection
    {
        $now = Carbon::now()->toDateString();
        
        return $patient->identities()
            ->with(['card.cardType'])
            ->where('start_date', '<=', $now)
            ->where(function ($query) use ($now) {
                $query->whereNull('end_date')
                      ->orWhere('end_date', '>=', $now);
            })
            ->whereHas('card', function ($query) use ($now) {
                $query->where('expiry_date', '>=', $now);
            })
            ->orderBy('start_date', 'desc')
            ->get();
    }

    /**
     * Check if patient is a beneficiary (has any active insurance)
     */
    public function isBeneficiary(Patient $patient): bool
    {
        return $this->getActiveInsurance($patient) !== null;
    }

    /**
     * Get beneficiary status with details
     */
    public function getBeneficiaryStatus(Patient $patient): array
    {
        $activeInsurance = $this->getActiveInsurance($patient);
        
        if (!$activeInsurance) {
            return [
                'is_beneficiary' => false,
                'status' => 'no_insurance',
                'message' => 'Patient has no active insurance coverage',
                'payment_type_id' => $this->getDefaultPaymentTypeId(),
            ];
        }

        $card = $activeInsurance->card;
        $cardType = $card->cardType;

        return [
            'is_beneficiary' => true,
            'status' => 'active_insurance',
            'message' => 'Patient has active insurance coverage',
            'payment_type_id' => $this->determinePaymentTypeId($patient),
            'insurance_details' => [
                'identity_code' => $activeInsurance->code,
                'card_code' => $card->code,
                'card_type' => $cardType->name ?? 'Unknown',
                'card_type_code' => $cardType->code ?? null,
                'expiry_date' => $card->expiry_date,
                'days_until_expiry' => Carbon::parse($card->expiry_date)->diffInDays(Carbon::now()),
                'coverage_start' => $activeInsurance->start_date,
                'coverage_end' => $activeInsurance->end_date,
            ],
        ];
    }

    /**
     * Validate patient identity dates and card validity
     */
    public function validateInsuranceCoverage(PatientIdentity $identity): array
    {
        $now = Carbon::now();
        $issues = [];
        $isValid = true;

        // Check identity date validity
        if ($identity->start_date > $now->toDateString()) {
            $issues[] = 'Coverage has not started yet';
            $isValid = false;
        }

        if ($identity->end_date && $identity->end_date < $now->toDateString()) {
            $issues[] = 'Coverage has expired';
            $isValid = false;
        }

        // Check card validity
        if ($identity->card) {
            if ($identity->card->issue_date > $now->toDateString()) {
                $issues[] = 'Card is not yet valid';
                $isValid = false;
            }

            if ($identity->card->expiry_date < $now->toDateString()) {
                $issues[] = 'Card has expired';
                $isValid = false;
            }

            // Warn if card expires soon (within 30 days)
            $daysUntilExpiry = Carbon::parse($identity->card->expiry_date)->diffInDays($now);
            if ($daysUntilExpiry <= 30 && $daysUntilExpiry > 0) {
                $issues[] = "Card expires in {$daysUntilExpiry} days";
            }
        }

        return [
            'is_valid' => $isValid,
            'issues' => $issues,
            'status' => $isValid ? 'valid' : 'invalid',
        ];
    }

    /**
     * Get insurance history for a patient
     */
    public function getInsuranceHistory(Patient $patient): Collection
    {
        return $patient->identities()
            ->with(['card.cardType'])
            ->orderBy('start_date', 'desc')
            ->get()
            ->map(function ($identity) {
                $validation = $this->validateInsuranceCoverage($identity);
                
                return [
                    'identity' => $identity,
                    'validation' => $validation,
                    'is_current' => $identity->is_active,
                ];
            });
    }

    /**
     * Check for overlapping insurance coverage
     */
    public function checkOverlappingCoverage(Patient $patient): array
    {
        $identities = $patient->identities()
            ->with(['card.cardType'])
            ->orderBy('start_date')
            ->get();

        $overlaps = [];
        
        for ($i = 0; $i < $identities->count() - 1; $i++) {
            $current = $identities[$i];
            $next = $identities[$i + 1];
            
            // Check if current coverage overlaps with next
            if ($current->end_date && $next->start_date <= $current->end_date) {
                $overlaps[] = [
                    'identity_1' => $current->id,
                    'identity_2' => $next->id,
                    'overlap_start' => $next->start_date,
                    'overlap_end' => $current->end_date,
                    'message' => "Coverage overlap between {$current->code} and {$next->code}",
                ];
            }
        }

        return $overlaps;
    }

    /**
     * Map card type codes to payment type IDs
     * This would typically be configured based on business rules
     */
    private function mapCardTypeToPaymentType(string $cardTypeCode): int
    {
        // This mapping should be configurable or stored in database
        $mapping = [
            'NSSF' => 1,        // National Social Security Fund
            'CBHI' => 2,        // Community Based Health Insurance
            'HEF' => 3,         // Health Equity Fund
            'PRIVATE' => 4,     // Private Insurance
            'GOVERNMENT' => 5,  // Government Employee Insurance
            'MILITARY' => 6,    // Military Insurance
            'STUDENT' => 7,     // Student Insurance
        ];

        return $mapping[$cardTypeCode] ?? $this->getDefaultPaymentTypeId();
    }

    /**
     * Get default payment type ID for cash/self-pay patients
     */
    private function getDefaultPaymentTypeId(): int
    {
        // This should be configurable
        return 1; // Assuming 1 is cash/self-pay
    }

    /**
     * Generate insurance summary for invoice generation
     */
    public function getInsuranceSummaryForInvoice(Patient $patient): array
    {
        $beneficiaryStatus = $this->getBeneficiaryStatus($patient);
        
        return [
            'patient_id' => $patient->id,
            'is_beneficiary' => $beneficiaryStatus['is_beneficiary'],
            'payment_type_id' => $beneficiaryStatus['payment_type_id'],
            'insurance_coverage' => $beneficiaryStatus['is_beneficiary'] ? [
                'type' => $beneficiaryStatus['insurance_details']['card_type'] ?? 'Unknown',
                'code' => $beneficiaryStatus['insurance_details']['identity_code'] ?? null,
                'expiry_date' => $beneficiaryStatus['insurance_details']['expiry_date'] ?? null,
            ] : null,
            'discount_eligibility' => $this->calculateDiscountEligibility($patient),
        ];
    }

    /**
     * Calculate discount eligibility based on insurance type
     */
    private function calculateDiscountEligibility(Patient $patient): array
    {
        $activeInsurance = $this->getActiveInsurance($patient);
        
        if (!$activeInsurance) {
            return [
                'eligible' => false,
                'percentage_discount' => 0,
                'amount_discount' => 0,
                'reason' => 'No active insurance',
            ];
        }

        $cardTypeCode = $activeInsurance->card->cardType->code ?? null;
        
        // Business rules for discount calculation
        $discountRules = [
            'HEF' => ['percentage' => 100, 'amount' => 0], // Health Equity Fund - 100% coverage
            'NSSF' => ['percentage' => 80, 'amount' => 0], // NSSF - 80% coverage
            'CBHI' => ['percentage' => 70, 'amount' => 0], // CBHI - 70% coverage
            'GOVERNMENT' => ['percentage' => 90, 'amount' => 0], // Government - 90% coverage
            'MILITARY' => ['percentage' => 95, 'amount' => 0], // Military - 95% coverage
            'PRIVATE' => ['percentage' => 60, 'amount' => 0], // Private - varies, default 60%
            'STUDENT' => ['percentage' => 50, 'amount' => 0], // Student - 50% coverage
        ];

        $rule = $discountRules[$cardTypeCode] ?? ['percentage' => 0, 'amount' => 0];

        return [
            'eligible' => $rule['percentage'] > 0 || $rule['amount'] > 0,
            'percentage_discount' => $rule['percentage'],
            'amount_discount' => $rule['amount'],
            'reason' => "Insurance coverage: {$cardTypeCode}",
        ];
    }
}