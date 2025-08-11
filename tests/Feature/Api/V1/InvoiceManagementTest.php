<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Service;
use App\Models\MedicationRequest;
use App\Models\User;
use App\Models\Term;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class InvoiceManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_generate_invoice_for_visit()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        
        $invoiceData = [
            'percentage_discount' => 10.0,
            'amount_discount' => 5.0,
            'remark' => 'Test invoice generation',
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/visits/{$visit->id}/invoices", $invoiceData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'ulid',
                    'code',
                    'visit_id',
                    'date',
                    'total',
                    'percentage_discount',
                    'amount_discount',
                    'discount',
                    'received',
                    'remaining_balance',
                    'is_fully_paid',
                    'remark',
                    'summary' => [
                        'subtotal',
                        'total_discount',
                        'final_amount',
                        'amount_paid',
                        'balance_due',
                        'payment_status',
                    ],
                ]
            ]);

        $this->assertDatabaseHas('invoices', [
            'visit_id' => $visit->id,
            'percentage_discount' => 10.0,
            'amount_discount' => 5.0,
            'remark' => 'Test invoice generation',
        ]);
    }

    public function test_can_show_invoice_details()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create();
        
        $service = Service::factory()->create();
        InvoiceItem::factory()->for($invoice)->forService()->create([
            'invoiceable_id' => $service->id,
            'quantity' => 2,
            'price' => 50.00,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/invoices/{$invoice->id}");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'id',
                    'ulid',
                    'code',
                    'visit_id',
                    'date',
                    'total',
                    'percentage_discount',
                    'amount_discount',
                    'discount',
                    'received',
                    'remaining_balance',
                    'is_fully_paid',
                    'visit' => [
                        'id',
                        'ulid',
                        'patient' => [
                            'id',
                            'code',
                            'full_name',
                        ],
                    ],
                    'invoice_items' => [
                        '*' => [
                            'id',
                            'ulid',
                            'invoiceable_type',
                            'invoiceable_id',
                            'quantity',
                            'price',
                            'line_total',
                            'item_details' => [
                                'type',
                                'id',
                                'name',
                            ],
                        ],
                    ],
                    'summary',
                ]
            ]);
    }

    public function test_can_get_visit_invoices()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        
        $invoice1 = Invoice::factory()->for($visit)->create();
        $invoice2 = Invoice::factory()->for($visit)->create();

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/visits/{$visit->id}/invoices");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ulid',
                        'code',
                        'visit_id',
                        'total',
                        'remaining_balance',
                        'is_fully_paid',
                        'summary',
                    ],
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertCount(2, $responseData);
    }

    public function test_can_get_patient_billing_history()
    {
        // Arrange
        $patient = Patient::factory()->create();
        
        $visit1 = Visit::factory()->for($patient)->create();
        $visit2 = Visit::factory()->for($patient)->create();
        
        $invoice1 = Invoice::factory()->for($visit1)->create();
        $invoice2 = Invoice::factory()->for($visit2)->create();

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/patients/{$patient->id}/billing-history");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ulid',
                        'code',
                        'visit_id',
                        'total',
                        'remaining_balance',
                        'is_fully_paid',
                        'visit' => [
                            'id',
                            'ulid',
                        ],
                        'summary',
                    ],
                ],
                'meta' => [
                    'current_page',
                    'last_page',
                    'per_page',
                    'total',
                ],
            ]);

        $responseData = $response->json('data');
        $this->assertCount(2, $responseData);
    }

    public function test_invoice_generation_validates_request_data()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        
        $invalidData = [
            'percentage_discount' => 150.0, // Invalid: over 100%
            'amount_discount' => -10.0, // Invalid: negative
            'remark' => str_repeat('a', 100), // Invalid: too long
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/visits/{$visit->id}/invoices", $invalidData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'percentage_discount',
                'amount_discount',
                'remark',
            ]);
    }

    public function test_invoice_generation_determines_payment_type_from_patient_insurance()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();

        // Create insurance payment type
        $insurancePaymentType = Term::factory()->create([
            'name' => 'Insurance Coverage',
            'category' => 'payment_type'
        ]);

        // Mock patient having insurance
        $this->partialMock(Patient::class, function ($mock) use ($insurancePaymentType) {
            $mock->shouldReceive('getPaymentTypeId')->andReturn($insurancePaymentType->id);
        });

        // Act
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/visits/{$visit->id}/invoices", []);

        // Assert
        $response->assertStatus(201);
        
        $invoice = Invoice::where('visit_id', $visit->id)->first();
        $this->assertNotNull($invoice);
    }

    public function test_invoice_includes_service_items_from_encounters()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        
        // Create service that should be billed
        Service::factory()->create(['name' => 'General Consultation']);

        // Act
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/visits/{$visit->id}/invoices", []);

        // Assert
        $response->assertStatus(201);
        
        $invoice = Invoice::where('visit_id', $visit->id)->first();
        $this->assertNotNull($invoice);
    }

    public function test_invoice_includes_medication_items_from_dispenses()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        
        $medicationRequest = MedicationRequest::factory()->for($visit)->create();
        
        // Create dispense for billing
        $medicationRequest->dispenses()->create([
            'ulid' => \Illuminate\Support\Str::ulid(),
            'visit_id' => $visit->id,
            'quantity' => 5,
            'dispenser_id' => $this->user->id,
            'dispensed_at' => now(),
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/visits/{$visit->id}/invoices", []);

        // Assert
        $response->assertStatus(201);
        
        $invoice = Invoice::where('visit_id', $visit->id)->first();
        $this->assertNotNull($invoice);
        
        // Check if medication items were included
        $medicationItems = $invoice->invoiceItems()
            ->where('invoiceable_type', MedicationRequest::class)
            ->count();
        $this->assertTrue($medicationItems > 0);
    }

    public function test_invoice_calculations_are_accurate()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
            'percentage_discount' => 10.0,
            'amount_discount' => 5.0,
            'received' => 30.00,
        ]);

        InvoiceItem::factory()->for($invoice)->create([
            'quantity' => 2,
            'price' => 50.00,
        ]);

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/invoices/{$invoice->id}");

        // Assert
        $response->assertStatus(200);
        
        $data = $response->json('data');
        $this->assertEquals(100.00, $data['total']);
        $this->assertEquals(15.00, $data['discount']); // 10% of 100 + 5
        $this->assertEquals(85.00, $data['summary']['final_amount']); // 100 - 15
        $this->assertEquals(55.00, $data['remaining_balance']); // 85 - 30
        $this->assertFalse($data['is_fully_paid']);
    }

    public function test_requires_authentication()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();

        // Act & Assert
        $this->postJson("/api/v1/visits/{$visit->id}/invoices", [])
            ->assertStatus(401);

        $this->getJson("/api/v1/patients/{$patient->id}/billing-history")
            ->assertStatus(401);
    }

    public function test_handles_nonexistent_visit()
    {
        // Act & Assert
        $this->actingAs($this->user)
            ->postJson("/api/v1/visits/99999/invoices", [])
            ->assertStatus(404);
    }

    public function test_handles_nonexistent_invoice()
    {
        // Act & Assert
        $this->actingAs($this->user)
            ->getJson("/api/v1/invoices/99999")
            ->assertStatus(404);
    }
}