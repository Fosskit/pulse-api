<?php

namespace Tests\Unit\Actions;

use App\Actions\ValidateFormDataAction;
use App\Models\ClinicalFormTemplate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

class ValidateFormDataActionTest extends TestCase
{
    use RefreshDatabase;

    private ValidateFormDataAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ValidateFormDataAction();
    }

    public function test_validates_required_fields()
    {
        $formTemplate = ClinicalFormTemplate::factory()->create([
            'form_schema' => [
                'sections' => [
                    [
                        'id' => 'vitals',
                        'title' => 'Vital Signs',
                        'fields' => [
                            [
                                'id' => 'temperature',
                                'type' => 'number_field',
                                'label' => 'Temperature',
                                'required' => true,
                                'min_value' => 35.0,
                                'max_value' => 42.0,
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Test missing required field
        $this->expectException(ValidationException::class);
        $this->action->execute($formTemplate, []);
    }

    public function test_validates_numeric_fields_with_ranges()
    {
        $formTemplate = ClinicalFormTemplate::factory()->create([
            'form_schema' => [
                'sections' => [
                    [
                        'id' => 'vitals',
                        'fields' => [
                            [
                                'id' => 'temperature',
                                'type' => 'number_field',
                                'label' => 'Temperature',
                                'required' => true,
                                'min_value' => 35.0,
                                'max_value' => 42.0,
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Test valid temperature
        $result = $this->action->execute($formTemplate, ['temperature' => 37.5]);
        $this->assertEquals(['temperature' => 37.5], $result['validated_data']);

        // Test temperature too low
        $this->expectException(ValidationException::class);
        $this->action->execute($formTemplate, ['temperature' => 30.0]);
    }

    public function test_validates_email_fields()
    {
        $formTemplate = ClinicalFormTemplate::factory()->create([
            'form_schema' => [
                'sections' => [
                    [
                        'id' => 'contact',
                        'fields' => [
                            [
                                'id' => 'email',
                                'type' => 'email',
                                'label' => 'Email Address',
                                'required' => false,
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Test valid email
        $result = $this->action->execute($formTemplate, ['email' => 'test@example.com']);
        $this->assertEquals(['email' => 'test@example.com'], $result['validated_data']);

        // Test invalid email
        $this->expectException(ValidationException::class);
        $this->action->execute($formTemplate, ['email' => 'invalid-email']);
    }

    public function test_validates_date_fields()
    {
        $formTemplate = ClinicalFormTemplate::factory()->create([
            'form_schema' => [
                'sections' => [
                    [
                        'id' => 'dates',
                        'fields' => [
                            [
                                'id' => 'birth_date',
                                'type' => 'date_field',
                                'label' => 'Birth Date',
                                'required' => true,
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Test valid date
        $result = $this->action->execute($formTemplate, ['birth_date' => '1990-01-01']);
        $this->assertEquals(['birth_date' => '1990-01-01'], $result['validated_data']);

        // Test invalid date
        $this->expectException(ValidationException::class);
        $this->action->execute($formTemplate, ['birth_date' => 'invalid-date']);
    }

    public function test_validates_text_fields_with_max_length()
    {
        $formTemplate = ClinicalFormTemplate::factory()->create([
            'form_schema' => [
                'sections' => [
                    [
                        'id' => 'notes',
                        'fields' => [
                            [
                                'id' => 'comments',
                                'type' => 'text_field',
                                'label' => 'Comments',
                                'max_length' => 10,
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        // Test valid text
        $result = $this->action->execute($formTemplate, ['comments' => 'Short']);
        $this->assertEquals(['comments' => 'Short'], $result['validated_data']);

        // Test text too long
        $this->expectException(ValidationException::class);
        $this->action->execute($formTemplate, ['comments' => 'This text is way too long']);
    }

    public function test_returns_validation_summary()
    {
        $formTemplate = ClinicalFormTemplate::factory()->create([
            'form_schema' => [
                'sections' => [
                    [
                        'id' => 'vitals',
                        'fields' => [
                            [
                                'id' => 'temperature',
                                'type' => 'number_field',
                                'label' => 'Temperature',
                                'required' => true,
                            ],
                            [
                                'id' => 'pulse',
                                'type' => 'number_field',
                                'label' => 'Pulse',
                                'required' => false,
                            ]
                        ]
                    ]
                ]
            ]
        ]);

        $result = $this->action->execute($formTemplate, [
            'temperature' => 37.5,
            'pulse' => 80,
            'extra_field' => 'ignored'
        ]);

        $this->assertEquals(3, $result['field_count']);
        $this->assertEquals(2, $result['validated_field_count']);
        $this->assertEquals($formTemplate->id, $result['form_template_id']);
        $this->assertEquals($formTemplate->name, $result['form_template_name']);
        $this->assertArrayHasKey('validation_rules', $result);
    }

    public function test_validates_vital_signs_field_type()
    {
        $this->assertTrue($this->action->validateFieldType('vital_signs', 37.5, ['vital_type' => 'temperature']));
        $this->assertFalse($this->action->validateFieldType('vital_signs', 50.0, ['vital_type' => 'temperature']));
        $this->assertFalse($this->action->validateFieldType('vital_signs', 'invalid', ['vital_type' => 'temperature']));
    }

    public function test_validates_medication_dosage_field_type()
    {
        $this->assertTrue($this->action->validateFieldType('medication_dosage', '10mg', []));
        $this->assertTrue($this->action->validateFieldType('medication_dosage', '2.5ml', []));
        $this->assertTrue($this->action->validateFieldType('medication_dosage', '1 tablet', []));
        $this->assertFalse($this->action->validateFieldType('medication_dosage', 'invalid dosage', []));
    }

    public function test_validates_clinical_scale_field_type()
    {
        $this->assertTrue($this->action->validateFieldType('clinical_scale', 5, ['min_scale' => 0, 'max_scale' => 10]));
        $this->assertFalse($this->action->validateFieldType('clinical_scale', 15, ['min_scale' => 0, 'max_scale' => 10]));
        $this->assertFalse($this->action->validateFieldType('clinical_scale', -1, ['min_scale' => 0, 'max_scale' => 10]));
    }
}