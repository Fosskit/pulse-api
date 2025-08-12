<?php

namespace App\Actions;

use App\Models\Visit;
use Illuminate\Support\Collection;

class ExportVisitAction
{
    public function execute(int $visitId): array
    {
        $visit = Visit::with([
            'patient.demographics',
            'patient.currentAddress.province',
            'patient.currentAddress.district', 
            'patient.currentAddress.commune',
            'patient.currentAddress.village',
            'patient.identities.card.cardType',
            'facility',
            'visitType',
            'admissionType',
            'dischargeType',
            'visitOutcome',
            'encounters.encounterType',
            'encounters.observations.observationConcept',
            'medicationRequests.instruction',
            'serviceRequests.laboratoryRequest',
            'serviceRequests.imagingRequest',

            'invoices.invoiceItems'
        ])->findOrFail($visitId);

        return [
            'visits' => [
                $this->transformVisit($visit)
            ]
        ];
    }

    private function transformVisit(Visit $visit): array
    {
        return [
            'health_facility_code' => $visit->facility?->code,
            'patient_code' => $visit->patient->code,
            'code' => $visit->ulid,
            'admission_type' => $visit->admissionType?->name,
            'discharge_type' => $visit->dischargeType?->name,
            'visit_outcome' => $visit->visitOutcome?->name,
            'visit_type' => $visit->visitType?->name,
            'admitted_at' => $visit->admitted_at?->toISOString(),
            'discharged_at' => $visit->discharged_at?->toISOString(),
            'followup_at' => null, // Not in current schema
            'created_at' => $visit->created_at->toISOString(),
            'updated_at' => $visit->updated_at->toISOString(),
            'patient' => $this->transformPatient($visit->patient),
            'triages' => $this->transformTriages($visit),
            'vital_signs' => $this->transformVitalSigns($visit),
            'medical_histories' => $this->transformMedicalHistories($visit),
            'physical_examinations' => $this->transformPhysicalExaminations($visit),
            'outpatients' => $this->transformOutpatients($visit),
            'inpatients' => $this->transformInpatients($visit),
            'emergencies' => $this->transformEmergencies($visit),
            'surgeries' => $this->transformSurgeries($visit),
            'progress_notes' => $this->transformProgressNotes($visit),
            'soaps' => $this->transformSoaps($visit),
            'laboratories' => $this->transformLaboratories($visit),
            'imageries' => $this->transformImageries($visit),
            'diagnosis' => $this->transformDiagnosis($visit),
            'prescriptions' => $this->transformPrescriptions($visit),
            'referrals' => $this->transformReferrals($visit),
            'invoices' => $this->transformInvoices($visit),
        ];
    }

    private function transformPatient($patient): array
    {
        $demographics = $patient->demographics;
        $address = $patient->currentAddress;
        
        return [
            'code' => $patient->code,
            'surname' => $demographics?->family_name ?? '',
            'name' => $demographics?->given_name ?? '',
            'sex' => $this->extractSex($demographics),
            'birthdate' => $demographics?->birthdate?->format('Y-m-d'),
            'phone' => $demographics?->telephone,
            'nationality' => $demographics?->nationality?->name,
            'disabilities' => $this->extractDisabilities($demographics),
            'occupation' => $this->extractOccupation($demographics),
            'marital_status' => $this->extractMaritalStatus($demographics),
            'photos' => $this->extractPhotos($demographics),
            'address' => $this->transformAddress($address),
            'identifications' => $this->transformIdentifications($patient->identities),
            'death_at' => $demographics?->died_at?->toISOString(),
            'spid' => null, // Not in current schema
            'created_at' => $patient->created_at->toISOString(),
            'updated_at' => $patient->updated_at->toISOString(),
        ];
    }

    private function transformAddress($address): ?array
    {
        if (!$address) {
            return null;
        }

        return [
            'province' => [
                'code' => $address->province?->code,
                'name' => $address->province?->name,
            ],
            'district' => [
                'code' => $address->district?->code,
                'name' => $address->district?->name,
            ],
            'commune' => [
                'code' => $address->commune?->code,
                'name' => $address->commune?->name,
            ],
            'village' => [
                'code' => $address->village?->code,
                'name' => $address->village?->name,
            ],
            'house_number' => $this->extractHouseNumber($address->street_address),
            'street_number' => $this->extractStreetNumber($address->street_address),
            'location' => $address->street_address,
        ];
    }

