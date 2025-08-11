<?php

namespace Tests\Unit\Actions;

use App\Actions\CalculateDiscountsAction;
use App\Models\Invoice;
use App\Models\Patient;
use App\Models\PatientIdentity;
use App\Models\Card;
use App\Models\Visit;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CalculateDiscountsActionTest extends TestCase
{
    use RefreshDatabase;

    private CalculateDiscountsAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new CalculateDiscountsAction();
    }

    public function test_calculates_discounts_for_patient_with_insurance()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
            'percentage_discount' => 0,
            'amount_discount' => 0,
        ]);

        // Create insurance card and identity
        $card = Card::factory()->create(['name' => 'Government Insurance']);
        $identity = PatientIdentity::factory()->for($patient)->create([
            'card_id' => $card->id,
            'code' => 'GOV123456',
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(365),
        ]);

        // Mock the activeInsurance relationship
        $patient = $this->partialMock(Patient::class, function ($mock) use ($identity) {
            $mock->shouldReceive('activeInsurance->first')->andReturn($identity);
        });
        
        $visit->patient_id = $patient->id;
        $visit->save();

        // Act
        $result = $this->action->execute($invoice);

        // Assert
        $this->assertTrue($result['has_insurance']);
        $this->assertEquals('government', $result['coverage_type']);
        $this->assertEquals(90.0, $result['percentage_discount']); // Government insurance covers 90%
        $this->assertEquals(90.0, $result['total_discount']); // 90% of $100
        $this->assertEquals(10.0, $result['final_amount']); // $100 - $90 + $0 copay
    }

    public function test_returns_no_discount_for_patient_without_insurance()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
        ]);

        // Mock no active insurance
        $patient = $this->partialMock(Patient::class, function ($mock) {
            $mock->shouldReceive('activeInsurance->first')->andReturn(null);
        });
        
        $visit->patient_id = $patient->id;
        $visit->save();

        // Act
        $result = $this->action->execute($invoice);

        // Assert
        $this->assertFalse($result['has_insurance']);
        $this->assertNull($result['coverage_type']);
        $this->assertEquals(0, $result['percentage_discount']);
        $this->assertEquals(0, $result['total_discount']);
        $this->assertEquals(100.0, $result['final_amount']);
    }

    public function test_applies_maximum_coverage_limit()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 10000.00, // High amount to test coverage limit
        ]);

        // Create private insurance card (has coverage limit)
        $card = Card::factory()->create(['name' => 'Private Insurance']);
        $identity = PatientIdentity::factory()->for($patient)->create([
            'card_id' => $card->id,
            'code' => 'PRI123456',
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(365),
        ]);

        // Mock the activeInsurance relationship
        $patient = $this->partialMock(Patient::class, function ($mock) use ($identity) {
            $mock->shouldReceive('activeInsurance->first')->andReturn($identity);
        });
        
        $visit->patient_id = $patient->id;
        $visit->save();

        // Act
        $result = $this->action->execute($invoice);

        // Assert
        $this->assertTrue($result['has_insurance']);
        $this->assertEquals('private', $result['coverage_type']);
        // Should be limited to maximum coverage of $10,000 instead of 70% of $10,000 = $7,000
        $this->assertTrue($result['percentage_discount'] <= 10000.0);
    }

    public function test_includes_copay_in_final_amount()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
        ]);

        // Create social security card (has copay)
        $card = Card::factory()->create(['name' => 'NSSF Social Security']);
        $identity = PatientIdentity::factory()->for($patient)->create([
            'card_id' => $card->id,
            'code' => 'NSSF123456',
            'start_date' => now()->subDays(30),
            'end_date' => now()->addDays(365),
        ]);

        // Mock the activeInsurance relationship
        $patient = $this->partialMock(Patient::class, function ($mock) use ($identity) {
            $mock->shouldReceive('activeInsurance->first')->andReturn($identity);
        });
        
        $visit->patient_id = $patient->id;
        $visit->save();

        // Act
        $result = $this->action->execute($invoice);

        // Assert
        $this->assertTrue($result['has_insurance']);
        $this->assertEquals('social_security', $result['coverage_type']);
        
        // NSSF covers 80% with $5 copay
        $expectedDiscount = 80.0; // 80% of $100
        $expectedCopay = 5.0;
        $expectedFinalAmount = (100.0 - $expectedDiscount) + $expectedCopay; // $20 + $5 = $25
        
        $this->assertEquals($expectedDiscount, $result['percentage_discount']);
        $this->assertEquals($expectedFinalAmount, $result['final_amount']);
    }

    public function test_generates_insurance_claim_data()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create();

        // Create insurance
        $card = Card::factory()->create(['name' => 'Government Insurance']);
        $identity = PatientIdentity::factory()->for($patient)->create([
            'card_id' => $card->id,
            'code' => 'GOV123456',
        ]);

        // Mock the activeInsurance relationship
        $patient = $this->partialMock(Patient::class, function ($mock) use ($identity) {
            $mock->shouldReceive('activeInsurance->first')->andReturn($identity);
        });
        
        $visit->patient_id = $patient->id;
        $visit->save();

        // Act
        $claimData = $this->action->generateInsuranceClaim($invoice);

        // Assert
        $this->assertArrayHasKey('claim_id', $claimData);
        $this->assertArrayHasKey('patient_information', $claimData);
        $this->assertArrayHasKey('insurance_information', $claimData);
        $this->assertArrayHasKey('visit_information', $claimData);
        $this->assertArrayHasKey('billing_information', $claimData);
        
        $this->assertEquals($invoice->id, $claimData['invoice_id']);
        $this->assertEquals($patient->id, $claimData['patient_information']['patient_id']);
        $this->assertEquals('GOV123456', $claimData['insurance_information']['card_number']);
        $this->assertEquals('pending', $claimData['claim_status']);
    }

    public function test_throws_exception_when_generating_claim_without_insurance()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create();

        // Mock no active insurance
        $patient = $this->partialMock(Patient::class, function ($mock) {
            $mock->shouldReceive('activeInsurance->first')->andReturn(null);
        });
        
        $visit->patient_id = $patient->id;
        $visit->save();

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Patient does not have active insurance coverage');
        
        $this->action->generateInsuranceClaim($invoice);
    }

    public function test_applies_additional_discount_option()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
        ]);

        // Create insurance
        $card = Card::factory()->create(['name' => 'Government Insurance']);
        $identity = PatientIdentity::factory()->for($patient)->create([
            'card_id' => $card->id,
        ]);

        // Mock the activeInsurance relationship
        $patient = $this->partialMock(Patient::class, function ($mock) use ($identity) {
            $mock->shouldReceive('activeInsurance->first')->andReturn($identity);
        });
        
        $visit->patient_id = $patient->id;
        $visit->save();

        $options = ['additional_discount' => 10.0];

        // Act
        $result = $this->action->execute($invoice, $options);

        // Assert
        $this->assertEquals(10.0, $result['amount_discount']);
        $this->assertEquals(100.0, $result['total_discount']); // 90% + $10 additional = $100
        $this->assertEquals(0.0, $result['final_amount']); // $100 - $100 + $0 copay
    }
}