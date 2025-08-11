<?php

namespace Tests\Unit\Actions;

use App\Actions\RecordPaymentAction;
use App\Models\Invoice;
use App\Models\InvoicePayment;
use App\Models\InvoiceItem;
use App\Models\Patient;
use App\Models\Visit;
use App\Models\Term;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RecordPaymentActionTest extends TestCase
{
    use RefreshDatabase;

    private RecordPaymentAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new RecordPaymentAction();
    }

    public function test_records_payment_for_invoice()
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
            'payment_date' => now(),
            'reference_number' => 'PAY123456',
            'notes' => 'Partial payment',
        ];

        // Act
        $payment = $this->action->execute($invoice, $paymentData);

        // Assert
        $this->assertInstanceOf(InvoicePayment::class, $payment);
        $this->assertEquals(50.00, $payment->amount);
        $this->assertEquals($paymentMethod->id, $payment->payment_method_id);
        $this->assertEquals('PAY123456', $payment->reference_number);
        $this->assertEquals('Partial payment', $payment->notes);

        // Check invoice updated
        $invoice->refresh();
        $this->assertEquals(50.00, $invoice->received);
        $this->assertEquals(40.00, $invoice->remaining_balance); // (100-10) - 50
    }

    public function test_validates_payment_amount_against_remaining_balance()
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

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount (20) cannot exceed remaining balance (10)');
        
        $this->action->execute($invoice, $paymentData);
    }

    public function test_validates_positive_payment_amount()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create();

        $paymentMethod = Term::factory()->create([
            'name' => 'Cash',
            'category' => 'payment_method'
        ]);

        $paymentData = [
            'amount' => -10.00, // Negative amount
            'payment_method_id' => $paymentMethod->id,
        ];

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Payment amount must be greater than zero');
        
        $this->action->execute($invoice, $paymentData);
    }

    public function test_allocates_payment_to_specific_items()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
            'discount' => 0.00,
            'received' => 0.00,
        ]);

        $item1 = InvoiceItem::factory()->for($invoice)->create([
            'quantity' => 1,
            'price' => 60.00,
            'paid' => 0.00,
        ]);

        $item2 = InvoiceItem::factory()->for($invoice)->create([
            'quantity' => 1,
            'price' => 40.00,
            'paid' => 0.00,
        ]);

        $paymentMethod = Term::factory()->create([
            'name' => 'Cash',
            'category' => 'payment_method'
        ]);

        $paymentData = [
            'amount' => 80.00,
            'payment_method_id' => $paymentMethod->id,
            'item_allocations' => [
                ['item_id' => $item1->id, 'amount' => 60.00],
                ['item_id' => $item2->id, 'amount' => 20.00],
            ],
        ];

        // Act
        $payment = $this->action->execute($invoice, $paymentData);

        // Assert
        $this->assertEquals(80.00, $payment->amount);

        $item1->refresh();
        $item2->refresh();
        
        $this->assertEquals(60.00, $item1->paid);
        $this->assertEquals(20.00, $item2->paid);
    }

    public function test_processes_refund()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
            'discount' => 0.00,
            'received' => 100.00,
        ]);

        $originalPayment = InvoicePayment::factory()->for($invoice)->create([
            'amount' => 100.00,
        ]);

        $refundData = [
            'amount' => 30.00,
            'refund_date' => now(),
            'reference_number' => 'REF123456',
            'notes' => 'Partial refund',
        ];

        // Act
        $refund = $this->action->processRefund($originalPayment, $refundData);

        // Assert
        $this->assertInstanceOf(InvoicePayment::class, $refund);
        $this->assertEquals(-30.00, $refund->amount);
        $this->assertEquals($originalPayment->id, $refund->original_payment_id);
        $this->assertEquals('REF123456', $refund->reference_number);
        $this->assertTrue($refund->is_refund);

        // Check invoice updated
        $invoice->refresh();
        $this->assertEquals(70.00, $invoice->received);
    }

    public function test_validates_refund_amount()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create();

        $originalPayment = InvoicePayment::factory()->for($invoice)->create([
            'amount' => 50.00,
        ]);

        $refundData = [
            'amount' => 60.00, // More than original payment
        ];

        // Act & Assert
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid refund amount');
        
        $this->action->processRefund($originalPayment, $refundData);
    }

    public function test_gets_payment_summary()
    {
        // Arrange
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->for($patient)->create();
        $invoice = Invoice::factory()->for($visit)->create([
            'total' => 100.00,
            'discount' => 10.00,
            'received' => 70.00,
        ]);

        // Create payments
        InvoicePayment::factory()->for($invoice)->create(['amount' => 50.00]);
        InvoicePayment::factory()->for($invoice)->create(['amount' => 30.00]);
        InvoicePayment::factory()->for($invoice)->create(['amount' => -10.00]); // Refund

        // Act
        $summary = $this->action->getPaymentSummary($invoice);

        // Assert
        $this->assertEquals($invoice->id, $summary['invoice_id']);
        $this->assertEquals(90.00, $summary['invoice_total']); // 100 - 10 discount
        $this->assertEquals(80.00, $summary['total_paid']); // 50 + 30
        $this->assertEquals(10.00, $summary['total_refunded']);
        $this->assertEquals(70.00, $summary['net_paid']); // 80 - 10
        $this->assertEquals(20.00, $summary['remaining_balance']); // 90 - 70
        $this->assertEquals('partial', $summary['payment_status']);
        $this->assertCount(3, $summary['payments']);
    }

    public function test_gets_billing_history_for_patient()
    {
        // Arrange
        $patient = Patient::factory()->create();
        
        $visit1 = Visit::factory()->for($patient)->create();
        $visit2 = Visit::factory()->for($patient)->create();
        
        $invoice1 = Invoice::factory()->for($visit1)->create([
            'total' => 100.00,
            'discount' => 10.00,
            'received' => 90.00,
        ]);
        
        $invoice2 = Invoice::factory()->for($visit2)->create([
            'total' => 200.00,
            'discount' => 20.00,
            'received' => 100.00,
        ]);

        // Act
        $history = $this->action->getBillingHistory($patient->id);

        // Assert
        $this->assertEquals($patient->id, $history['patient_id']);
        $this->assertEquals(2, $history['summary']['total_invoices']);
        $this->assertEquals(270.00, $history['summary']['total_billed']); // (100-10) + (200-20)
        $this->assertEquals(190.00, $history['summary']['total_paid']); // 90 + 100
        $this->assertEquals(80.00, $history['summary']['total_outstanding']); // 270 - 190
        $this->assertCount(2, $history['invoices']);
    }

    public function test_filters_billing_history_by_date_range()
    {
        // Arrange
        $patient = Patient::factory()->create();
        
        $visit1 = Visit::factory()->for($patient)->create();
        $visit2 = Visit::factory()->for($patient)->create();
        
        $invoice1 = Invoice::factory()->for($visit1)->create([
            'date' => now()->subDays(10),
        ]);
        
        $invoice2 = Invoice::factory()->for($visit2)->create([
            'date' => now()->subDays(5),
        ]);

        $options = [
            'from_date' => now()->subDays(7),
            'to_date' => now(),
        ];

        // Act
        $history = $this->action->getBillingHistory($patient->id, $options);

        // Assert
        $this->assertEquals(1, $history['summary']['total_invoices']);
        $this->assertCount(1, $history['invoices']);
        $this->assertEquals($invoice2->id, $history['invoices'][0]['id']);
    }

    public function test_filters_billing_history_by_payment_status()
    {
        // Arrange
        $patient = Patient::factory()->create();
        
        $visit1 = Visit::factory()->for($patient)->create();
        $visit2 = Visit::factory()->for($patient)->create();
        
        // Fully paid invoice
        $invoice1 = Invoice::factory()->for($visit1)->fullyPaid()->create();
        
        // Unpaid invoice
        $invoice2 = Invoice::factory()->for($visit2)->unpaid()->create();

        $options = ['payment_status' => 'paid'];

        // Act
        $history = $this->action->getBillingHistory($patient->id, $options);

        // Assert
        $this->assertEquals(1, $history['summary']['total_invoices']);
        $this->assertCount(1, $history['invoices']);
        $this->assertEquals($invoice1->id, $history['invoices'][0]['id']);
    }
}