    private function transformIdentifications(Collection $identities): array
    {
        return $identities->map(function ($identity) {
            return [
                'patient_code' => $identity->patient->code,
                'card_code' => $identity->code,
                'card_type' => $identity->card?->cardType?->name,
            ];
        })->toArray();
    }

    private function transformTriages(Visit $visit): array
    {
        // Get triage encounters
        $triageEncounters = $visit->encounters()
            ->whereHas('encounterType', function ($query) {
                $query->where('name', 'Triage');
            })
            ->with(['observations.observationConcept', 'clinicalFormTemplate'])
            ->get();

        return $triageEncounters->map(function ($encounter) use ($visit) {
            $observations = $encounter->observations;
            
            return [
                'parent_code' => $visit->patient->code,
                'visit_code' => $visit->ulid,
                'code' => $encounter->ulid,
                'chief_complaint' => $this->extractChiefComplaint($observations),
                'height' => $this->extractVitalSign($observations, 'Height'),
                'weight' => $this->extractVitalSign($observations, 'Weight'),
                'recorded_at' => $encounter->started_at?->toISOString(),
                'recorded_by' => $this->extractRecordedBy($encounter),
                'title' => $this->extractRecorderTitle($encounter),
            ];
        })->toArray();
    }

    private function transformVitalSigns(Visit $visit): array
    {
        $vitalSignsEncounters = $visit->encounters()
            ->whereHas('observations.observationConcept', function ($query) {
                $query->whereIn('name', [
                    'Blood Pressure Systolic', 'Blood Pressure Diastolic',
                    'Pulse', 'Respiratory Rate', 'Temperature', 'SpO2', 'Glucose'
                ]);
            })
            ->with(['observations.observationConcept'])
            ->get();

        return $vitalSignsEncounters->map(function ($encounter) {
            $vitalObservations = $encounter->observations->filter(function ($observation) {
                return in_array($observation->observationConcept?->name, [
                    'Blood Pressure Systolic', 'Blood Pressure Diastolic',
                    'Pulse', 'Respiratory Rate', 'Temperature', 'SpO2', 'Glucose'
                ]);
            });

            return [
                'encounter_code' => $encounter->ulid,
                'recorded_at' => $encounter->started_at?->toISOString(),
                'recorded_by' => $this->extractRecordedBy($encounter),
                'title' => $this->extractRecorderTitle($encounter),
                'observations' => $this->transformVitalSignObservations($vitalObservations),
            ];
        })->filter(function ($vitalSign) {
            return count($vitalSign['observations']) > 0;
        })->values()->toArray();
    }

    private function transformVitalSignObservations(Collection $observations): array
    {
        return $observations->map(function ($observation) {
            return [
                'name' => $observation->observationConcept?->name,
                'value' => $observation->value_number ?? $observation->value_string,
            ];
        })->toArray();
    }

    private function transformMedicalHistories(Visit $visit): array
    {
        // Get medical history observations
        $historyObservations = $visit->encounters()
            ->with(['observations.observationConcept'])
            ->get()
            ->flatMap(function ($encounter) {
                return $encounter->observations->filter(function ($observation) {
                    return in_array($observation->observationConcept?->name, [
                        'Immunization History', 'Allergies', 'Past Surgical History',
                        'Past Medical History', 'Family History', 'Current Illness History',
                        'Current Medication'
                    ]);
                });
            });

        return $historyObservations->map(function ($observation) use ($visit) {
            return [
                'patient_code' => $visit->patient->code,
                'visit_code' => $visit->ulid,
                'encounter_code' => $observation->encounter->ulid,
                'name' => $observation->observationConcept?->name,
                'value' => $this->parseObservationValue($observation),
            ];
        })->toArray();
    }

    private function transformPhysicalExaminations(Visit $visit): array
    {
        $examObservations = $visit->encounters()
            ->with(['observations.observationConcept'])
            ->get()
            ->flatMap(function ($encounter) {
                return $encounter->observations->filter(function ($observation) {
                    return str_contains($observation->observationConcept?->name ?? '', 'Examination') ||
                           in_array($observation->observationConcept?->name, [
                               'General Appearance', 'Skin', 'Digestive System'
                           ]);
                });
            });

        return $examObservations->map(function ($observation) use ($visit) {
            return [
                'patient_code' => $visit->patient->code,
                'visit_code' => $visit->ulid,
                'encounter_code' => $observation->encounter->ulid,
                'name' => $observation->observationConcept?->name,
                'value' => $this->parseObservationValue($observation),
                'value_type' => $this->determineValueType($observation),
                'value_unit' => null,
            ];
        })->toArray();
    }

