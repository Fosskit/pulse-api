<?php

namespace Tests\Unit\Actions;

use App\Actions\ProcessFormSubmissionAction;
use App\Actions\ValidateFormDataAction;
use App\Actions\GenerateObservationsAction;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Encounter;
use App\Models\ClinicalFormTemplate;
use App\Models\Observation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;
use Mockery;

class ProcessFormSubmissionActionTest extends TestCase
{
    use RefreshDatabase;

    private ProcessFormSubmissionAction $action;
    private ValidateFormDataAction $mockValidateAction;
    private GenerateObservationsAction $mockGenerateAction;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->mockValidateAction = Mockery::mock(ValidateFormDataAction::class);
        $this->mockGenerateAction = Mockery::mock(GenerateObservationsAction::class);
        
        $this->action = new ProcessFormSubmissionAction(
            $this->mockValidateAction,
            $this->mockGenerateAction
        );
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_processes_valid_form_submission()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $formTemplate = ClinicalFormTemplate::factory()->create();

        $formData = [
            'temperature' => 37.5,
            'blood_pressure_systolic' => 120,
            'blood_pressure_diastolic' => 80
        ];

        $this->mockValidateAction
            ->shouldReceive('execute')
            ->once()
            ->with($formTemplate, $formData)
            ->andReturn(['valid' => true, 'errors' => []]);

        $expectedObservations = [
            ['concept_id' => 1, 'value_number' => 37.5],
            ['concept_id' => 2, 'value_number' => 120],
            ['concept_id' => 3, 'value_number' => 80]
        ];

        $this->mockGenerateAction
            ->shouldReceive('execute')
            ->once()
            ->with($formTemplate, $formData, $encounter->id, $patient->id)
            ->andReturn($expectedObservations);

        $result = $this->action->execute($encounter->id, $formTemplate->id, $formData);

        $this->assertTrue($result['success']);
        $this->assertCount(3, $result['observations']);
        $this->assertEmpty($result['errors']);
    }

    public function test_handles_validation_errors()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $formTemplate = ClinicalFormTemplate::factory()->create();

        $formData = [
            'temperature' => 'invalid', // Invalid temperature
            'blood_pressure_systolic' => null // Missing required field
        ];

        $validationErrors = [
            'temperature' => ['Temperature must be a number'],
            'blood_pressure_systolic' => ['Blood pressure systolic is required']
        ];

        $this->mockValidateAction
            ->shouldReceive('execute')
            ->once()
            ->with($formTemplate, $formData)
            ->andReturn(['valid' => false, 'errors' => $validationErrors]);

        $this->mockGenerateAction
            ->shouldNotReceive('execute');

        $result = $this->action->execute($encounter->id, $formTemplate->id, $formData);

        $this->assertFalse($result['success']);
        $this->assertEquals($validationErrors, $result['errors']);
        $this->assertEmpty($result['observations']);
    }

    public function test_handles_nonexistent_encounter()
    {
        $formTemplate = ClinicalFormTemplate::factory()->create();
        $formData = ['temperature' => 37.5];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Encounter not found');

        $this->action->execute(999, $formTemplate->id, $formData);
    }

    public function test_handles_nonexistent_form_template()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $formData = ['temperature' => 37.5];

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Form template not found');

        $this->action->execute($encounter->id, 999, $formData);
    }

    public function test_updates_encounter_with_form_template()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'encounter_form_id' => null
        ]);
        $formTemplate = ClinicalFormTemplate::factory()->create();

        $formData = ['temperature' => 37.5];

        $this->mockValidateAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->mockGenerateAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn([]);

        $result = $this->action->execute($encounter->id, $formTemplate->id, $formData);

        $encounter->refresh();
        $this->assertEquals($formTemplate->id, $encounter->encounter_form_id);
        $this->assertTrue($result['success']);
    }

    public function test_handles_partial_form_data()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $formTemplate = ClinicalFormTemplate::factory()->create();

        $formData = [
            'temperature' => 37.5
            // Missing other optional fields
        ];

        $this->mockValidateAction
            ->shouldReceive('execute')
            ->once()
            ->with($formTemplate, $formData)
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->mockGenerateAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn([['concept_id' => 1, 'value_number' => 37.5]]);

        $result = $this->action->execute($encounter->id, $formTemplate->id, $formData);

        $this->assertTrue($result['success']);
        $this->assertCount(1, $result['observations']);
    }

    public function test_handles_empty_form_data()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $formTemplate = ClinicalFormTemplate::factory()->create();

        $formData = [];

        $this->mockValidateAction
            ->shouldReceive('execute')
            ->once()
            ->with($formTemplate, $formData)
            ->andReturn(['valid' => false, 'errors' => ['No data provided']]);

        $this->mockGenerateAction
            ->shouldNotReceive('execute');

        $result = $this->action->execute($encounter->id, $formTemplate->id, $formData);

        $this->assertFalse($result['success']);
        $this->assertEquals(['No data provided'], $result['errors']);
    }

    public function test_includes_form_submission_metadata()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $formTemplate = ClinicalFormTemplate::factory()->create();

        $formData = ['temperature' => 37.5];

        $this->mockValidateAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->mockGenerateAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn([['concept_id' => 1, 'value_number' => 37.5]]);

        $result = $this->action->execute($encounter->id, $formTemplate->id, $formData);

        $this->assertArrayHasKey('metadata', $result);
        $this->assertEquals($encounter->id, $result['metadata']['encounter_id']);
        $this->assertEquals($formTemplate->id, $result['metadata']['form_template_id']);
        $this->assertArrayHasKey('submitted_at', $result['metadata']);
    }

    public function test_handles_observation_generation_failure()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $formTemplate = ClinicalFormTemplate::factory()->create();

        $formData = ['temperature' => 37.5];

        $this->mockValidateAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->mockGenerateAction
            ->shouldReceive('execute')
            ->once()
            ->andThrow(new \Exception('Failed to generate observations'));

        $result = $this->action->execute($encounter->id, $formTemplate->id, $formData);

        $this->assertFalse($result['success']);
        $this->assertStringContainsString('Failed to generate observations', $result['errors'][0]);
    }

    public function test_validates_required_parameters()
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->action->execute(null, 1, []);
    }

    public function test_logs_form_submission_activity()
    {
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create(['patient_id' => $patient->id]);
        $encounter = Encounter::factory()->create(['visit_id' => $visit->id]);
        $formTemplate = ClinicalFormTemplate::factory()->create();

        $formData = ['temperature' => 37.5];

        $this->mockValidateAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn(['valid' => true, 'errors' => []]);

        $this->mockGenerateAction
            ->shouldReceive('execute')
            ->once()
            ->andReturn([['concept_id' => 1, 'value_number' => 37.5]]);

        $result = $this->action->execute($encounter->id, $formTemplate->id, $formData);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('activity_logged', $result['metadata']);
    }
}