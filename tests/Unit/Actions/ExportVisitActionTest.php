<?php

namespace Tests\Unit\Actions;

use App\Actions\ExportVisitAction;
use App\Models\Card;
use App\Models\Encounter;
use App\Models\Facility;
use App\Models\Gazetteer;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\MedicationInstruction;
use App\Models\MedicationRequest;
use App\Models\Observation;
use App\Models\Patient;
use App\Models\PatientAddress;
use App\Models\PatientDemographic;
use App\Models\PatientIdentity;
use App\Models\ServiceRequest;
use App\Models\Term;
use App\Models\Visit;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExportVisitActionTest extends TestCase
{
    use RefreshDatabase;

    private ExportVisitAction $action;

    protected function setUp(): void
    {
        parent::setUp();
        $this->action = new ExportVisitAction();
    }

    public function test_exports_visit_with_comprehensive_data()
    {
        // Create test data
        $facility = Facility::factory()->create(['code' => '121020']);
        
        $patient = Patient::factory()->create(['code' => 'P25003877']);
        
        $demographics = PatientDemographic::factory()->alive()->create([
            'patient_id' => $patient->id,
            'name' => [
                'family' => 'Smith',
                'given' => ['John']
            ],
            'sex' => 'Male',
            'birthdate' => '1980-05-15',
            'telephone' => '012 345 678'
        ]);

        // Create gazetteer hierarchy
        $root = Gazetteer::factory()->create([
            'code' => '0',
            'name' => 'Root',
            'type' => 'Province',
            'parent_id' => 1
        ]);
        
        $province = Gazetteer::factory()->create([
            'code' => '12',
            'name' => 'Phnom Penh',
            'type' => 'Province',
            'parent_id' => $root->id
        ]);
        
        $district = Gazetteer::factory()->create([
            'code' => '1201',
            'name' => 'Chamkar Mon',
            'type' => 'District',
            'parent_id' => $province->id
        ]);
        
        $commune = Gazetteer::factory()->create([
            'code' => '120101',
            'name' => 'Tonle Bassac',
            'type' => 'Commune',
            'parent_id' => $district->id
        ]);
        
        $village = Gazetteer::factory()->create([
            'code' => '12010101',
            'name' => 'Phsar Deum Thkov',
            'type' => 'Village',
            'parent_id' => $commune->id
        ]);

        $address = PatientAddress::factory()->create([
            'patient_id' => $patient->id,
            'province_id' => $province->id,
            'district_id' => $district->id,
            'commune_id' => $commune->id,
            'village_id' => $village->id,
            'street_address' => '45 Street 302, Near Central Market 1',
            'is_current' => true
        ]);

        // Create patient identity with card
        $cardType = Term::factory()->create(['name' => 'National ID']);
        $card = Card::factory()->create(['card_type_id' => $cardType->id]);
        
        $identity = PatientIdentity::factory()->create([
            'patient_id' => $patient->id,
            'card_id' => $card->id,
            'code' => 'ID123456789'
        ]);

        // Create visit with types
        $visitType = Term::factory()->create(['name' => 'OPD']);
        $admissionType = Term::factory()->create(['name' => 'Self Refer']);
        $dischargeType = Term::factory()->create(['name' => 'Authorized']);
        $visitOutcome = Term::factory()->create(['name' => 'Improved']);

        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'visit_type_id' => $visitType->id,
            'admission_type_id' => $admissionType->id,
            'discharge_type_id' => $dischargeType->id,
            'visit_outcome_id' => $visitOutcome->id,
            'ulid' => 'V420650',
            'admitted_at' => '2025-03-17 09:30:00',
            'discharged_at' => '2025-03-17 10:45:00'
        ]);

        // Create encounters
        $triageType = Term::factory()->create(['name' => 'Triage']);
        $outpatientType = Term::factory()->create(['name' => 'Outpatient']);

        $triageEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'encounter_type_id' => $triageType->id,
            'ulid' => 'TR250317',
            'started_at' => '2025-03-17 09:32:00'
        ]);

        $outpatientEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'encounter_type_id' => $outpatientType->id,
            'ulid' => 'E417001',
            'started_at' => '2025-03-17 09:35:00',
            'ended_at' => '2025-03-17 10:15:00'
        ]);

        // Create medication instruction first
        $medicationInstruction = MedicationInstruction::factory()->create([
            'morning' => 4,
            'afternoon' => 0,
            'evening' => 4,
            'night' => 0,
            'days' => 3,
            'note' => 'Take with food. Complete full course even if feeling better.'
        ]);

        // Create medication request with instructions
        $medicationRequest = MedicationRequest::factory()->create([
            'visit_id' => $visit->id,
            'instruction_id' => $medicationInstruction->id,
            'ulid' => 'RX20250317001'
        ]);

        // Create service request
        $serviceRequest = ServiceRequest::factory()->create([
            'visit_id' => $visit->id,
            'request_type' => 'Laboratory',
            'ulid' => 'EL23222',
            'ordered_at' => '2025-03-17 10:30:00'
        ]);

        // Create invoice
        $invoice = Invoice::factory()->create([
            'visit_id' => $visit->id,
            'ulid' => 'INV20250317001',
            'total' => 120000
        ]);

        $service = \App\Models\Service::factory()->create(['name' => 'Blood Smear for Malaria']);
        $invoiceItem = InvoiceItem::factory()->create([
            'invoice_id' => $invoice->id,
            'invoiceable_id' => $service->id,
            'invoiceable_type' => \App\Models\Service::class,
            'price' => 15000,
            'quantity' => 1
        ]);

        // Execute the action
        $result = $this->action->execute($visit->id);

        // Assertions
        $this->assertArrayHasKey('visits', $result);
        $this->assertCount(1, $result['visits']);

        $exportedVisit = $result['visits'][0];

        // Test visit basic data
        $this->assertEquals('121020', $exportedVisit['health_facility_code']);
        $this->assertEquals('P25003877', $exportedVisit['patient_code']);
        $this->assertEquals('V420650', $exportedVisit['code']);
        $this->assertEquals('Self Refer', $exportedVisit['admission_type']);
        $this->assertEquals('Authorized', $exportedVisit['discharge_type']);
        $this->assertEquals('Improved', $exportedVisit['visit_outcome']);
        $this->assertEquals('OPD', $exportedVisit['visit_type']);

        // Test patient data
        $patient = $exportedVisit['patient'];
        $this->assertEquals('P25003877', $patient['code']);
        $this->assertEquals('Smith', $patient['surname']);
        $this->assertEquals('John', $patient['name']);
        $this->assertEquals('M', $patient['sex']);
        $this->assertEquals('1980-05-15', $patient['birthdate']);
        $this->assertEquals('012 345 678', $patient['phone']);

        // Test address structure
        $address = $patient['address'];
        $this->assertEquals('12', $address['province']['code']);
        $this->assertEquals('Phnom Penh', $address['province']['name']);
        $this->assertEquals('1201', $address['district']['code']);
        $this->assertEquals('Chamkar Mon', $address['district']['name']);
        $this->assertEquals('120101', $address['commune']['code']);
        $this->assertEquals('Tonle Bassac', $address['commune']['name']);
        $this->assertEquals('12010101', $address['village']['code']);
        $this->assertEquals('Phsar Deum Thkov', $address['village']['name']);

        // Test identifications
        $this->assertCount(1, $patient['identifications']);
        $identification = $patient['identifications'][0];
        $this->assertEquals('P25003877', $identification['patient_code']);
        $this->assertEquals('ID123456789', $identification['card_code']);
        $this->assertEquals('National ID', $identification['card_type']);

        // Test encounters
        $this->assertCount(1, $exportedVisit['triages']);
        $this->assertCount(1, $exportedVisit['outpatients']);

        // Test prescriptions
        $this->assertCount(1, $exportedVisit['prescriptions']);
        $prescription = $exportedVisit['prescriptions'][0];
        $this->assertEquals('RX20250317001', $prescription['code']);
        $this->assertCount(1, $prescription['medications']);
        
        $medication = $prescription['medications'][0];
        $this->assertEquals(4, $medication['morning']);
        $this->assertEquals(0, $medication['afternoon']);
        $this->assertEquals(4, $medication['evening']);
        $this->assertEquals(0, $medication['night']);
        $this->assertEquals(3, $medication['days']);

        // Test invoices
        $this->assertCount(1, $exportedVisit['invoices']);
        $invoice = $exportedVisit['invoices'][0];
        $this->assertEquals('INV20250317001', $invoice['code']);
        $this->assertEquals(120000, $invoice['total']);
        $this->assertCount(1, $invoice['services']);
        
        $service = $invoice['services'][0];
        $this->assertEquals('Blood Smear for Malaria', $service['service_name']);
        $this->assertEquals(15000, $service['price']);
    }

    public function test_handles_missing_visit()
    {
        $this->expectException(\Illuminate\Database\Eloquent\ModelNotFoundException::class);
        
        $this->action->execute(999);
    }

    public function test_exports_visit_with_minimal_data()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create();
        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id
        ]);

        $result = $this->action->execute($visit->id);

        $this->assertArrayHasKey('visits', $result);
        $this->assertCount(1, $result['visits']);
        
        $exportedVisit = $result['visits'][0];
        $this->assertEquals($patient->code, $exportedVisit['patient_code']);
        $this->assertEquals($visit->ulid, $exportedVisit['code']);
        
        // Should handle null relationships gracefully
        $this->assertIsArray($exportedVisit['triages']);
        $this->assertIsArray($exportedVisit['vital_signs']);
        $this->assertIsArray($exportedVisit['prescriptions']);
        $this->assertIsArray($exportedVisit['invoices']);
    }

    public function test_exports_enhanced_clinical_data()
    {
        // Create test data with enhanced clinical information
        $facility = Facility::factory()->create(['code' => '121020']);
        $patient = Patient::factory()->create(['code' => 'P25003877']);
        
        $demographics = PatientDemographic::factory()->alive()->create([
            'patient_id' => $patient->id,
            'name' => [
                'family' => 'Smith',
                'given' => ['John']
            ],
            'sex' => 'Male',
            'birthdate' => '1980-05-15',
            'telephone' => '012 345 678'
        ]);

        $visit = Visit::factory()->create([
            'patient_id' => $patient->id,
            'facility_id' => $facility->id,
            'ulid' => 'V420650'
        ]);

        // Create encounters with observations
        $triageType = Term::factory()->create(['name' => 'Triage']);
        $outpatientType = Term::factory()->create(['name' => 'Outpatient']);

        $triageEncounter = Encounter::factory()->create([
            'visit_id' => $visit->id,
            'encounter_type_id' => $triageType->id,
            'ulid' => 'TR250317',
            'started_at' => '2025-03-17 09:32:00'
        ]);

        // Create vital signs observations
        $tempConcept = \App\Models\Concept::factory()->create(['name' => 'Temperature']);
        $bpSystolicConcept = \App\Models\Concept::factory()->create(['name' => 'Blood Pressure Systolic']);
        
        Observation::factory()->create([
            'encounter_id' => $triageEncounter->id,
            'patient_id' => $patient->id,
            'concept_id' => $tempConcept->id,
            'value_number' => 38.7
        ]);

        Observation::factory()->create([
            'encounter_id' => $triageEncounter->id,
            'patient_id' => $patient->id,
            'concept_id' => $bpSystolicConcept->id,
            'value_number' => 135
        ]);

        // Create SOAP note observations
        $subjectiveConcept = \App\Models\Concept::factory()->create(['name' => 'Subjective']);
        $objectiveConcept = \App\Models\Concept::factory()->create(['name' => 'Objective']);
        
        Observation::factory()->create([
            'encounter_id' => $triageEncounter->id,
            'patient_id' => $patient->id,
            'concept_id' => $subjectiveConcept->id,
            'value_string' => 'Patient reports fever has decreased after first dose of medication.'
        ]);

        Observation::factory()->create([
            'encounter_id' => $triageEncounter->id,
            'patient_id' => $patient->id,
            'concept_id' => $objectiveConcept->id,
            'value_string' => 'Temperature 37.8°C (down from 38.7°C). Heart rate 85/min.'
        ]);

        // Execute the action
        $result = $this->action->execute($visit->id);

        // Assertions
        $this->assertArrayHasKey('visits', $result);
        $exportedVisit = $result['visits'][0];

        // Test enhanced vital signs export
        $this->assertIsArray($exportedVisit['vital_signs']);
        if (count($exportedVisit['vital_signs']) > 0) {
            $vitalSigns = $exportedVisit['vital_signs'][0];
            $this->assertArrayHasKey('observations', $vitalSigns);
            $this->assertIsArray($vitalSigns['observations']);
        }

        // Test enhanced SOAP notes export
        $this->assertIsArray($exportedVisit['soaps']);
        if (count($exportedVisit['soaps']) > 0) {
            $soap = $exportedVisit['soaps'][0];
            $this->assertArrayHasKey('subjective', $soap);
            $this->assertArrayHasKey('objective', $soap);
            $this->assertArrayHasKey('assessment', $soap);
            $this->assertArrayHasKey('plan', $soap);
        }

        // Test that all required sections are present
        $this->assertArrayHasKey('triages', $exportedVisit);
        $this->assertArrayHasKey('vital_signs', $exportedVisit);
        $this->assertArrayHasKey('medical_histories', $exportedVisit);
        $this->assertArrayHasKey('physical_examinations', $exportedVisit);
        $this->assertArrayHasKey('outpatients', $exportedVisit);
        $this->assertArrayHasKey('inpatients', $exportedVisit);
        $this->assertArrayHasKey('emergencies', $exportedVisit);
        $this->assertArrayHasKey('surgeries', $exportedVisit);
        $this->assertArrayHasKey('progress_notes', $exportedVisit);
        $this->assertArrayHasKey('soaps', $exportedVisit);
        $this->assertArrayHasKey('laboratories', $exportedVisit);
        $this->assertArrayHasKey('imageries', $exportedVisit);
        $this->assertArrayHasKey('diagnosis', $exportedVisit);
        $this->assertArrayHasKey('prescriptions', $exportedVisit);
        $this->assertArrayHasKey('referrals', $exportedVisit);
        $this->assertArrayHasKey('invoices', $exportedVisit);
    }
}