    private function transformOutpatients(Visit $visit): array
    {
        return $this->transformEncountersByType($visit, 'Outpatient');
    }

    private function transformInpatients(Visit $visit): array
    {
        return $this->transformEncountersByType($visit, 'Inpatient');
    }

    private function transformEmergencies(Visit $visit): array
    {
        return $this->transformEncountersByType($visit, 'Emergency');
    }

    private function transformSurgeries(Visit $visit): array
    {
        return $this->transformEncountersByType($visit, 'Surgery');
    }

    private function transformProgressNotes(Visit $visit): array
    {
        return $this->transformEncountersByType($visit, 'Progress Note');
    }

    private function transformEncountersByType(Visit $visit, string $encounterType): array
    {
        $encounters = $visit->encounters()
            ->whereHas('encounterType', function ($query) use ($encounterType) {
                $query->where('name', $encounterType);
            })
            ->with(['clinicalFormTemplate'])
            ->get();

        return $encounters->map(function ($encounter) use ($visit, $encounterType) {
            $baseData = [
                'visit_code' => $visit->ulid,
                'code' => $encounter->ulid,
                'name' => $encounter->encounterType?->name,
                'service_type' => $this->extractServiceType($encounter),
                'started_at' => $encounter->started_at?->toISOString(),
                'ended_at' => $encounter->ended_at?->toISOString(),
                'encountered_by' => $this->extractRecordedBy($encounter),
                'title' => $this->extractRecorderTitle($encounter),
                'created_at' => $encounter->created_at->toISOString(),
                'updated_at' => $encounter->updated_at->toISOString(),
            ];

            // Add surgery-specific fields if this is a surgery encounter
            if ($encounterType === 'Surgery') {
                $baseData = array_merge($baseData, $this->extractSurgeryDetails($encounter));
            }

            return $baseData;
        })->toArray();
    }

    private function transformSoaps(Visit $visit): array
    {
        // SOAP notes are stored as observations with specific concept types
        $soapEncounters = $visit->encounters()
            ->whereHas('observations.observationConcept', function ($query) {
                $query->whereIn('name', [
                    'Subjective', 'Objective', 'Assessment', 'Plan', 'Evaluation'
                ]);
            })
            ->with(['observations.observationConcept'])
            ->get();

        return $soapEncounters->map(function ($encounter) {
            $observations = $encounter->observations;
            
            return [
                'encounter_code' => $encounter->ulid,
                'subjective' => $this->extractObservationValue($observations, 'Subjective'),
                'objective' => $this->extractObservationValue($observations, 'Objective'),
                'assessment' => $this->extractObservationValue($observations, 'Assessment'),
                'plan' => $this->extractObservationValue($observations, 'Plan'),
                'evaluation' => $this->extractObservationValue($observations, 'Evaluation'),
            ];
        })->filter(function ($soap) {
            // Only include SOAP notes that have at least one component
            return $soap['subjective'] || $soap['objective'] || $soap['assessment'] || $soap['plan'];
        })->values()->toArray();
    }

    private function transformLaboratories(Visit $visit): array
    {
        $labRequests = $visit->serviceRequests()
            ->where('request_type', 'Laboratory')
            ->with(['laboratoryRequest', 'observations.observationConcept'])
            ->get();

        return $labRequests->map(function ($request) use ($visit) {
            return [
                'patient_code' => $visit->patient->code,
                'visit_code' => $visit->ulid,
                'encounter_code' => null, // Not directly linked
                'request_code' => $request->ulid,
                'requested_at' => $request->ordered_at?->toISOString(),
                'requested_by' => null, // Not in current schema
                'title' => null,
                'collected_at' => $request->completed_at?->toISOString(),
                'collected_by' => null, // Not in current schema
                'results' => $this->transformLabResults($request->observations),
            ];
        })->toArray();
    }

