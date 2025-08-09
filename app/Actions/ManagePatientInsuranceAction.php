<?php

namespace App\Actions;

use App\Models\Patient;
use App\Models\PatientIdentity;
use App\Models\Card;
use App\Services\PatientInsuranceService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ManagePatientInsuranceAction
{
    public function __construct(
        private PatientInsuranceService $insuranceService
    ) {}

    /**
     * Add new insurance identity to patient
     */
    public function addInsuranceIdentity(Patient $patient, array $data): PatientIdentity
    {
        return DB::transaction(function () use ($patient, $data) {
            // Validate card exists and is active
            $card = Card::findOrFail($data['card_id']);
            
            if ($card->is_expired) {
                throw new \InvalidArgumentException('Cannot add expired card to patient');
            }

            // Check for overlapping coverage if end_date is not provided
            if (!isset($data['end_date'])) {
                $this->handleOverlappingCoverage($patient, $data['start_date']);
            }

            // Create the identity
            $identity = PatientIdentity::create([
                'patient_id' => $patient->id,
                'code' => $data['code'],
                'card_id' => $data['card_id'],
                'start_date' => $data['start_date'],
                'end_date' => $data['end_date'] ?? null,
                'detail' => $data['detail'] ?? [],
            ]);

            return $identity->load('card.cardType');
        });
    }

    /**
     * Update existing insurance identity
     */
    public function updateInsuranceIdentity(PatientIdentity $identity, array $data): PatientIdentity
    {
        return DB::transaction(function () use ($identity, $data) {
            // If changing card, validate new card
            if (isset($data['card_id']) && $data['card_id'] !== $identity->card_id) {
                $card = Card::findOrFail($data['card_id']);
                
                if ($card->is_expired) {
                    throw new \InvalidArgumentException('Cannot assign expired card to patient');
                }
            }

            // Update the identity
            $identity->update(array_filter([
                'code' => $data['code'] ?? null,
                'card_id' => $data['card_id'] ?? null,
                'start_date' => $data['start_date'] ?? null,
                'end_date' => $data['end_date'] ?? null,
                'detail' => $data['detail'] ?? null,
            ]));

            return $identity->fresh('card.cardType');
        });
    }

    /**
     * Terminate insurance coverage
     */
    public function terminateInsurance(PatientIdentity $identity, string $endDate = null): PatientIdentity
    {
        $endDate = $endDate ?? Carbon::now()->toDateString();
        
        $identity->update([
            'end_date' => $endDate,
        ]);

        return $identity->fresh('card.cardType');
    }

    /**
     * Get patient insurance status with comprehensive details
     */
    public function getInsuranceStatus(Patient $patient): array
    {
        $beneficiaryStatus = $this->insuranceService->getBeneficiaryStatus($patient);
        $allActiveInsurances = $this->insuranceService->getAllActiveInsurances($patient);
        $insuranceHistory = $this->insuranceService->getInsuranceHistory($patient);
        $overlaps = $this->insuranceService->checkOverlappingCoverage($patient);

        return [
            'patient_id' => $patient->id,
            'beneficiary_status' => $beneficiaryStatus,
            'active_insurances' => $allActiveInsurances->map(function ($identity) {
                return [
                    'identity' => $identity,
                    'validation' => $this->insuranceService->validateInsuranceCoverage($identity),
                ];
            }),
            'insurance_history' => $insuranceHistory,
            'coverage_overlaps' => $overlaps,
            'payment_recommendations' => $this->getPaymentRecommendations($patient),
        ];
    }

    /**
     * Validate multiple patient identities for date conflicts
     */
    public function validateMultipleIdentities(Patient $patient, array $identitiesData): array
    {
        $validationResults = [];
        $conflicts = [];

        // Sort by start date for conflict checking
        usort($identitiesData, function ($a, $b) {
            return strcmp($a['start_date'], $b['start_date']);
        });

        // Check each identity
        foreach ($identitiesData as $index => $identityData) {
            $validation = [
                'index' => $index,
                'is_valid' => true,
                'issues' => [],
            ];

            // Validate date logic
            if (isset($identityData['end_date']) && $identityData['start_date'] >= $identityData['end_date']) {
                $validation['is_valid'] = false;
                $validation['issues'][] = 'Start date must be before end date';
            }

            // Check card validity
            if (isset($identityData['card_id'])) {
                $card = Card::find($identityData['card_id']);
                if (!$card) {
                    $validation['is_valid'] = false;
                    $validation['issues'][] = 'Card does not exist';
                } elseif ($card->is_expired) {
                    $validation['is_valid'] = false;
                    $validation['issues'][] = 'Card is expired';
                } elseif ($identityData['start_date'] < $card->issue_date) {
                    $validation['is_valid'] = false;
                    $validation['issues'][] = 'Coverage cannot start before card issue date';
                }
            }

            $validationResults[] = $validation;

            // Check for conflicts with other identities
            for ($i = 0; $i < $index; $i++) {
                $previousIdentity = $identitiesData[$i];
                
                if ($this->identitiesOverlap($identityData, $previousIdentity)) {
                    $conflicts[] = [
                        'identity_1_index' => $i,
                        'identity_2_index' => $index,
                        'message' => 'Coverage periods overlap',
                    ];
                }
            }
        }

        return [
            'validations' => $validationResults,
            'conflicts' => $conflicts,
            'overall_valid' => empty($conflicts) && collect($validationResults)->every('is_valid'),
        ];
    }

    /**
     * Handle overlapping coverage when adding new insurance
     */
    private function handleOverlappingCoverage(Patient $patient, string $newStartDate): void
    {
        $activeInsurance = $this->insuranceService->getActiveInsurance($patient);
        
        if ($activeInsurance && (!$activeInsurance->end_date || $activeInsurance->end_date >= $newStartDate)) {
            // Automatically end the previous coverage one day before new coverage starts
            $previousEndDate = Carbon::parse($newStartDate)->subDay()->toDateString();
            $activeInsurance->update(['end_date' => $previousEndDate]);
        }
    }

    /**
     * Check if two identity periods overlap
     */
    private function identitiesOverlap(array $identity1, array $identity2): bool
    {
        $start1 = $identity1['start_date'];
        $end1 = $identity1['end_date'] ?? '9999-12-31';
        $start2 = $identity2['start_date'];
        $end2 = $identity2['end_date'] ?? '9999-12-31';

        return $start1 <= $end2 && $start2 <= $end1;
    }

    /**
     * Get payment recommendations based on insurance status
     */
    private function getPaymentRecommendations(Patient $patient): array
    {
        $beneficiaryStatus = $this->insuranceService->getBeneficiaryStatus($patient);
        
        if (!$beneficiaryStatus['is_beneficiary']) {
            return [
                'payment_method' => 'cash',
                'discount_applicable' => false,
                'message' => 'Patient should pay cash as no active insurance is available',
            ];
        }

        $insuranceDetails = $beneficiaryStatus['insurance_details'];
        $discountEligibility = $this->insuranceService->getInsuranceSummaryForInvoice($patient)['discount_eligibility'];

        return [
            'payment_method' => 'insurance',
            'payment_type_id' => $beneficiaryStatus['payment_type_id'],
            'discount_applicable' => $discountEligibility['eligible'],
            'discount_percentage' => $discountEligibility['percentage_discount'],
            'discount_amount' => $discountEligibility['amount_discount'],
            'insurance_type' => $insuranceDetails['card_type'],
            'message' => "Patient has {$insuranceDetails['card_type']} coverage with {$discountEligibility['percentage_discount']}% discount",
        ];
    }
}