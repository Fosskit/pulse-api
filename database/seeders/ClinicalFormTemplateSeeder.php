<?php

namespace Database\Seeders;

use App\Models\ClinicalFormTemplate;
use Illuminate\Database\Seeder;

class ClinicalFormTemplateSeeder extends Seeder
{
    /**
     * Run the database seeders.
     */
    public function run(): void
    {
        $this->createVitalSignsForm();
        $this->createPhysicalExaminationForm();
        $this->createMedicalHistoryForm();
    }

    private function createVitalSignsForm(): void
    {
        ClinicalFormTemplate::create([
            'name' => 'vital-signs-basic',
            'title' => 'Basic Vital Signs Assessment',
            'description' => 'Standard vital signs measurement form including temperature, blood pressure, pulse, respiratory rate, oxygen saturation, height, weight, and BMI calculation.',
            'category' => 'vital-signs',
            'fhir_observation_category' => [
                'http://terminology.hl7.org/CodeSystem/observation-category' => 'vital-signs'
            ],
            'form_schema' => [
                'version' => '1.0',
                'sections' => [
                    [
                        'id' => 'basic_vitals',
                        'title' => 'Basic Vital Signs',
                        'description' => 'Core vital signs measurements',
                        'fields' => [
                            [
                                'id' => 'temperature',
                                'type' => 'number_field',
                                'label' => 'Body Temperature',
                                'required' => true,
                                'min_value' => 35.0,
                                'max_value' => 45.0,
                                'unit' => '°C',
                                'help_text' => 'Normal range: 36.1-37.2°C'
                            ],
                            [
                                'id' => 'systolic_bp',
                                'type' => 'number_field',
                                'label' => 'Systolic Blood Pressure',
                                'required' => true,
                                'min_value' => 60,
                                'max_value' => 250,
                                'unit' => 'mmHg',
                                'help_text' => 'Normal range: 90-140 mmHg'
                            ],
                            [
                                'id' => 'diastolic_bp',
                                'type' => 'number_field',
                                'label' => 'Diastolic Blood Pressure',
                                'required' => true,
                                'min_value' => 40,
                                'max_value' => 150,
                                'unit' => 'mmHg',
                                'help_text' => 'Normal range: 60-90 mmHg'
                            ],
                            [
                                'id' => 'heart_rate',
                                'type' => 'number_field',
                                'label' => 'Heart Rate (Pulse)',
                                'required' => true,
                                'min_value' => 30,
                                'max_value' => 200,
                                'unit' => 'bpm',
                                'help_text' => 'Normal range: 60-100 bpm'
                            ],
                            [
                                'id' => 'respiratory_rate',
                                'type' => 'number_field',
                                'label' => 'Respiratory Rate',
                                'required' => true,
                                'min_value' => 8,
                                'max_value' => 40,
                                'unit' => 'breaths/min',
                                'help_text' => 'Normal range: 12-20 breaths/min'
                            ],
                            [
                                'id' => 'oxygen_saturation',
                                'type' => 'number_field',
                                'label' => 'Oxygen Saturation (SpO2)',
                                'required' => false,
                                'min_value' => 70,
                                'max_value' => 100,
                                'unit' => '%',
                                'help_text' => 'Normal range: 95-100%'
                            ]
                        ]
                    ],
                    [
                        'id' => 'anthropometric',
                        'title' => 'Anthropometric Measurements',
                        'description' => 'Height, weight, and BMI measurements',
                        'fields' => [
                            [
                                'id' => 'height',
                                'type' => 'number_field',
                                'label' => 'Height',
                                'required' => false,
                                'min_value' => 30,
                                'max_value' => 250,
                                'unit' => 'cm',
                                'help_text' => 'Patient height in centimeters'
                            ],
                            [
                                'id' => 'weight',
                                'type' => 'number_field',
                                'label' => 'Weight',
                                'required' => false,
                                'min_value' => 1,
                                'max_value' => 300,
                                'unit' => 'kg',
                                'help_text' => 'Patient weight in kilograms'
                            ],
                            [
                                'id' => 'pain_scale',
                                'type' => 'select_field',
                                'label' => 'Pain Scale (0-10)',
                                'required' => false,
                                'options' => [
                                    '0' => '0 - No Pain',
                                    '1' => '1 - Minimal Pain',
                                    '2' => '2 - Mild Pain',
                                    '3' => '3 - Uncomfortable',
                                    '4' => '4 - Moderate Pain',
                                    '5' => '5 - Moderate Pain',
                                    '6' => '6 - Moderately Strong',
                                    '7' => '7 - Strong Pain',
                                    '8' => '8 - Very Strong Pain',
                                    '9' => '9 - Severe Pain',
                                    '10' => '10 - Unbearable Pain'
                                ],
                                'help_text' => 'Patient-reported pain level'
                            ]
                        ]
                    ],
                    [
                        'id' => 'additional_notes',
                        'title' => 'Additional Information',
                        'description' => 'Additional observations and notes',
                        'fields' => [
                            [
                                'id' => 'measurement_notes',
                                'type' => 'textarea_field',
                                'label' => 'Measurement Notes',
                                'required' => false,
                                'rows' => 3,
                                'help_text' => 'Any additional notes about the vital signs measurements'
                            ]
                        ]
                    ]
                ]
            ],
            'fhir_mapping' => [
                'field_mappings' => [
                    'temperature' => [
                        'observation_concept_id' => 1,
                        'value_field' => 'value_number',
                        'unit' => 'Cel',
                        'body_site_id' => null
                    ],
                    'systolic_bp' => [
                        'observation_concept_id' => 2,
                        'value_field' => 'value_number',
                        'unit' => 'mm[Hg]',
                        'body_site_id' => null
                    ],
                    'diastolic_bp' => [
                        'observation_concept_id' => 3,
                        'value_field' => 'value_number',
                        'unit' => 'mm[Hg]',
                        'body_site_id' => null
                    ],
                    'heart_rate' => [
                        'observation_concept_id' => 4,
                        'value_field' => 'value_number',
                        'unit' => '/min',
                        'body_site_id' => null
                    ],
                    'respiratory_rate' => [
                        'observation_concept_id' => 5,
                        'value_field' => 'value_number',
                        'unit' => '/min',
                        'body_site_id' => null
                    ],
                    'oxygen_saturation' => [
                        'observation_concept_id' => 6,
                        'value_field' => 'value_number',
                        'unit' => '%',
                        'body_site_id' => null
                    ],
                    'height' => [
                        'observation_concept_id' => 7,
                        'value_field' => 'value_number',
                        'unit' => 'cm',
                        'body_site_id' => null
                    ],
                    'weight' => [
                        'observation_concept_id' => 8,
                        'value_field' => 'value_number',
                        'unit' => 'kg',
                        'body_site_id' => null
                    ],
                    'pain_scale' => [
                        'observation_concept_id' => 9,
                        'value_field' => 'value_string',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'measurement_notes' => [
                        'observation_concept_id' => 10,
                        'value_field' => 'value_text',
                        'unit' => null,
                        'body_site_id' => null
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1,
                    'body_site_id' => null
                ]
            ],
            'active' => true
        ]);
    }

    private function createPhysicalExaminationForm(): void
    {
        ClinicalFormTemplate::create([
            'name' => 'physical-examination-general',
            'title' => 'General Physical Examination',
            'description' => 'Comprehensive physical examination form covering major body systems including general appearance, HEENT, cardiovascular, respiratory, abdominal, neurological, and musculoskeletal systems.',
            'category' => 'physical-exam',
            'fhir_observation_category' => [
                'http://terminology.hl7.org/CodeSystem/observation-category' => 'exam'
            ],
            'form_schema' => [
                'version' => '1.0',
                'sections' => [
                    [
                        'id' => 'general_appearance',
                        'title' => 'General Appearance',
                        'description' => 'Overall patient appearance and demeanor',
                        'fields' => [
                            [
                                'id' => 'general_condition',
                                'type' => 'select_field',
                                'label' => 'General Condition',
                                'required' => true,
                                'options' => [
                                    'well-appearing' => 'Well-appearing',
                                    'mild-distress' => 'Mild distress',
                                    'moderate-distress' => 'Moderate distress',
                                    'severe-distress' => 'Severe distress',
                                    'critical' => 'Critical condition'
                                ],
                                'help_text' => 'Overall patient appearance'
                            ],
                            [
                                'id' => 'consciousness_level',
                                'type' => 'select_field',
                                'label' => 'Level of Consciousness',
                                'required' => true,
                                'options' => [
                                    'alert' => 'Alert',
                                    'drowsy' => 'Drowsy',
                                    'lethargic' => 'Lethargic',
                                    'stuporous' => 'Stuporous',
                                    'comatose' => 'Comatose'
                                ],
                                'help_text' => 'Patient\'s level of consciousness'
                            ],
                            [
                                'id' => 'mobility',
                                'type' => 'select_field',
                                'label' => 'Mobility',
                                'required' => false,
                                'options' => [
                                    'ambulatory' => 'Ambulatory',
                                    'assisted-walking' => 'Assisted walking',
                                    'wheelchair' => 'Wheelchair bound',
                                    'bedbound' => 'Bedbound'
                                ],
                                'help_text' => 'Patient\'s mobility status'
                            ]
                        ]
                    ],
                    [
                        'id' => 'heent',
                        'title' => 'HEENT (Head, Eyes, Ears, Nose, Throat)',
                        'description' => 'Head, eyes, ears, nose, and throat examination',
                        'fields' => [
                            [
                                'id' => 'head_examination',
                                'type' => 'select_field',
                                'label' => 'Head',
                                'required' => false,
                                'options' => [
                                    'normal' => 'Normal',
                                    'abnormal' => 'Abnormal'
                                ],
                                'help_text' => 'Head examination findings'
                            ],
                            [
                                'id' => 'eyes_examination',
                                'type' => 'select_field',
                                'label' => 'Eyes',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'normal' => 'Normal',
                                    'conjunctival-injection' => 'Conjunctival injection',
                                    'scleral-icterus' => 'Scleral icterus',
                                    'pupil-abnormality' => 'Pupil abnormality',
                                    'visual-impairment' => 'Visual impairment'
                                ],
                                'help_text' => 'Eye examination findings'
                            ],
                            [
                                'id' => 'ears_examination',
                                'type' => 'select_field',
                                'label' => 'Ears',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'normal' => 'Normal',
                                    'hearing-loss' => 'Hearing loss',
                                    'ear-discharge' => 'Ear discharge',
                                    'tympanic-membrane-abnormal' => 'Tympanic membrane abnormal'
                                ],
                                'help_text' => 'Ear examination findings'
                            ],
                            [
                                'id' => 'throat_examination',
                                'type' => 'select_field',
                                'label' => 'Throat',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'normal' => 'Normal',
                                    'erythema' => 'Erythema',
                                    'tonsillar-enlargement' => 'Tonsillar enlargement',
                                    'exudate' => 'Exudate',
                                    'lymphadenopathy' => 'Lymphadenopathy'
                                ],
                                'help_text' => 'Throat examination findings'
                            ]
                        ]
                    ],
                    [
                        'id' => 'cardiovascular',
                        'title' => 'Cardiovascular System',
                        'description' => 'Heart and circulation examination',
                        'fields' => [
                            [
                                'id' => 'heart_sounds',
                                'type' => 'select_field',
                                'label' => 'Heart Sounds',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'normal-s1-s2' => 'Normal S1, S2',
                                    'murmur' => 'Murmur present',
                                    'gallop' => 'Gallop rhythm',
                                    'irregular' => 'Irregular rhythm',
                                    'extra-sounds' => 'Extra heart sounds'
                                ],
                                'help_text' => 'Heart sound findings'
                            ],
                            [
                                'id' => 'peripheral_pulses',
                                'type' => 'select_field',
                                'label' => 'Peripheral Pulses',
                                'required' => false,
                                'options' => [
                                    'normal' => 'Normal',
                                    'weak' => 'Weak',
                                    'absent' => 'Absent',
                                    'bounding' => 'Bounding'
                                ],
                                'help_text' => 'Peripheral pulse examination'
                            ],
                            [
                                'id' => 'edema',
                                'type' => 'select_field',
                                'label' => 'Edema',
                                'required' => false,
                                'options' => [
                                    'none' => 'None',
                                    'mild' => 'Mild (+1)',
                                    'moderate' => 'Moderate (+2)',
                                    'severe' => 'Severe (+3)',
                                    'very-severe' => 'Very severe (+4)'
                                ],
                                'help_text' => 'Edema assessment'
                            ]
                        ]
                    ],
                    [
                        'id' => 'respiratory',
                        'title' => 'Respiratory System',
                        'description' => 'Lung and breathing examination',
                        'fields' => [
                            [
                                'id' => 'breath_sounds',
                                'type' => 'select_field',
                                'label' => 'Breath Sounds',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'normal' => 'Normal',
                                    'wheezing' => 'Wheezing',
                                    'rales' => 'Rales/Crackles',
                                    'rhonchi' => 'Rhonchi',
                                    'diminished' => 'Diminished',
                                    'absent' => 'Absent'
                                ],
                                'help_text' => 'Lung auscultation findings'
                            ],
                            [
                                'id' => 'breathing_pattern',
                                'type' => 'select_field',
                                'label' => 'Breathing Pattern',
                                'required' => false,
                                'options' => [
                                    'normal' => 'Normal',
                                    'tachypnea' => 'Tachypnea',
                                    'bradypnea' => 'Bradypnea',
                                    'dyspnea' => 'Dyspnea',
                                    'orthopnea' => 'Orthopnea'
                                ],
                                'help_text' => 'Breathing pattern assessment'
                            ],
                            [
                                'id' => 'chest_expansion',
                                'type' => 'select_field',
                                'label' => 'Chest Expansion',
                                'required' => false,
                                'options' => [
                                    'normal' => 'Normal',
                                    'reduced' => 'Reduced',
                                    'asymmetric' => 'Asymmetric'
                                ],
                                'help_text' => 'Chest expansion assessment'
                            ]
                        ]
                    ],
                    [
                        'id' => 'examination_notes',
                        'title' => 'Additional Findings',
                        'description' => 'Additional examination notes and findings',
                        'fields' => [
                            [
                                'id' => 'additional_findings',
                                'type' => 'textarea_field',
                                'label' => 'Additional Findings',
                                'required' => false,
                                'rows' => 4,
                                'help_text' => 'Any additional examination findings not covered above'
                            ],
                            [
                                'id' => 'clinical_impression',
                                'type' => 'textarea_field',
                                'label' => 'Clinical Impression',
                                'required' => false,
                                'rows' => 3,
                                'help_text' => 'Initial clinical impression based on examination'
                            ]
                        ]
                    ]
                ]
            ],
            'fhir_mapping' => [
                'field_mappings' => [
                    'general_condition' => [
                        'observation_concept_id' => 11,
                        'value_field' => 'value_string',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'consciousness_level' => [
                        'observation_concept_id' => 12,
                        'value_field' => 'value_string',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'heart_sounds' => [
                        'observation_concept_id' => 13,
                        'value_field' => 'value_complex',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'breath_sounds' => [
                        'observation_concept_id' => 14,
                        'value_field' => 'value_complex',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'additional_findings' => [
                        'observation_concept_id' => 15,
                        'value_field' => 'value_text',
                        'unit' => null,
                        'body_site_id' => null
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1,
                    'body_site_id' => null
                ]
            ],
            'active' => true
        ]);
    }

    private function createMedicalHistoryForm(): void
    {
        ClinicalFormTemplate::create([
            'name' => 'medical-history-comprehensive',
            'title' => 'Comprehensive Medical History',
            'description' => 'Detailed medical history form including chief complaint, history of present illness, past medical history, medications, allergies, social history, and family history.',
            'category' => 'assessment',
            'fhir_observation_category' => [
                'http://terminology.hl7.org/CodeSystem/observation-category' => 'survey'
            ],
            'form_schema' => [
                'version' => '1.0',
                'sections' => [
                    [
                        'id' => 'chief_complaint',
                        'title' => 'Chief Complaint & History of Present Illness',
                        'description' => 'Primary reason for visit and current illness details',
                        'fields' => [
                            [
                                'id' => 'chief_complaint',
                                'type' => 'textarea_field',
                                'label' => 'Chief Complaint',
                                'required' => true,
                                'rows' => 2,
                                'help_text' => 'Primary reason for the patient\'s visit'
                            ],
                            [
                                'id' => 'hpi_duration',
                                'type' => 'text_field',
                                'label' => 'Duration of Symptoms',
                                'required' => false,
                                'placeholder' => 'e.g., 3 days, 2 weeks',
                                'help_text' => 'How long have symptoms been present?'
                            ],
                            [
                                'id' => 'hpi_severity',
                                'type' => 'select_field',
                                'label' => 'Symptom Severity',
                                'required' => false,
                                'options' => [
                                    'mild' => 'Mild',
                                    'moderate' => 'Moderate',
                                    'severe' => 'Severe'
                                ],
                                'help_text' => 'Severity of current symptoms'
                            ],
                            [
                                'id' => 'hpi_details',
                                'type' => 'textarea_field',
                                'label' => 'History of Present Illness',
                                'required' => false,
                                'rows' => 4,
                                'help_text' => 'Detailed description of the current illness'
                            ],
                            [
                                'id' => 'associated_symptoms',
                                'type' => 'select_field',
                                'label' => 'Associated Symptoms',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'fever' => 'Fever',
                                    'chills' => 'Chills',
                                    'nausea' => 'Nausea',
                                    'vomiting' => 'Vomiting',
                                    'headache' => 'Headache',
                                    'dizziness' => 'Dizziness',
                                    'fatigue' => 'Fatigue',
                                    'shortness-of-breath' => 'Shortness of breath',
                                    'chest-pain' => 'Chest pain',
                                    'abdominal-pain' => 'Abdominal pain'
                                ],
                                'help_text' => 'Select all associated symptoms'
                            ]
                        ]
                    ],
                    [
                        'id' => 'past_medical_history',
                        'title' => 'Past Medical History',
                        'description' => 'Previous medical conditions and surgeries',
                        'fields' => [
                            [
                                'id' => 'chronic_conditions',
                                'type' => 'select_field',
                                'label' => 'Chronic Medical Conditions',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'hypertension' => 'Hypertension',
                                    'diabetes' => 'Diabetes Mellitus',
                                    'heart-disease' => 'Heart Disease',
                                    'asthma' => 'Asthma',
                                    'copd' => 'COPD',
                                    'kidney-disease' => 'Kidney Disease',
                                    'liver-disease' => 'Liver Disease',
                                    'cancer' => 'Cancer',
                                    'depression' => 'Depression',
                                    'anxiety' => 'Anxiety',
                                    'arthritis' => 'Arthritis',
                                    'thyroid-disease' => 'Thyroid Disease'
                                ],
                                'help_text' => 'Select all known chronic conditions'
                            ],
                            [
                                'id' => 'previous_surgeries',
                                'type' => 'textarea_field',
                                'label' => 'Previous Surgeries',
                                'required' => false,
                                'rows' => 3,
                                'help_text' => 'List previous surgeries with approximate dates'
                            ],
                            [
                                'id' => 'previous_hospitalizations',
                                'type' => 'textarea_field',
                                'label' => 'Previous Hospitalizations',
                                'required' => false,
                                'rows' => 3,
                                'help_text' => 'List previous hospitalizations with reasons and dates'
                            ]
                        ]
                    ],
                    [
                        'id' => 'medications_allergies',
                        'title' => 'Medications & Allergies',
                        'description' => 'Current medications and known allergies',
                        'fields' => [
                            [
                                'id' => 'current_medications',
                                'type' => 'textarea_field',
                                'label' => 'Current Medications',
                                'required' => false,
                                'rows' => 4,
                                'help_text' => 'List all current medications including dosage and frequency'
                            ],
                            [
                                'id' => 'drug_allergies',
                                'type' => 'textarea_field',
                                'label' => 'Drug Allergies',
                                'required' => false,
                                'rows' => 3,
                                'help_text' => 'List known drug allergies and reactions'
                            ],
                            [
                                'id' => 'other_allergies',
                                'type' => 'textarea_field',
                                'label' => 'Other Allergies',
                                'required' => false,
                                'rows' => 2,
                                'help_text' => 'Food, environmental, or other allergies'
                            ],
                            [
                                'id' => 'nkda',
                                'type' => 'checkbox_field',
                                'label' => 'No Known Drug Allergies (NKDA)',
                                'required' => false,
                                'help_text' => 'Check if patient has no known drug allergies'
                            ]
                        ]
                    ],
                    [
                        'id' => 'social_history',
                        'title' => 'Social History',
                        'description' => 'Social habits and lifestyle factors',
                        'fields' => [
                            [
                                'id' => 'smoking_status',
                                'type' => 'select_field',
                                'label' => 'Smoking Status',
                                'required' => false,
                                'options' => [
                                    'never' => 'Never smoked',
                                    'current' => 'Current smoker',
                                    'former' => 'Former smoker'
                                ],
                                'help_text' => 'Patient\'s smoking history'
                            ],
                            [
                                'id' => 'alcohol_use',
                                'type' => 'select_field',
                                'label' => 'Alcohol Use',
                                'required' => false,
                                'options' => [
                                    'none' => 'None',
                                    'occasional' => 'Occasional',
                                    'moderate' => 'Moderate',
                                    'heavy' => 'Heavy'
                                ],
                                'help_text' => 'Patient\'s alcohol consumption'
                            ],
                            [
                                'id' => 'drug_use',
                                'type' => 'select_field',
                                'label' => 'Recreational Drug Use',
                                'required' => false,
                                'options' => [
                                    'none' => 'None',
                                    'past' => 'Past use',
                                    'current' => 'Current use'
                                ],
                                'help_text' => 'History of recreational drug use'
                            ],
                            [
                                'id' => 'occupation',
                                'type' => 'text_field',
                                'label' => 'Occupation',
                                'required' => false,
                                'help_text' => 'Patient\'s current occupation'
                            ],
                            [
                                'id' => 'exercise',
                                'type' => 'select_field',
                                'label' => 'Exercise Frequency',
                                'required' => false,
                                'options' => [
                                    'none' => 'None',
                                    'rarely' => 'Rarely',
                                    'weekly' => '1-2 times per week',
                                    'regular' => '3+ times per week',
                                    'daily' => 'Daily'
                                ],
                                'help_text' => 'How often does the patient exercise?'
                            ]
                        ]
                    ],
                    [
                        'id' => 'family_history',
                        'title' => 'Family History',
                        'description' => 'Family medical history',
                        'fields' => [
                            [
                                'id' => 'family_conditions',
                                'type' => 'select_field',
                                'label' => 'Family History of Medical Conditions',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'heart-disease' => 'Heart Disease',
                                    'diabetes' => 'Diabetes',
                                    'hypertension' => 'Hypertension',
                                    'cancer' => 'Cancer',
                                    'stroke' => 'Stroke',
                                    'kidney-disease' => 'Kidney Disease',
                                    'mental-illness' => 'Mental Illness',
                                    'autoimmune' => 'Autoimmune Disease',
                                    'genetic-disorders' => 'Genetic Disorders'
                                ],
                                'help_text' => 'Select all conditions present in family history'
                            ],
                            [
                                'id' => 'family_history_details',
                                'type' => 'textarea_field',
                                'label' => 'Family History Details',
                                'required' => false,
                                'rows' => 3,
                                'help_text' => 'Provide details about family medical history including relationships'
                            ]
                        ]
                    ],
                    [
                        'id' => 'review_of_systems',
                        'title' => 'Review of Systems',
                        'description' => 'Systematic review of body systems',
                        'fields' => [
                            [
                                'id' => 'constitutional_symptoms',
                                'type' => 'select_field',
                                'label' => 'Constitutional Symptoms',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'fever' => 'Fever',
                                    'weight-loss' => 'Weight loss',
                                    'weight-gain' => 'Weight gain',
                                    'fatigue' => 'Fatigue',
                                    'night-sweats' => 'Night sweats',
                                    'appetite-change' => 'Appetite change'
                                ],
                                'help_text' => 'Constitutional symptoms experienced'
                            ],
                            [
                                'id' => 'cardiovascular_symptoms',
                                'type' => 'select_field',
                                'label' => 'Cardiovascular Symptoms',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'chest-pain' => 'Chest pain',
                                    'palpitations' => 'Palpitations',
                                    'shortness-of-breath' => 'Shortness of breath',
                                    'edema' => 'Swelling/Edema',
                                    'syncope' => 'Fainting/Syncope'
                                ],
                                'help_text' => 'Cardiovascular symptoms'
                            ],
                            [
                                'id' => 'respiratory_symptoms',
                                'type' => 'select_field',
                                'label' => 'Respiratory Symptoms',
                                'required' => false,
                                'multiple' => true,
                                'options' => [
                                    'cough' => 'Cough',
                                    'sputum' => 'Sputum production',
                                    'wheezing' => 'Wheezing',
                                    'dyspnea' => 'Difficulty breathing',
                                    'hemoptysis' => 'Coughing blood'
                                ],
                                'help_text' => 'Respiratory symptoms'
                            ]
                        ]
                    ]
                ]
            ],
            'fhir_mapping' => [
                'field_mappings' => [
                    'chief_complaint' => [
                        'observation_concept_id' => 16,
                        'value_field' => 'value_text',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'hpi_details' => [
                        'observation_concept_id' => 17,
                        'value_field' => 'value_text',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'chronic_conditions' => [
                        'observation_concept_id' => 18,
                        'value_field' => 'value_complex',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'current_medications' => [
                        'observation_concept_id' => 19,
                        'value_field' => 'value_text',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'drug_allergies' => [
                        'observation_concept_id' => 20,
                        'value_field' => 'value_text',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'smoking_status' => [
                        'observation_concept_id' => 21,
                        'value_field' => 'value_string',
                        'unit' => null,
                        'body_site_id' => null
                    ],
                    'family_conditions' => [
                        'observation_concept_id' => 22,
                        'value_field' => 'value_complex',
                        'unit' => null,
                        'body_site_id' => null
                    ]
                ],
                'default_values' => [
                    'observation_status_id' => 1,
                    'body_site_id' => null
                ]
            ],
            'active' => true
        ]);
    }
}