    private function transformLabResults(Collection $observations): array
    {
        return $observations->map(function ($observation) {
            return [
                'name' => $observation->observationConcept?->name,
                'category' => $this->extractObservationCategory($observation),
                'value' => $observation->value_number ?? $observation->value_string,
                'value_type' => $this->determineValueType($observation),
                'value_unit' => $this->extractObservationUnit($observation),
                'reference_range' => $this->extractReferenceRange($observation),
                'interpretation' => $this->extractInterpretation($observation),
                'verified_at' => $observation->updated_at?->toISOString(),
                'verified_by' => $this->extractVerifiedBy($observation),
                'recorded_at' => $observation->created_at?->toISOString(),
                'recorded_by' => $this->extractRecordedBy($observation->encounter ?? null),
            ];
        })->toArray();
    }

    private function transformImageries(Visit $visit): array
    {
        $imagingRequests = $visit->serviceRequests()
            ->where('request_type', 'Imaging')
            ->with(['imagingRequest', 'observations.observationConcept'])
            ->get();

        return $imagingRequests->map(function ($request) use ($visit) {
            return [
                'patient_code' => $visit->patient->code,
                'visit_code' => $visit->ulid,
                'encounter_code' => null, // Not directly linked
                'request_code' => $request->ulid,
                'requested_at' => $request->ordered_at?->toISOString(),
                'requested_by' => null, // Not in current schema
                'title' => null,
                'collected_at' => $request->completed_at?->toISOString(),
                'collected_by' => null, // Not in current schema
                'results' => $this->transformImagingResults($request->observations),
            ];
        })->toArray();
    }

    private function transformImagingResults(Collection $observations): array
    {
        return $observations->map(function ($observation) {
            return [
                'name' => $observation->observationConcept?->name,
                'category' => $this->extractObservationCategory($observation),
                'images' => $this->extractImageUrls($observation),
                'result' => $observation->value_string,
                'conclusion' => $this->extractConclusion($observation),
                'verified_at' => $observation->updated_at?->toISOString(),
                'verified_by' => $this->extractVerifiedBy($observation),
                'recorded_at' => $observation->created_at?->toISOString(),
                'recorded_by' => $this->extractRecordedBy($observation->encounter ?? null),
            ];
        })->toArray();
    }

    private function transformDiagnosis(Visit $visit): array
    {
        // Diagnosis would be stored as observations with specific concept types
        $diagnosisObservations = $visit->encounters()
            ->with(['observations.observationConcept'])
            ->get()
            ->flatMap(function ($encounter) {
                return $encounter->observations->filter(function ($observation) {
                    return str_contains($observation->observationConcept?->name ?? '', 'Diagnosis');
                });
            });

        return $diagnosisObservations->map(function ($observation) use ($visit) {
            return [
                'patient_code' => $visit->patient->code,
                'visit_code' => $visit->ulid,
                'encounter_code' => $observation->encounter->ulid,
                'diagnosis_type_name' => 'Primary', // Default
                'diagnosis_code' => null, // Not in current schema
                'diagnosis_name' => $observation->observationConcept?->name,
                'diagnosis_description' => $observation->value_string,
                'diagnosed_at' => $observation->created_at?->toISOString(),
                'diagnosed_by' => null, // Not in current schema
                'title' => null,
            ];
        })->toArray();
    }

    private function transformPrescriptions(Visit $visit): array
    {
        $prescriptions = $visit->medicationRequests()
            ->with(['instruction'])
            ->get()
            ->groupBy('created_at'); // Group by prescription session

        return $prescriptions->map(function ($prescriptionGroup, $prescribedAt) use ($visit) {
            $firstPrescription = $prescriptionGroup->first();
            
            return [
                'patient_code' => $visit->patient->code,
                'visit_code' => $visit->ulid,
                'encounter_code' => null, // Not directly linked
                'code' => $firstPrescription->ulid,
                'prescribed_at' => $prescribedAt,
                'prescribed_by' => null, // Not in current schema
                'title' => null,
                'medications' => $prescriptionGroup->map(function ($prescription) {
                    $instruction = $prescription->instruction;
                    
                    return [
                        'code' => $prescription->ulid,
                        'medicine_name' => null, // Not in current schema
                        'strength' => null, // Not in current schema
                        'form' => null, // Not in current schema
                        'method' => null, // Not in current schema
                        'unit' => null, // Not in current schema
                        'morning' => $instruction?->morning,
                        'afternoon' => $instruction?->afternoon,
                        'evening' => $instruction?->evening,
                        'night' => $instruction?->night,
                        'days' => $instruction?->days,
                        'interval' => null, // Not in current schema
                        'note' => $instruction?->note,
                    ];
                })->toArray(),
            ];
        })->values()->toArray();
    }

