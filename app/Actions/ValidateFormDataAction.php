<?php

namespace App\Actions;

use App\Models\ClinicalFormTemplate;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class ValidateFormDataAction
{
    /**
     * Validate form data against clinical form template schema
     */
    public function execute(ClinicalFormTemplate $formTemplate, array $formData): array
    {
        // Get validation rules from form schema
        $validationRules = $formTemplate->getValidationRules();
        
        // Add custom validation messages based on form schema
        $customMessages = $this->getCustomValidationMessages($formTemplate);
        
        // Create validator instance
        $validator = Validator::make($formData, $validationRules, $customMessages);
        
        // Add custom validation logic for complex field types
        $this->addCustomValidationRules($validator, $formTemplate, $formData);
        
        // Validate the data
        if ($validator->fails()) {
            throw new ValidationException($validator);
        }
        
        // Return validated and sanitized data
        return [
            'validated_data' => $validator->validated(),
            'validation_rules' => $validationRules,
            'field_count' => count($formData),
            'validated_field_count' => count($validator->validated()),
            'form_template_id' => $formTemplate->id,
            'form_template_name' => $formTemplate->name,
        ];
    }
    
    /**
     * Get custom validation messages from form schema
     */
    private function getCustomValidationMessages(ClinicalFormTemplate $formTemplate): array
    {
        $messages = [];
        $schema = $formTemplate->form_schema;
        
        if (isset($schema['sections'])) {
            foreach ($schema['sections'] as $section) {
                if (isset($section['fields'])) {
                    foreach ($section['fields'] as $field) {
                        $fieldId = $field['id'];
                        $fieldLabel = $field['label'] ?? $fieldId;
                        
                        // Add custom messages for common validation rules
                        $messages["{$fieldId}.required"] = "The {$fieldLabel} field is required.";
                        $messages["{$fieldId}.numeric"] = "The {$fieldLabel} must be a number.";
                        $messages["{$fieldId}.email"] = "The {$fieldLabel} must be a valid email address.";
                        $messages["{$fieldId}.date"] = "The {$fieldLabel} must be a valid date.";
                        
                        // Add range validation messages
                        if (isset($field['min_value'])) {
                            $messages["{$fieldId}.min"] = "The {$fieldLabel} must be at least {$field['min_value']}.";
                        }
                        if (isset($field['max_value'])) {
                            $messages["{$fieldId}.max"] = "The {$fieldLabel} must not exceed {$field['max_value']}.";
                        }
                        if (isset($field['max_length'])) {
                            $messages["{$fieldId}.max"] = "The {$fieldLabel} must not exceed {$field['max_length']} characters.";
                        }
                    }
                }
            }
        }
        
        return $messages;
    }
    
    /**
     * Add custom validation rules for complex field types
     */
    private function addCustomValidationRules($validator, ClinicalFormTemplate $formTemplate, array $formData): void
    {
        $schema = $formTemplate->form_schema;
        
        if (!isset($schema['sections'])) {
            return;
        }
        
        foreach ($schema['sections'] as $section) {
            if (!isset($section['fields'])) {
                continue;
            }
            
            foreach ($section['fields'] as $field) {
                $fieldId = $field['id'];
                
                // Skip if field is not in form data
                if (!array_key_exists($fieldId, $formData)) {
                    continue;
                }
                
                $value = $formData[$fieldId];
                
                // Add custom validation based on field type
                switch ($field['type']) {
                    case 'select_field':
                        if (isset($field['options']) && !empty($value)) {
                            $validOptions = array_column($field['options'], 'value');
                            $validator->sometimes($fieldId, 'in:' . implode(',', $validOptions), function () {
                                return true;
                            });
                        }
                        break;
                        
                    case 'multi_select_field':
                        if (isset($field['options']) && is_array($value)) {
                            $validOptions = array_column($field['options'], 'value');
                            $validator->sometimes($fieldId, 'array', function () {
                                return true;
                            });
                            $validator->sometimes($fieldId . '.*', 'in:' . implode(',', $validOptions), function () {
                                return true;
                            });
                        }
                        break;
                        
                    case 'checkbox_field':
                        $validator->sometimes($fieldId, 'boolean', function () {
                            return true;
                        });
                        break;
                        
                    case 'date_range_field':
                        if (is_array($value)) {
                            $validator->sometimes($fieldId . '.start_date', 'required|date', function () {
                                return true;
                            });
                            $validator->sometimes($fieldId . '.end_date', 'required|date|after_or_equal:' . $fieldId . '.start_date', function () {
                                return true;
                            });
                        }
                        break;
                        
                    case 'time_field':
                        $validator->sometimes($fieldId, 'date_format:H:i', function () {
                            return true;
                        });
                        break;
                        
                    case 'url_field':
                        $validator->sometimes($fieldId, 'url', function () {
                            return true;
                        });
                        break;
                }
                
                // Add conditional validation based on field dependencies
                if (isset($field['depends_on'])) {
                    $dependsOn = $field['depends_on'];
                    if (isset($formData[$dependsOn['field']]) && 
                        $formData[$dependsOn['field']] === $dependsOn['value']) {
                        
                        if ($field['required'] ?? false) {
                            $validator->sometimes($fieldId, 'required', function () {
                                return true;
                            });
                        }
                    }
                }
            }
        }
    }
    
    /**
     * Validate specific field types with custom logic
     */
    public function validateFieldType(string $fieldType, $value, array $fieldConfig): bool
    {
        switch ($fieldType) {
            case 'vital_signs':
                return $this->validateVitalSigns($value, $fieldConfig);
                
            case 'medication_dosage':
                return $this->validateMedicationDosage($value, $fieldConfig);
                
            case 'clinical_scale':
                return $this->validateClinicalScale($value, $fieldConfig);
                
            default:
                return true;
        }
    }
    
    /**
     * Validate vital signs values
     */
    private function validateVitalSigns($value, array $fieldConfig): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        $numericValue = (float) $value;
        
        // Define normal ranges for common vital signs
        $vitalRanges = [
            'temperature' => ['min' => 35.0, 'max' => 42.0],
            'heart_rate' => ['min' => 30, 'max' => 200],
            'systolic_bp' => ['min' => 70, 'max' => 250],
            'diastolic_bp' => ['min' => 40, 'max' => 150],
            'respiratory_rate' => ['min' => 8, 'max' => 40],
            'oxygen_saturation' => ['min' => 70, 'max' => 100],
        ];
        
        $vitalType = $fieldConfig['vital_type'] ?? null;
        if ($vitalType && isset($vitalRanges[$vitalType])) {
            $range = $vitalRanges[$vitalType];
            return $numericValue >= $range['min'] && $numericValue <= $range['max'];
        }
        
        return true;
    }
    
    /**
     * Validate medication dosage format
     */
    private function validateMedicationDosage($value, array $fieldConfig): bool
    {
        if (!is_string($value)) {
            return false;
        }
        
        // Basic dosage format validation (e.g., "10mg", "2.5ml", "1 tablet")
        return preg_match('/^\d+(\.\d+)?\s*(mg|ml|g|tablet|capsule|unit)s?$/i', trim($value));
    }
    
    /**
     * Validate clinical scale values (e.g., pain scale 1-10)
     */
    private function validateClinicalScale($value, array $fieldConfig): bool
    {
        if (!is_numeric($value)) {
            return false;
        }
        
        $numericValue = (int) $value;
        $minScale = $fieldConfig['min_scale'] ?? 0;
        $maxScale = $fieldConfig['max_scale'] ?? 10;
        
        return $numericValue >= $minScale && $numericValue <= $maxScale;
    }
}