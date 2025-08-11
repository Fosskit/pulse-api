<?php

namespace App\Actions;

use App\Models\ServiceRequest;
use App\Models\Observation;
use App\Models\LaboratoryRequest;
use App\Models\ImagingRequest;
use App\Models\Procedure;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class UpdateServiceResultsAction
{
    public function execute(ServiceRequest $serviceRequest, array $data): ServiceRequest
    {
        return DB::transaction(function () use ($serviceRequest, $data) {
            // Update the service request completion status
            $serviceRequest->update([
                'completed_at' => $data['completed_at'] ?? now(),
            ]);

            // Update specific request type with performance details
            $this->updateSpecificRequestType($serviceRequest, $data);

            // Create observations from results if provided
            if (isset($data['results']) && is_array($data['results'])) {
                $this->createObservationsFromResults($serviceRequest, $data['results']);
            }

            return $serviceRequest->load([
                'visit',
                'encounter',
                'service',
                'status',
                'laboratoryRequest.testConcept',
                'laboratoryRequest.specimenTypeConcept',
                'imagingRequest.modalityConcept',
                'imagingRequest.bodySiteConcept',
                'observations',
            ]);
        });
    }

    private function updateSpecificRequestType(ServiceRequest $serviceRequest, array $data): void
    {
        switch ($serviceRequest->request_type) {
            case 'Laboratory':
                $this->updateLaboratoryRequest($serviceRequest, $data);
                break;
            case 'Imaging':
                $this->updateImagingRequest($serviceRequest, $data);
                break;
            case 'Procedure':
                $this->updateProcedureRequest($serviceRequest, $data);
                break;
        }
    }

    private function updateLaboratoryRequest(ServiceRequest $serviceRequest, array $data): void
    {
        $laboratoryRequest = $serviceRequest->laboratoryRequest;
        
        if (!$laboratoryRequest) {
            throw new InvalidArgumentException('Laboratory request not found for this service request');
        }

        $laboratoryRequest->update([
            'performed_at' => $data['performed_at'] ?? now(),
            'performed_by' => $data['performed_by'] ?? null,
        ]);
    }

    private function updateImagingRequest(ServiceRequest $serviceRequest, array $data): void
    {
        $imagingRequest = $serviceRequest->imagingRequest;
        
        if (!$imagingRequest) {
            throw new InvalidArgumentException('Imaging request not found for this service request');
        }

        $imagingRequest->update([
            'performed_at' => $data['performed_at'] ?? now(),
            'performed_by' => $data['performed_by'] ?? null,
        ]);
    }

    private function updateProcedureRequest(ServiceRequest $serviceRequest, array $data): void
    {
        // For procedures, we need to find the procedure record by encounter_id
        $procedure = Procedure::where('encounter_id', $serviceRequest->encounter_id)
            ->where('patient_id', $serviceRequest->visit->patient_id)
            ->first();
        
        if (!$procedure) {
            throw new InvalidArgumentException('Procedure record not found for this service request');
        }

        $procedure->update([
            'performed_at' => $data['performed_at'] ?? now(),
            'performed_by' => $data['performed_by'] ?? null,
            'outcome_id' => $data['outcome_id'] ?? $procedure->outcome_id,
        ]);
    }

    private function createObservationsFromResults(ServiceRequest $serviceRequest, array $results): void
    {
        foreach ($results as $result) {
            $observationData = [
                'patient_id' => $serviceRequest->visit->patient_id,
                'encounter_id' => $serviceRequest->encounter_id,
                'service_request_id' => $serviceRequest->id,
                'observation_status_id' => $result['observation_status_id'] ?? 1,
                'concept_id' => $result['concept_id'],
                'code' => $result['code'] ?? null,
                'body_site_id' => $result['body_site_id'] ?? null,
                'value_id' => $result['value_id'] ?? null,
                'value_string' => $result['value_string'] ?? null,
                'value_number' => $result['value_number'] ?? null,
                'value_datetime' => $result['value_datetime'] ?? null,
                'value_boolean' => $result['value_boolean'] ?? null,
                'reference_range_low' => $result['reference_range_low'] ?? null,
                'reference_range_high' => $result['reference_range_high'] ?? null,
                'reference_range_text' => $result['reference_range_text'] ?? null,
                'interpretation' => $result['interpretation'] ?? null,
                'comments' => $result['comments'] ?? null,
                'verified_at' => $result['verified_at'] ?? null,
                'verified_by' => $result['verified_by'] ?? null,
            ];

            Observation::create($observationData);
        }
    }
}