    private function transformReferrals(Visit $visit): array
    {
        // Referrals structure not defined in current schema
        return [
            'referred_from' => null,
            'referred_to' => null,
        ];
    }

    private function transformInvoices(Visit $visit): array
    {
        return $visit->invoices->map(function ($invoice) use ($visit) {
            return [
                'patient_code' => $visit->patient->code,
                'visit_code' => $visit->ulid,
                'encounter_code' => null, // Not directly linked
                'code' => $invoice->ulid,
                'payment_type' => null, // Not in current schema
                'invoice_date' => $invoice->created_at->toISOString(),
                'total' => $invoice->total,
                'created_at' => $invoice->created_at->toISOString(),
                'updated_at' => $invoice->updated_at->toISOString(),
                'cashier' => null, // Not in current schema
                'services' => $this->transformInvoiceServices($invoice),
                'medications' => $this->transformInvoiceMedications($invoice),
            ];
        })->toArray();
    }

    private function transformInvoiceServices($invoice): array
    {
        return $invoice->invoiceItems()
            ->where('invoiceable_type', \App\Models\Service::class)
            ->with('invoiceable')
            ->get()
            ->map(function ($item) use ($invoice) {
                return [
                    'invoice_code' => $invoice->ulid,
                    'service_code' => $item->invoiceable?->code,
                    'service_name' => $item->invoiceable?->name,
                    'service_category' => $item->invoiceable?->category?->name,
                    'price' => $item->price,
                    'payment' => $item->paid,
                    'paid' => $item->paid > 0,
                    'discount_type' => $item->discountType?->name,
                    'discount' => $item->discount ?? 0,
                ];
            })->toArray();
    }

    private function transformInvoiceMedications($invoice): array
    {
        return $invoice->invoiceItems()
            ->where('invoiceable_type', \App\Models\MedicationRequest::class)
            ->with('invoiceable')
            ->get()
            ->map(function ($item) use ($invoice) {
                return [
                    'invoice_code' => $invoice->ulid,
                    'medicine_code' => $item->invoiceable?->ulid,
                    'medicine_name' => $item->invoiceable?->medication_name ?? 'Unknown Medication',
                    'quantity' => $item->quantity,
                    'price' => $item->price,
                    'payment' => $item->paid,
                    'paid' => $item->paid > 0,
                    'discount_type' => $item->discountType?->name,
                    'discount' => $item->discount ?? 0,
                ];
            })->toArray();
    }

    // Helper methods
    private function extractSex($demographics): ?string
    {
        if (!$demographics || !$demographics->sex) {
            return null;
        }
        
        return strtoupper(substr($demographics->sex, 0, 1));
    }

    private function extractDisabilities($demographics): array
    {
        // Extract from demographics array if available
        if ($demographics && is_array($demographics->address)) {
            return $demographics->address['disabilities'] ?? [];
        }
        return [];
    }

    private function extractOccupation($demographics): ?string
    {
        // Extract from demographics array if available
        if ($demographics && is_array($demographics->address)) {
            return $demographics->address['occupation'] ?? null;
        }
        return null;
    }

    private function extractMaritalStatus($demographics): ?string
    {
        // Extract from demographics array if available
        if ($demographics && is_array($demographics->address)) {
            return $demographics->address['marital_status'] ?? null;
        }
        return null;
    }

    private function extractPhotos($demographics): array
    {
        // Extract from demographics array if available
        if ($demographics && is_array($demographics->address)) {
            return $demographics->address['photos'] ?? [];
        }
        return [];
    }

