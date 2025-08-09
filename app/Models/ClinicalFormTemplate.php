<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class ClinicalFormTemplate extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'title',
        'description',
        'category',
        'fhir_observation_category',
        'form_schema',
        'fhir_mapping',
        'active',
    ];

    protected $casts = [
        'fhir_observation_category' => 'array',
        'form_schema' => 'array',
        'fhir_mapping' => 'array',
        'active' => 'boolean',
    ];

    public function encounters(): HasMany
    {
        return $this->hasMany(Encounter::class);
    }

    public function scopeActive($query)
    {
        return $query->where('active', true);
    }

    public function scopeByCategory($query, $category)
    {
        return $query->where('category', $category);
    }

    /**
     * Generate observations from form data
     */
    public function generateObservations(array $formData, int $encounterId, int $patientId): array
    {
        $observations = [];
        $mapping = $this->fhir_mapping;

        foreach ($formData as $fieldId => $value) {
            if (isset($mapping['field_mappings'][$fieldId]) && !empty($value)) {
                $fieldMapping = $mapping['field_mappings'][$fieldId];

                $observation = [
                    'encounter_id' => $encounterId,
                    'patient_id' => $patientId,
                    'observation_concept_id' => $fieldMapping['observation_concept_id'],
                    'observation_status_id' => $mapping['default_values']['observation_status_id'] ?? 1,
                    'body_site_id' => $fieldMapping['body_site_id'] ?? null,
                    'observed_at' => now(),
                ];

                // Map value to appropriate field based on type
                switch ($fieldMapping['value_field']) {
                    case 'value_string':
                        $observation['value_string'] = (string) $value;
                        break;
                    case 'value_number':
                        $observation['value_number'] = (float) $value;
                        break;
                    case 'value_text':
                        $observation['value_text'] = (string) $value;
                        break;
                    case 'value_datetime':
                        $observation['value_datetime'] = $value;
                        break;
                    case 'value_complex':
                        $observation['value_complex'] = is_array($value) ? $value : [$value];
                        break;
                }

                $observations[] = $observation;
            }
        }

        return $observations;
    }

    /**
     * Get form fields for validation
     */
    public function getValidationRules(): array
    {
        $rules = [];
        $schema = $this->form_schema;

        if (isset($schema['sections'])) {
            foreach ($schema['sections'] as $section) {
                if (isset($section['fields'])) {
                    foreach ($section['fields'] as $field) {
                        $fieldRules = [];

                        if ($field['required'] ?? false) {
                            $fieldRules[] = 'required';
                        }

                        switch ($field['type']) {
                            case 'number_field':
                                $fieldRules[] = 'numeric';
                                if (isset($field['min_value'])) {
                                    $fieldRules[] = 'min:' . $field['min_value'];
                                }
                                if (isset($field['max_value'])) {
                                    $fieldRules[] = 'max:' . $field['max_value'];
                                }
                                break;
                            case 'email':
                                $fieldRules[] = 'email';
                                break;
                            case 'date_field':
                                $fieldRules[] = 'date';
                                break;
                            case 'text_field':
                                if (isset($field['max_length'])) {
                                    $fieldRules[] = 'max:' . $field['max_length'];
                                }
                                break;
                        }

                        if (!empty($fieldRules)) {
                            $rules[$field['id']] = $fieldRules;
                        }
                    }
                }
            }
        }

        return $rules;
    }
}
