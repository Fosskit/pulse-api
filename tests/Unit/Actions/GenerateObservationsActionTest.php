<?php

namespace Tests\Unit\Actions;

use App\Actions\GenerateObservationsAction;
use App\Models\ClinicalFormTemplate;
use App\Models\Encounter;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Observation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Carbon\Carbon;

class GenerateObservationsActionTest extends TestCase
{
    use RefreshDatabase;

    private GenerateObservationsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GenerateObservationsAction();
    }

    public function test_generates_observations_from_form_data()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $formTemplate = ClinicalFormTemplate::factory()->create([
            'fhir_mapping' => [
                'field_mappings' => [
                    'temperature' => [
                        'observation_concept_id' => 1,
                        'value_field' => 'value_number',
                        'observation_code' => 'TEMP',
                        'unit' => '°C'
                    ],
                    'pulse' => [
                        'observation_concept_id' => 2,
                        'value_field' => 'value_number',
                        'observation_code' => 'PULSE',
                        'unit' => 'bpm'
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1
                ]
            ]
        ]);

        $formData = [
            'temperature' => 37.5,
            'pulse' => 80,
            'empty_field' => null
        ];

        $result = $this->action->execute(
            $formTemplate,
            $formData,
            $encounter->id,
            $patient->id
        );

        $this->assertEquals(2, $result['observations_count']);
        $this->assertEquals($encounter->id, $result['encounter_id']);
        $this->assertEquals($patient->id, $result['patient_id']);
        $this->assertEquals($formTemplate->id, $result['form_template_id']);

        // Check that observations were created in database
        $this->assertEquals(2, Observation::count());
        
        $tempObservation = Observation::where('code', 'TEMP')->first();
        $this->assertNotNull($tempObservation);
        $this->assertEquals(37.5, $tempObservation->value_number);
        $this->assertEquals($encounter->id, $tempObservation->encounter_id);
        $this->assertEquals($patient->id, $tempObservation->patient_id);
    }

    public function test_processes_different_value_types()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $formTemplate = ClinicalFormTemplate::factory()->create([
            'fhir_mapping' => [
                'field_mappings' => [
                    'name' => [
                        'observation_concept_id' => 1,
                        'value_field' => 'value_string',
                        'observation_code' => 'NAME'
                    ],
                    'weight' => [
                        'observation_concept_id' => 2,
                        'value_field' => 'value_number',
                        'observation_code' => 'WEIGHT'
                    ],
                    'notes' => [
                        'observation_concept_id' => 3,
                        'value_field' => 'value_text',
                        'observation_code' => 'NOTES'
                    ],
                    'birth_date' => [
                        'observation_concept_id' => 4,
                        'value_field' => 'value_datetime',
                        'observation_code' => 'DOB'
                    ],
                    'allergies' => [
                        'observation_concept_id' => 5,
                        'value_field' => 'value_complex',
                        'observation_code' => 'ALLERGIES'
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1
                ]
            ]
        ]);

        $formData = [
            'name' => 'John Doe',
            'weight' => 70.5,
            'notes' => 'Patient appears healthy',
            'birth_date' => '1990-01-01',
            'allergies' => ['peanuts', 'shellfish']
        ];

        $result = $this->action->execute(
            $formTemplate,
            $formData,
            $encounter->id,
            $patient->id
        );

        $this->assertEquals(5, $result['observations_count']);

        // Check string value
        $nameObs = Observation::where('code', 'NAME')->first();
        $this->assertEquals('John Doe', $nameObs->value_string);

        // Check number value
        $weightObs = Observation::where('code', 'WEIGHT')->first();
        $this->assertEquals(70.5, $weightObs->value_number);

        // Check text value
        $notesObs = Observation::where('code', 'NOTES')->first();
        $this->assertEquals('Patient appears healthy', $notesObs->value_text);

        // Check datetime value
        $dobObs = Observation::where('code', 'DOB')->first();
        $this->assertEquals('1990-01-01 00:00:00', $dobObs->value_datetime->format('Y-m-d H:i:s'));

        // Check complex value
        $allergiesObs = Observation::where('code', 'ALLERGIES')->first();
        $this->assertEquals(['peanuts', 'shellfish'], $allergiesObs->value_complex);
    }

    public function test_processes_complex_blood_pressure_value()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $formTemplate = ClinicalFormTemplate::factory()->create([
            'fhir_mapping' => [
                'field_mappings' => [
                    'blood_pressure' => [
                        'observation_concept_id' => 1,
                        'value_field' => 'value_complex',
                        'observation_code' => 'BP',
                        'complex_type' => 'blood_pressure'
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1
                ]
            ]
        ]);

        $formData = [
            'blood_pressure' => '120/80'
        ];

        $result = $this->action->execute(
            $formTemplate,
            $formData,
            $encounter->id,
            $patient->id
        );

        $bpObs = Observation::where('code', 'BP')->first();
        $this->assertEquals([
            'systolic' => 120,
            'diastolic' => 80,
            'unit' => 'mmHg'
        ], $bpObs->value_complex);
    }

    public function test_handles_grouped_observations()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $formTemplate = ClinicalFormTemplate::factory()->create([
            'fhir_mapping' => [
                'field_mappings' => [
                    'temperature' => [
                        'observation_concept_id' => 1,
                        'value_field' => 'value_number',
                        'observation_code' => 'TEMP'
                    ],
                    'pulse' => [
                        'observation_concept_id' => 2,
                        'value_field' => 'value_number',
                        'observation_code' => 'PULSE'
                    ]
                ],
                'grouped_observations' => [
                    'vital_signs_panel' => [
                        'observation_concept_id' => 10,
                        'observation_code' => 'VITALS_PANEL',
                        'fields' => ['temperature', 'pulse']
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1
                ]
            ]
        ]);

        $formData = [
            'temperature' => 37.5,
            'pulse' => 80
        ];

        $result = $this->action->execute(
            $formTemplate,
            $formData,
            $encounter->id,
            $patient->id
        );

        // Should create individual observations plus grouped observation
        $this->assertEquals(3, $result['observations_count']);

        $vitalsPanelObs = Observation::where('code', 'VITALS_PANEL')->first();
        $this->assertNotNull($vitalsPanelObs);
        $this->assertEquals([
            'temperature' => 37.5,
            'pulse' => 80
        ], $vitalsPanelObs->value_complex);
    }

    public function test_calculates_bmi_observation()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $formTemplate = ClinicalFormTemplate::factory()->create([
            'fhir_mapping' => [
                'field_mappings' => [
                    'height' => [
                        'observation_concept_id' => 1,
                        'value_field' => 'value_number',
                        'observation_code' => 'HEIGHT'
                    ],
                    'weight' => [
                        'observation_concept_id' => 2,
                        'value_field' => 'value_number',
                        'observation_code' => 'WEIGHT'
                    ]
                ],
                'calculated_observations' => [
                    'bmi' => [
                        'observation_concept_id' => 10,
                        'observation_code' => 'BMI',
                        'calculation' => 'bmi',
                        'required_fields' => ['height', 'weight']
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1
                ]
            ]
        ]);

        $formData = [
            'height' => 175, // cm
            'weight' => 70   // kg
        ];

        $result = $this->action->execute(
            $formTemplate,
            $formData,
            $encounter->id,
            $patient->id
        );

        // Should create height, weight, and BMI observations
        $this->assertEquals(3, $result['observations_count']);

        $bmiObs = Observation::where('code', 'BMI')->first();
        $this->assertNotNull($bmiObs);
        $this->assertEquals(22.86, $bmiObs->value_number); // 70 / (1.75^2) = 22.86
    }

    public function test_skips_empty_values()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $formTemplate = ClinicalFormTemplate::factory()->create([
            'fhir_mapping' => [
                'field_mappings' => [
                    'temperature' => [
                        'observation_concept_id' => 1,
                        'value_field' => 'value_number',
                        'observation_code' => 'TEMP'
                    ],
                    'notes' => [
                        'observation_concept_id' => 2,
                        'value_field' => 'value_text',
                        'observation_code' => 'NOTES'
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1
                ]
            ]
        ]);

        $formData = [
            'temperature' => 37.5,
            'notes' => '',
            'empty_field' => null,
            'empty_array' => []
        ];

        $result = $this->action->execute(
            $formTemplate,
            $formData,
            $encounter->id,
            $patient->id
        );

        // Should only create observation for temperature (non-empty value)
        $this->assertEquals(1, $result['observations_count']);
        $this->assertEquals(1, Observation::count());
    }

    public function test_includes_processing_summary()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);

        $formTemplate = ClinicalFormTemplate::factory()->create([
            'fhir_mapping' => [
                'field_mappings' => [
                    'temperature' => [
                        'observation_concept_id' => 1,
                        'value_field' => 'value_number',
                        'observation_code' => 'TEMP'
                    ],
                    'notes' => [
                        'observation_concept_id' => 2,
                        'value_field' => 'value_complex',
                        'observation_code' => 'NOTES'
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1
                ]
            ]
        ]);

        $formData = [
            'temperature' => 37.5,
            'notes' => ['note1', 'note2']
        ];

        $result = $this->action->execute(
            $formTemplate,
            $formData,
            $encounter->id,
            $patient->id
        );

        $summary = $result['processing_summary'];
        $this->assertEquals(2, $summary['total_form_fields']);
        $this->assertEquals(2, $summary['observations_created']);
        $this->assertArrayHasKey('observation_types', $summary);
        $this->assertArrayHasKey('value_types', $summary);
        $this->assertTrue($summary['has_complex_values']);
        $this->assertEquals(1, $summary['observation_types']['TEMP']);
        $this->assertEquals(1, $summary['observation_types']['NOTES']);
        $this->assertEquals(1, $summary['value_types']['number']);
        $this->assertEquals(1, $summary['value_types']['complex']);
    }
}