    private function extractHouseNumber($streetAddress): ?string
    {
        // Simple extraction - could be enhanced with regex
        if (preg_match('/^(\d+)/', $streetAddress, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractStreetNumber($streetAddress): ?string
    {
        // Simple extraction - could be enhanced with regex
        if (preg_match('/Street (\d+)/', $streetAddress, $matches)) {
            return $matches[1];
        }
        return null;
    }

    private function extractChiefComplaint(Collection $observations): ?string
    {
        $complaint = $observations->firstWhere('observationConcept.name', 'Chief Complaint');
        return $complaint?->value_string;
    }

    private function extractVitalSign(Collection $observations, string $vitalName): ?float
    {
        $vital = $observations->firstWhere('observationConcept.name', $vitalName);
        return $vital?->value_number;
    }

    private function parseObservationValue($observation)
    {
        if ($observation->value_string) {
            // Try to parse as JSON array
            $decoded = json_decode($observation->value_string, true);
            return $decoded ?? $observation->value_string;
        }
        
        return $observation->value_number ?? $observation->value_datetime;
    }

    private function determineValueType($observation): string
    {
        if ($observation->value_number !== null) {
            return is_int($observation->value_number) ? 'integer' : 'float';
        }
        
        if ($observation->value_datetime !== null) {
            return 'datetime';
        }
        
        if ($observation->value_string !== null) {
            $decoded = json_decode($observation->value_string, true);
            return $decoded ? 'complex' : 'text';
        }
        
        return 'text';
    }

    // Additional helper methods for enhanced export
    private function extractRecordedBy($encounter): ?string
    {
        // This would typically come from the encounter's created_by or a related user
        // For now, return null as the schema doesn't include this information
        return null;
    }

    private function extractRecorderTitle($encounter): ?string
    {
        // This would typically come from the user's role or title
        // For now, return null as the schema doesn't include this information
        return null;
    }

    private function extractServiceType($encounter): ?string
    {
        // This could come from the encounter's service or department
        // For now, return null as the schema doesn't include this information
        return null;
    }

    private function extractSurgeryDetails($encounter): array
    {
        // Extract surgery-specific details from observations or related data
        $observations = $encounter->observations ?? collect();
        
        return [
            'parent_code' => $encounter->visit->encounters()->first()?->ulid,
            'theater_name' => $this->extractObservationValue($observations, 'Theater Name'),
            'reason' => $this->extractObservationValue($observations, 'Surgery Reason'),
            'anesthesia_type' => $this->extractObservationValue($observations, 'Anesthesia Type'),
            'procedure_notes' => $this->extractObservationValue($observations, 'Procedure Notes'),
            'complications' => $this->extractObservationArrayValue($observations, 'Complications'),
            'specimens' => $this->extractObservationArrayValue($observations, 'Specimens'),
            'blood_loss' => $this->extractObservationValue($observations, 'Blood Loss'),
            'surgeon_name' => $this->extractObservationValue($observations, 'Surgeon Name'),
            'anesthetist_name' => $this->extractObservationValue($observations, 'Anesthetist Name'),
            'assistant_names' => $this->extractObservationArrayValue($observations, 'Assistant Names'),
        ];
    }

    private function extractObservationValue(Collection $observations, string $conceptName): ?string
    {
        $observation = $observations->firstWhere('observationConcept.name', $conceptName);
        return $observation?->value_string ?? $observation?->value_number;
    }

    private function extractObservationArrayValue(Collection $observations, string $conceptName): array
    {
        $observation = $observations->firstWhere('observationConcept.name', $conceptName);
        if (!$observation || !$observation->value_string) {
            return [];
        }
        
        $decoded = json_decode($observation->value_string, true);
        return is_array($decoded) ? $decoded : [$observation->value_string];
    }

    private function extractObservationCategory($observation): string
    {
        // Extract category from observation concept or default based on context
        return $observation->observationConcept?->category?->name ?? 'Laboratory';
    }

    private function extractObservationUnit($observation): ?string
    {
        // Extract unit from observation concept or observation data
        return $observation->observationConcept?->unit ?? null;
    }

    private function extractReferenceRange($observation): ?string
    {
        // Extract reference range from observation concept or observation data
        return $observation->observationConcept?->reference_range ?? null;
    }

    private function extractInterpretation($observation): ?string
    {
        // Extract interpretation from observation data
        return $observation->interpretation ?? null;
    }

    private function extractVerifiedBy($observation): ?string
    {
        // Extract who verified the observation
        return $observation->verified_by ?? null;
    }

    private function extractImageUrls($observation): array
    {
        // Extract image URLs from observation data
        if ($observation->value_string) {
            $decoded = json_decode($observation->value_string, true);
            if (is_array($decoded) && isset($decoded['images'])) {
                return $decoded['images'];
            }
        }
        return [];
    }

    private function extractConclusion($observation): ?string
    {
        // Extract conclusion from observation data
        if ($observation->value_string) {
            $decoded = json_decode($observation->value_string, true);
            if (is_array($decoded) && isset($decoded['conclusion'])) {
                return $decoded['conclusion'];
            }
        }
        return null;
    }
}