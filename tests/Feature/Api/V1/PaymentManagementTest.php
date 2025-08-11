<?php

namespace Tests\Feature\Api\V1;

use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Term;
use App\Models\User;
use App\Models\Card;
use App\Models\PatientIdentity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PaymentManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    public function test_can_record_payment_for_invoice()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
            'discount' => 10.00,
            'received' => 0.00,
        ]);

        $paymentMethod = Term::factory()->create([
            'name' => 'Cash',
            'category' => 'payment_method'
        ]);

        $paymentData = [
            'amount' => 50.00,
            'payment_method_id' => $paymentMethod->id,
            'payment_date' => now()->toISOString(),
            'reference_number' => 'PAY123456',
            'notes' => 'Partial payment',
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", $paymentData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'ulid',
                    'invoice_id',
                    'amount',
                    'payment_type',
                    'is_refund',
                    'payment_date',
                    'reference_number',
                    'notes',
                ]
            ]);

        $this->assertDatabaseHas('invoice_payments', [
            'invoice_id' => $invoice->id,
            'amount' => 50.00,
            'payment_method_id' => $paymentMethod->id,
            'reference_number' => 'PAY123456',
        ]);

        // Check invoice updated
        $invoice->refresh();
        $this->assertEquals(50.00, $invoice->received);
    }

    public function test_validates_payment_amount()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
            'discount' => 10.00,
            'received' => 80.00, // Only $10 remaining
        ]);

        $paymentMethod = Term::factory()->create([
            'name' => 'Cash',
            'category' => 'payment_method'
        ]);

        $paymentData = [
            'amount' => 20.00, // More than remaining balance
            'payment_method_id' => $paymentMethod->id,
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", $paymentData);

        // Assert
        $response->assertStatus(400);
    }

    public function test_can_process_refund()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
            'received' => 100.00,
        ]);

        $originalPayment = InvoicePayment::factory()->for($invoice)->create([
            'amount' => 100.00,
        ]);

        $refundData = [
            'amount' => 30.00,
            'refund_date' => now()->toISOString(),
            'reference_number' => 'REF123456',
            'notes' => 'Partial refund',
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/payments/{$originalPayment->id}/refund", $refundData);

        // Assert
        $response->assertStatus(201)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'id',
                    'amount',
                    'payment_type',
                    'is_refund',
                    'reference_number',
                ]
            ]);

        $responseData = $response->json('data');
        $this->assertEquals(-30.00, $responseData['amount']);
        $this->assertTrue($responseData['is_refund']);

        // Check invoice updated
        $invoice->refresh();
        $this->assertEquals(70.00, $invoice->received);
    }

    public function test_can_get_payment_summary()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
            'discount' => 10.00,
            'received' => 70.00,
        ]);

        InvoicePayment::factory()->for($invoice)->create(['amount' => 50.00]);
        InvoicePayment::factory()->for($invoice)->create(['amount' => 30.00]);
        InvoicePayment::factory()->for($invoice)->create(['amount' => -10.00]); // Refund

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/invoices/{$invoice->id}/payment-summary");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'invoice_id',
                    'invoice_total',
                    'total_paid',
                    'total_refunded',
                    'net_paid',
                    'remaining_balance',
                    'is_fully_paid',
                    'payment_status',
                    'payments' => [
                        '*' => [
                            'id',
                            'amount',
                            'payment_date',
                            'type',
                        ]
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals(90.00, $data['invoice_total']);
        $this->assertEquals(80.00, $data['total_paid']);
        $this->assertEquals(10.00, $data['total_refunded']);
        $this->assertEquals(70.00, $data['net_paid']);
        $this->assertEquals('partial', $data['payment_status']);
        $this->assertCount(3, $data['payments']);
    }

    public function test_can_calculate_insurance_discounts()
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
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/invoices/{$invoice->id}/calculate-discounts");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'has_insurance',
                    'coverage_type',
                    'percentage_discount',
                    'amount_discount',
                    'total_discount',
                    'final_amount',
                    'coverage_details',
                    'calculation_breakdown',
                ]
            ]);

        $data = $response->json('data');
        $this->assertTrue($data['has_insurance']);
        $this->assertEquals('government', $data['coverage_type']);
    }

    public function test_can_generate_insurance_claim()
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
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->id}/insurance-claim");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'message',
                'data' => [
                    'claim_id',
                    'invoice_id',
                    'patient_information',
                    'insurance_information',
                    'visit_information',
                    'billing_information',
                    'claim_status',
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals($invoice->id, $data['invoice_id']);
        $this->assertEquals('pending', $data['claim_status']);
    }

    public function test_insurance_claim_fails_without_insurance()
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

        // Act
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->id}/insurance-claim");

        // Assert
        $response->assertStatus(400)
            ->assertJsonStructure([
                'error' => [
                    'code',
                    'message',
                ]
            ]);

        $this->assertEquals('NO_INSURANCE_COVERAGE', $response->json('error.code'));
    }

    public function test_can_get_billing_history_for_patient()
    {
        // Arrange
        $patient = Patient::factory()->create();
        
        $visit1 = Visit::factory()->for($patient)->create();
        $visit2 = Visit::factory()->for($patient)->create();
        
        $invoice1 = Invoice::factory()->for($visit1)->create();
        $invoice2 = Invoice::factory()->for($visit2)->create();

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/patients/{$patient->id}/billing-summary");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    'patient_id',
                    'summary' => [
                        'total_invoices',
                        'total_billed',
                        'total_paid',
                        'total_outstanding',
                        'payment_rate',
                    ],
                    'invoices' => [
                        '*' => [
                            'id',
                            'code',
                            'date',
                            'total_amount',
                            'amount_paid',
                            'balance_due',
                            'payment_status',
                        ]
                    ]
                ]
            ]);

        $data = $response->json('data');
        $this->assertEquals($patient->id, $data['patient_id']);
        $this->assertEquals(2, $data['summary']['total_invoices']);
        $this->assertCount(2, $data['invoices']);
    }

    public function test_can_get_invoice_payments()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create();

        $payment1 = InvoicePayment::factory()->for($invoice)->create();
        $payment2 = InvoicePayment::factory()->for($invoice)->create();

        // Act
        $response = $this->actingAs($this->user)
            ->getJson("/api/v1/invoices/{$invoice->id}/payments");

        // Assert
        $response->assertStatus(200)
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'ulid',
                        'amount',
                        'payment_type',
                        'is_refund',
                        'payment_date',
                    ]
                ]
            ]);

        $this->assertCount(2, $response->json('data'));
    }

    public function test_payment_validation_rules()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create();

        $invalidData = [
            'amount' => -10.00, // Invalid: negative
            'payment_method_id' => 99999, // Invalid: doesn't exist
            'item_allocations' => [
                ['item_id' => 99999, 'amount' => 10.00] // Invalid: item doesn't exist
            ]
        ];

        // Act
        $response = $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/{$invoice->id}/payments", $invalidData);

        // Assert
        $response->assertStatus(422)
            ->assertJsonValidationErrors([
                'amount',
                'payment_method_id',
                'item_allocations.0.item_id',
            ]);
    }

    public function test_requires_authentication()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create();

        // Act & Assert
        $this->postJson("/api/v1/invoices/{$invoice->id}/payments", [])
            ->assertStatus(401);

        $this->getJson("/api/v1/invoices/{$invoice->id}/payment-summary")
            ->assertStatus(401);

        $this->getJson("/api/v1/patients/{$patient->id}/billing-summary")
            ->assertStatus(401);
    }

    public function test_handles_nonexistent_resources()
    {
        // Act & Assert
        $this->actingAs($this->user)
            ->postJson("/api/v1/invoices/99999/payments", [])
            ->assertStatus(404);

        $this->actingAs($this->user)
            ->getJson("/api/v1/invoices/99999/payment-summary")
            ->assertStatus(404);

        $this->actingAs($this->user)
            ->postJson("/api/v1/payments/99999/refund", [])
            ->assertStatus(404);
    }
}