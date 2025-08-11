<?php

namespace App\Actions;

use App\Models\ServiceRequest;
use App\Models\LaboratoryRequest;
use App\Models\ImagingRequest;
use App\Models\Procedure;
use App\Models\Visit;
use App\Models\Encounter;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CreateServiceRequestAction
{
    public function execute(array $data): ServiceRequest
    {
        return DB::transaction(function () use ($data) {
            // Validate visit and encounter exist
            $visit = Visit::findOrFail($data['visit_id']);
            $encounter = Encounter::findOrFail($data['encounter_id']);

            // Ensure encounter belongs to the visit
            if ($encounter->visit_id !== $visit->id) {
                throw new InvalidArgumentException('Encounter does not belong to the specified visit');
            }

            // Create the main service request
            $serviceRequest = ServiceRequest::create([
                'visit_id' => $data['visit_id'],
                'encounter_id' => $data['encounter_id'],
                'service_id' => $data['service_id'] ?? null,
                'request_type' => $data['request_type'],
                'status_id' => $data['status_id'],
                'ordered_at' => $data['ordered_at'] ?? now(),
                'scheduled_at' => $data['scheduled_at'] ?? null,
                'scheduled_for' => $data['scheduled_for'] ?? null,
            ]);

            // Create specific request type record
            switch ($data['request_type']) {
                case 'Laboratory':
                    $this->createLaboratoryRequest($serviceRequest, $data);
                    break;
                case 'Imaging':
                    $this->createImagingRequest($serviceRequest, $data);
                    break;
                case 'Procedure':
                    $this->createProcedureRequest($serviceRequest, $data);
                    break;
                default:
                    throw new InvalidArgumentException('Invalid request type: ' . $data['request_type']);
            }

            return $serviceRequest->load([
                'visit',
                'encounter',
                'service',
                'status',
                'laboratoryRequest.testConcept',
                'laboratoryRequest.specimenTypeConcept',
                'imagingRequest.modalityConcept',
                'imagingRequest.bodySiteConcept'
            ]);
        });
    }

    private function createLaboratoryRequest(ServiceRequest $serviceRequest, array $data): void
    {
        if (!isset($data['laboratory_data'])) {
            throw new InvalidArgumentException('Laboratory data is required for laboratory requests');
        }

        $labData = $data['laboratory_data'];

        LaboratoryRequest::create([
            'service_request_id' => $serviceRequest->id,
            'test_concept_id' => $labData['test_concept_id'],
            'specimen_type_concept_id' => $labData['specimen_type_concept_id'],
            'reason_for_study' => $labData['reason_for_study'] ?? null,
        ]);
    }

    private function createImagingRequest(ServiceRequest $serviceRequest, array $data): void
    {
        if (!isset($data['imaging_data'])) {
            throw new InvalidArgumentException('Imaging data is required for imaging requests');
        }

        $imagingData = $data['imaging_data'];

        ImagingRequest::create([
            'service_request_id' => $serviceRequest->id,
            'modality_concept_id' => $imagingData['modality_concept_id'],
            'body_site_concept_id' => $imagingData['body_site_concept_id'],
            'reason_for_study' => $imagingData['reason_for_study'] ?? null,
        ]);
    }

    private function createProcedureRequest(ServiceRequest $serviceRequest, array $data): void
    {
        if (!isset($data['procedure_data'])) {
            throw new InvalidArgumentException('Procedure data is required for procedure requests');
        }

        $procedureData = $data['procedure_data'];

        // Create a default concept if outcome_id or body_site_id are not provided
        $defaultConcept = \App\Models\Concept::first();
        if (!$defaultConcept) {
            $defaultConcept = \App\Models\Concept::factory()->create();
        }

        Procedure::create([
            'patient_id' => $serviceRequest->visit->patient_id,
            'encounter_id' => $serviceRequest->encounter_id,
            'procedure_concept_id' => $procedureData['procedure_concept_id'],
            'outcome_id' => $procedureData['outcome_id'] ?? $defaultConcept->id,
            'body_site_id' => $procedureData['body_site_id'] ?? $defaultConcept->id,
        ]);
    }
}