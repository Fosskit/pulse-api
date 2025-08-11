<?php

namespace Tests\Unit\Actions;

use App\Actions\GenerateInvoiceAction;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Service;
use App\Models\MedicationRequest;
use App\Models\MedicationDispense;
use App\Models\ServiceRequest;
use App\Models\Term;
use App\Models\Encounter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GenerateInvoiceActionTest extends TestCase
{
    use RefreshDatabase;

    private GenerateInvoiceAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new GenerateInvoiceAction();
    }

    public function test_generates_invoice_for_visit_with_services()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        
        // Create encounters for the visit
        $encounter = Encounter::factory()->for($visit)->create();
        
        // Create a service for billing
        $service = Service::factory()->create(['name' => 'General Consultation']);
        
        // Act
        $invoice = $this->action->execute($visit);

        // Assert
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals($visit->id, $invoice->visit_id);
        $this->assertNotNull($invoice->code);
        $this->assertNotNull($invoice->payment_type_id);
        $this->assertNotNull($invoice->invoice_category_id);
        $this->assertEquals(0, $invoice->received);
    }

    public function test_generates_invoice_with_medication_items()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        
        $medicationRequest = MedicationRequest::factory()->for($visit)->create([
            'quantity' => 10
        ]);
        
        // Create a dispense for the medication
        MedicationDispense::factory()->for($medicationRequest)->create([
            'quantity' => 5
        ]);

        // Act
        $invoice = $this->action->execute($visit);

        // Assert
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertTrue($invoice->invoiceItems->count() > 0);
        
        // Check if medication items were added
        $medicationItems = $invoice->invoiceItems->where('invoiceable_type', MedicationRequest::class);
        $this->assertTrue($medicationItems->count() > 0);
    }

    public function test_determines_payment_type_based_on_patient_insurance()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();

        // Mock patient having insurance
        $insurancePaymentType = Term::factory()->create([
            'name' => 'Insurance',
            'category' => 'payment_type'
        ]);

        // Mock the patient's getPaymentTypeId method to return insurance type
        $patient = $this->partialMock(Patient::class, function ($mock) use ($insurancePaymentType) {
            $mock->shouldReceive('getPaymentTypeId')->andReturn($insurancePaymentType->id);
        });
        
        $visit->patient_id = $patient->id;
        $visit->save();

        // Act
        $invoice = $this->action->execute($visit);

        // Assert
        $this->assertEquals($insurancePaymentType->id, $invoice->payment_type_id);
    }

    public function test_applies_percentage_and_amount_discounts()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        
        $service = Service::factory()->create(['name' => 'Test Service']);
        
        $options = [
            'percentage_discount' => 10.0,
            'amount_discount' => 5.0,
        ];

        // Act
        $invoice = $this->action->execute($visit, $options);

        // Assert
        $this->assertEquals(10.0, $invoice->percentage_discount);
        $this->assertEquals(5.0, $invoice->amount_discount);
        $this->assertTrue($invoice->discount > 0);
    }

    public function test_calculates_invoice_totals_correctly()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        
        // Create encounters and services that will generate invoice items
        Encounter::factory()->for($visit)->create();
        Service::factory()->create(['name' => 'General Consultation']);

        // Act
        $invoice = $this->action->execute($visit);

        // Assert
        $this->assertTrue($invoice->total >= 0);
        $this->assertEquals($invoice->calculateTotal(), $invoice->total);
        $this->assertEquals($invoice->calculateDiscount(), $invoice->discount);
    }

    public function test_generates_unique_invoice_code()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit1 = Visit::factory()->for($patient)->create();
        $visit2 = Visit::factory()->for($patient)->create();

        // Act
        $invoice1 = $this->action->execute($visit1);
        $invoice2 = $this->action->execute($visit2);

        // Assert
        $this->assertNotEquals($invoice1->code, $invoice2->code);
        $this->assertStringStartsWith('INV-', $invoice1->code);
        $this->assertStringStartsWith('INV-', $invoice2->code);
    }

    public function test_handles_visit_without_services_or_medications()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();

        // Act
        $invoice = $this->action->execute($visit);

        // Assert
        $this->assertInstanceOf(Invoice::class, $invoice);
        $this->assertEquals(0, $invoice->total);
        $this->assertEquals(0, $invoice->invoiceItems->count());
    }

    public function test_creates_default_terms_if_not_exist()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();

        // Ensure no default terms exist
        Term::where('category', 'invoice_category')->delete();
        Term::where('category', 'payment_type')->delete();

        // Act
        $invoice = $this->action->execute($visit);

        // Assert
        $this->assertNotNull($invoice->invoice_category_id);
        $this->assertNotNull($invoice->payment_type_id);
        
        // Check that default terms were created
        $this->assertDatabaseHas('terms', [
            'name' => 'General Invoice',
            'category' => 'invoice_category'
        ]);
        
        $this->assertDatabaseHas('terms', [
            'name' => 'Self Pay',
            'category' => 'payment_type'
        ]);
    }

    public function test_uses_custom_options()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        
        $customCategory = Term::factory()->create([
            'name' => 'Emergency Invoice',
            'category' => 'invoice_category'
        ]);
        
        $customDate = now()->subDays(5);
        
        $options = [
            'invoice_category_id' => $customCategory->id,
            'date' => $customDate,
            'remark' => 'Emergency treatment',
        ];

        // Act
        $invoice = $this->action->execute($visit, $options);

        // Assert
        $this->assertEquals($customCategory->id, $invoice->invoice_category_id);
        $this->assertEquals($customDate->format('Y-m-d H:i:s'), $invoice->date->format('Y-m-d H:i:s'));
        $this->assertEquals('Emergency treatment', $invoice->remark);
    }
}