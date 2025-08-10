<?php

namespace Database\Factories;

use App\Models\ClinicalFormTemplate;
use Illuminate\Database\Eloquent\Factories\Factory;

class ClinicalFormTemplateFactory extends Factory
{
    protected $model = ClinicalFormTemplate::class;

    public function definition(): array
    {
        return [
            'name' => $this->faker->slug(2),
            'title' => $this->faker->sentence(3),
            'description' => $this->faker->paragraph(),
            'category' => $this->faker->randomElement(['vital_signs', 'physical_exam', 'medical_history', 'assessment']),
            'fhir_observation_category' => ['vital-signs'],
            'form_schema' => [
                'version' => '1.0',
                'sections' => [
                    [
                        'id' => 'basic_info',
                        'title' => 'Basic Information',
                        'fields' => [
                            [
                                'id' => 'test_field',
                                'type' => 'text_field',
                                'label' => 'Test Field',
                                'required' => false,
                            ]
                        ]
                    ]
                ]
            ],
            'fhir_mapping' => [
                'field_mappings' => [
                    'test_field' => [
                        'observation_concept_id' => 1,
                        'value_field' => 'value_string'
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1
                ]
            ],
            'active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'active' => false,
        ]);
    }

    public function vitalSigns(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => 'vital_signs_form',
            'title' => 'Vital Signs Assessment',
            'category' => 'vital_signs',
            'fhir_observation_category' => ['vital-signs'],
            'form_schema' => [
                'version' => '1.0',
                'sections' => [
                    [
                        'id' => 'vitals',
                        'title' => 'Vital Signs',
                        'fields' => [
                            [
                                'id' => 'temperature',
                                'type' => 'number_field',
                                'label' => 'Temperature (°C)',
                                'required' => true,
                                'min_value' => 35.0,
                                'max_value' => 45.0
                            ],
                            [
                                'id' => 'blood_pressure_systolic',
                                'type' => 'number_field',
                                'label' => 'Systolic BP',
                                'required' => true,
                                'min_value' => 60,
                                'max_value' => 250
                            ]
                        ]
                    ]
                ]
            ],
            'fhir_mapping' => [
                'field_mappings' => [
                    'temperature' => [
                        'observation_concept_id' => 1,
                        'value_field' => 'value_number'
                    ],
                    'blood_pressure_systolic' => [
                        'observation_concept_id' => 2,
                        'value_field' => 'value_number'
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1
                ]
            ]
        ]);
    }
}