<?php

namespace App\Actions;

use App\Models\ClinicalFormTemplate;
use App\Models\Observation;
use App\Models\Encounter;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class GenerateObservationsAction
{
    /**
     * Generate FHIR observations from validated form data
     */
    public function execute(
        ClinicalFormTemplate $formTemplate, 
        array $validatedFormData, 
        int $encounterId, 
        int $patientId,
        ?Carbon $observedAt = null
    ): array {
        return DB::transaction(function () use ($formTemplate, $validatedFormData, $encounterId, $patientId, $observedAt) {
            $encounter = Encounter::findOrFail($encounterId);
            $observedAt = $observedAt ?? now();
            
            // Get FHIR mapping configuration
            $fhirMapping = $formTemplate->fhir_mapping;
            if (!$fhirMapping || !isset($fhirMapping['field_mappings'])) {
                throw new \Exception('No FHIR mapping configuration found for form template');
            }
            
            $observations = [];
            $fieldMappings = $fhirMapping['field_mappings'];
            $defaultValues = $fhirMapping['default_values'] ?? [];
            
            // Process each form field that has a FHIR mapping
            foreach ($validatedFormData as $fieldId => $value) {
                if (!isset($fieldMappings[$fieldId]) || $this->isEmpty($value)) {
                    continue;
                }
                
                $fieldMapping = $fieldMappings[$fieldId];
                $observation = $this->createObservationFromField(
                    $fieldId,
                    $value,
                    $fieldMapping,
                    $defaultValues,
                    $encounterId,
                    $patientId,
                    $observedAt,
                    $formTemplate
                );
                
                if ($observation) {
                    $observations[] = $observation;
                }
            }
            
            // Handle complex observations (grouped fields)
            $complexObservations = $this->createComplexObservations(
                $validatedFormData,
                $fhirMapping,
                $encounterId,
                $patientId,
                $observedAt,
                $formTemplate
            );
            
            $observations = array_merge($observations, $complexObservations);
            
            // Create parent-child relationships for grouped observations
            $this->establishObservationRelationships($observations, $fhirMapping);
            
            return [
                'observations' => $observations,
                'observations_count' => count($observations),
                'encounter_id' => $encounterId,
                'patient_id' => $patientId,
                'form_template_id' => $formTemplate->id,
                'observed_at' => $observedAt->toISOString(),
                'processing_summary' => $this->generateProcessingSummary($observations, $validatedFormData)
            ];
        });
    }
    
    /**
     * Create a single observation from a form field
     */
    private function createObservationFromField(
        string $fieldId,
        $value,
        array $fieldMapping,
        array $defaultValues,
        int $encounterId,
        int $patientId,
        Carbon $observedAt,
        ClinicalFormTemplate $formTemplate
    ): ?Observation {
        
        // Base observation data
        $observationData = [
            'encounter_id' => $encounterId,
            'patient_id' => $patientId,
            'concept_id' => $fieldMapping['observation_concept_id'],
            'observation_status_id' => $defaultValues['observation_status_id'] ?? 1,
            'body_site_id' => $fieldMapping['body_site_id'] ?? null,
            'observed_at' => $observedAt,
            'observed_by' => auth()->id(),
        ];
        
        // Add code if specified in mapping
        if (isset($fieldMapping['observation_code'])) {
            $observationData['code'] = $fieldMapping['observation_code'];
        }
        
        // Map value to appropriate field based on type
        $valueField = $fieldMapping['value_field'];
        $processedValue = $this->processValueByType($value, $valueField, $fieldMapping);
        
        if ($processedValue !== null) {
            $observationData[$valueField] = $processedValue;
            
            // Add unit information if available
            if (isset($fieldMapping['unit'])) {
                $observationData['unit'] = $fieldMapping['unit'];
            }
            
            // Add reference range if specified
            if (isset($fieldMapping['reference_range'])) {
                $observationData['reference_range'] = $fieldMapping['reference_range'];
            }
            
            return Observation::create($observationData);
        }
        
        return null;
    }
    
    /**
     * Process value based on target field type
     */
    private function processValueByType($value, string $valueField, array $fieldMapping)
    {
        switch ($valueField) {
            case 'value_string':
                return (string) $value;
                
            case 'value_number':
                if (is_numeric($value)) {
                    return (float) $value;
                }
                return null;
                
            case 'value_text':
                return (string) $value;
                
            case 'value_datetime':
                try {
                    return Carbon::parse($value);
                } catch (\Exception $e) {
                    return null;
                }
                
            case 'value_complex':
                if (is_array($value)) {
                    return $value;
                }
                
                // Handle special complex value types
                if (isset($fieldMapping['complex_type'])) {
                    return $this->processComplexValue($value, $fieldMapping['complex_type']);
                }
                
                return [$value];
                
            default:
                return $value;
        }
    }
    
    /**
     * Process complex value types (e.g., blood pressure, medication dosage)
     */
    private function processComplexValue($value, string $complexType): array
    {
        switch ($complexType) {
            case 'blood_pressure':
                if (is_string($value) && preg_match('/(\d+)\/(\d+)/', $value, $matches)) {
                    return [
                        'systolic' => (int) $matches[1],
                        'diastolic' => (int) $matches[2],
                        'unit' => 'mmHg'
                    ];
                }
                break;
                
            case 'medication_dosage':
                if (is_string($value) && preg_match('/(\d+(?:\.\d+)?)\s*(\w+)/', $value, $matches)) {
                    return [
                        'amount' => (float) $matches[1],
                        'unit' => $matches[2],
                        'original_text' => $value
                    ];
                }
                break;
                
            case 'vital_signs_set':
                if (is_array($value)) {
                    return array_map(function ($item) {
                        return [
                            'value' => $item['value'] ?? null,
                            'unit' => $item['unit'] ?? null,
                            'timestamp' => $item['timestamp'] ?? now()->toISOString()
                        ];
                    }, $value);
                }
                break;
        }
        
        return is_array($value) ? $value : [$value];
    }
    
    /**
     * Create complex observations from grouped fields
     */
    private function createComplexObservations(
        array $validatedFormData,
        array $fhirMapping,
        int $encounterId,
        int $patientId,
        Carbon $observedAt,
        ClinicalFormTemplate $formTemplate
    ): array {
        $complexObservations = [];
        
        // Handle grouped observations (e.g., vital signs panel)
        if (isset($fhirMapping['grouped_observations'])) {
            foreach ($fhirMapping['grouped_observations'] as $groupId => $groupConfig) {
                $groupObservation = $this->createGroupedObservation(
                    $groupId,
                    $groupConfig,
                    $validatedFormData,
                    $encounterId,
                    $patientId,
                    $observedAt
                );
                
                if ($groupObservation) {
                    $complexObservations[] = $groupObservation;
                }
            }
        }
        
        // Handle calculated observations (derived values)
        if (isset($fhirMapping['calculated_observations'])) {
            foreach ($fhirMapping['calculated_observations'] as $calcId => $calcConfig) {
                $calculatedObservation = $this->createCalculatedObservation(
                    $calcId,
                    $calcConfig,
                    $validatedFormData,
                    $encounterId,
                    $patientId,
                    $observedAt
                );
                
                if ($calculatedObservation) {
                    $complexObservations[] = $calculatedObservation;
                }
            }
        }
        
        return $complexObservations;
    }
    
    /**
     * Create grouped observation (e.g., vital signs panel)
     */
    private function createGroupedObservation(
        string $groupId,
        array $groupConfig,
        array $formData,
        int $encounterId,
        int $patientId,
        Carbon $observedAt
    ): ?Observation {
        
        $groupFields = $groupConfig['fields'] ?? [];
        $hasData = false;
        $groupValue = [];
        
        foreach ($groupFields as $fieldId) {
            if (isset($formData[$fieldId]) && !$this->isEmpty($formData[$fieldId])) {
                $groupValue[$fieldId] = $formData[$fieldId];
                $hasData = true;
            }
        }
        
        if (!$hasData) {
            return null;
        }
        
        return Observation::create([
            'encounter_id' => $encounterId,
            'patient_id' => $patientId,
            'concept_id' => $groupConfig['observation_concept_id'],
            'observation_status_id' => $groupConfig['observation_status_id'] ?? 1,
            'code' => $groupConfig['observation_code'] ?? $groupId,
            'value_complex' => $groupValue,
            'observed_at' => $observedAt,
            'observed_by' => auth()->id(),
        ]);
    }
    
    /**
     * Create calculated observation (e.g., BMI from height and weight)
     */
    private function createCalculatedObservation(
        string $calcId,
        array $calcConfig,
        array $formData,
        int $encounterId,
        int $patientId,
        Carbon $observedAt
    ): ?Observation {
        
        $requiredFields = $calcConfig['required_fields'] ?? [];
        $calculation = $calcConfig['calculation'] ?? null;
        
        // Check if all required fields are present
        foreach ($requiredFields as $fieldId) {
            if (!isset($formData[$fieldId]) || $this->isEmpty($formData[$fieldId])) {
                return null;
            }
        }
        
        // Perform calculation
        $calculatedValue = $this->performCalculation($calculation, $formData, $calcConfig);
        
        if ($calculatedValue === null) {
            return null;
        }
        
        return Observation::create([
            'encounter_id' => $encounterId,
            'patient_id' => $patientId,
            'concept_id' => $calcConfig['observation_concept_id'],
            'observation_status_id' => $calcConfig['observation_status_id'] ?? 1,
            'code' => $calcConfig['observation_code'] ?? $calcId,
            'value_number' => $calculatedValue,
            'observed_at' => $observedAt,
            'observed_by' => auth()->id(),
        ]);
    }
    
    /**
     * Perform calculation for derived observations
     */
    private function performCalculation(string $calculation, array $formData, array $config): ?float
    {
        switch ($calculation) {
            case 'bmi':
                $height = $formData['height'] ?? null;
                $weight = $formData['weight'] ?? null;
                
                if ($height && $weight && is_numeric($height) && is_numeric($weight)) {
                    $heightInMeters = (float) $height / 100; // Convert cm to meters
                    return round((float) $weight / ($heightInMeters * $heightInMeters), 2);
                }
                break;
                
            case 'mean_arterial_pressure':
                $systolic = $formData['systolic_bp'] ?? null;
                $diastolic = $formData['diastolic_bp'] ?? null;
                
                if ($systolic && $diastolic && is_numeric($systolic) && is_numeric($diastolic)) {
                    return round(((float) $diastolic * 2 + (float) $systolic) / 3, 1);
                }
                break;
                
            case 'pulse_pressure':
                $systolic = $formData['systolic_bp'] ?? null;
                $diastolic = $formData['diastolic_bp'] ?? null;
                
                if ($systolic && $diastolic && is_numeric($systolic) && is_numeric($diastolic)) {
                    return (float) $systolic - (float) $diastolic;
                }
                break;
        }
        
        return null;
    }
    
    /**
     * Establish parent-child relationships between observations
     */
    private function establishObservationRelationships(array $observations, array $fhirMapping): void
    {
        if (!isset($fhirMapping['observation_relationships'])) {
            return;
        }
        
        $relationships = $fhirMapping['observation_relationships'];
        
        foreach ($relationships as $relationship) {
            $parentCode = $relationship['parent_code'] ?? null;
            $childCodes = $relationship['child_codes'] ?? [];
            
            if (!$parentCode || empty($childCodes)) {
                continue;
            }
            
            // Find parent observation
            $parentObservation = collect($observations)->first(function ($obs) use ($parentCode) {
                return $obs->code === $parentCode;
            });
            
            if (!$parentObservation) {
                continue;
            }
            
            // Update child observations
            foreach ($observations as $observation) {
                if (in_array($observation->code, $childCodes)) {
                    $observation->update(['parent_id' => $parentObservation->id]);
                }
            }
        }
    }
    
    /**
     * Generate processing summary
     */
    private function generateProcessingSummary(array $observations, array $formData): array
    {
        $summary = [
            'total_form_fields' => count($formData),
            'observations_created' => count($observations),
            'observation_types' => [],
            'value_types' => [],
            'has_complex_values' => false,
            'has_calculated_values' => false,
        ];
        
        foreach ($observations as $observation) {
            // Count observation types
            if ($observation->code) {
                $summary['observation_types'][$observation->code] = 
                    ($summary['observation_types'][$observation->code] ?? 0) + 1;
            }
            
            // Count value types
            $valueType = $observation->value_type;
            $summary['value_types'][$valueType] = 
                ($summary['value_types'][$valueType] ?? 0) + 1;
            
            // Check for complex values
            if ($valueType === 'complex') {
                $summary['has_complex_values'] = true;
            }
        }
        
        return $summary;
    }
    
    /**
     * Check if a value is empty (but allow 0 and false)
     */
    private function isEmpty($value): bool
    {
        return $value === null || $value === '' || (is_array($value) && empty($value));
    }
}