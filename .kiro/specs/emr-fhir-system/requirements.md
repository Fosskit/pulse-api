# Requirements Document

## Introduction

This document outlines the requirements for a comprehensive Electronic Medical Record (EMR) API system built on a simplified version of HL7 FHIR standards. The system is API-only with no user interface requirements. The system builds upon an existing Laravel-based foundation with established database structures for patients, visits, encounters, medications, service requests, billing, and Cambodia gazetteer support. The system will manage complete clinical workflows from patient registration through discharge, including patient demographics, insurance management through cards and identities, clinical encounters, medication management, service requests, billing, and facility management. The system will support Cambodia-specific addressing through the existing gazetteer integration and provide interoperability through JSON export capabilities.

### Existing Foundation

The system already has the following database structures in place:
- **Patient Management**: patients, patient_demographics, patient_addresses, patient_identities tables
- **Clinical Workflow**: visits, encounters, observations tables  
- **Medication Management**: medication_instructions, medication_requests, medication_dispenses tables
- **Service Requests**: service_requests, laboratory_requests, imaging_requests, procedures tables
- **Billing**: invoices, invoice_items tables
- **Facility Management**: facilities, departments, rooms, services tables
- **Address Support**: gazetteers table with Province/District/Commune/Village hierarchy
- **Insurance/Cards**: cards table linked to patient_identities for coverage tracking
- **Clinical Forms**: clinical_form_templates table for structured data capture

## Requirements

### 1. Patient Information Management

**User Story:** As a healthcare provider, I want to manage comprehensive patient information including demographics, insurance status, and payment types, so that I can provide appropriate care and ensure proper billing.

#### Acceptance Criteria

1. WHEN a new patient is registered THEN the system SHALL create records in patients and patient_demographics tables with FHIR-compliant structure
2. WHEN patient insurance information is provided THEN the system SHALL create card records and link them through patient_identities table
3. WHEN a patient has multiple insurance cards THEN the system SHALL support multiple patient_identities records with start/end date validity
4. IF a patient is a beneficiary THEN the system SHALL automatically determine payment_type_id for invoice generation
5. WHEN patient information is updated THEN the system SHALL use the existing commonFields() audit trail functionality
6. WHEN searching for patients THEN the system SHALL support search by patient code, demographics JSON fields, and identity codes

### 2. Cambodia Gazetteer Address Support

**User Story:** As a registration clerk, I want to use Cambodia's official gazetteer for patient addresses, so that I can ensure accurate and standardized address information.

#### Acceptance Criteria

1. WHEN entering patient address THEN the system SHALL provide dropdown selections from gazetteers table filtered by type='Province'
2. WHEN selecting province THEN the system SHALL filter gazetteers where parent_id equals selected province and type='District'
3. WHEN selecting district THEN the system SHALL filter gazetteers where parent_id equals selected district and type='Commune'
4. WHEN selecting commune THEN the system SHALL filter gazetteers where parent_id equals selected commune and type='Village'
5. WHEN address is saved THEN the system SHALL create patient_addresses record with province_id, district_id, commune_id, village_id references
6. WHEN searching by address THEN the system SHALL support search through gazetteer name fields and patient_addresses relationships

### 3. Facility Management

**User Story:** As a facility administrator, I want to manage departments, rooms, and services, so that I can efficiently organize healthcare delivery and resource allocation.

#### Acceptance Criteria

1. WHEN creating a department THEN the system SHALL use the existing departments table linked to facilities
2. WHEN adding rooms THEN the system SHALL use the existing rooms table with department relationships
3. WHEN managing services THEN the system SHALL use the existing services table with pricing and configuration
4. WHEN scheduling appointments THEN the system SHALL check room availability through existing room management
5. WHEN transferring patients THEN the system SHALL verify destination room availability and update encounter location
6. WHEN generating reports THEN the system SHALL provide utilization statistics using facility, department, and room data

### 4. Clinical Workflow Management

**User Story:** As a clinical staff member, I want to track every step of a patient's journey through encounters, so that I can maintain complete clinical documentation and continuity of care.

#### Acceptance Criteria

1. WHEN a patient is admitted THEN the system SHALL create a visit record with admitted_at timestamp and admission_type_id and an encounter is created
2. WHEN clinical activities occur THEN the system SHALL create encounter records linked to the visit with encounter_type_id and encounter_form_id
3. WHEN a patient is transferred between departments THEN the system SHALL create a new encounter with transfer encounter_type_id
4. WHEN clinical forms are completed THEN the system SHALL store form data using clinical_form_templates and create observations
5. WHEN a patient is discharged THEN the system SHALL update the visit record with discharged_at, discharge_type_id, and visit_outcome_id and an encounter is created
6. WHEN viewing patient history THEN the system SHALL display all encounters linked to visits in chronological order using started_at/ended_at

### 5. Medication Management

**User Story:** As a healthcare provider, I want to manage the complete medication lifecycle from prescription to administration, so that I can ensure safe and effective medication therapy.

#### Acceptance Criteria

1. WHEN a medication is prescribed THEN the system SHALL create medication_requests record linked to visit_id with status_id and intent_id
2. WHEN medication instructions are provided THEN the system SHALL create medication_instructions record with morning/afternoon/evening/night dosages and days
3. WHEN medication is administered THEN the system SHALL record administration details (implementation needed for medication_administrations table)
4. WHEN medication is dispensed THEN the system SHALL use the existing medication_dispenses table with quantity and dispenser information
5. WHEN checking medication history THEN the system SHALL display all medication_requests and dispenses for a patient's visits
6. WHEN prescribing medications THEN the system SHALL implement validation against patient allergies and drug interactions

### 6. Service Request Management

**User Story:** As a healthcare provider, I want to request and track laboratory tests, imaging studies, and procedures, so that I can obtain necessary diagnostic information for patient care.

#### Acceptance Criteria

1. WHEN a laboratory test is ordered THEN the system SHALL create service_requests record with request_type='Laboratory' and link to laboratory_requests table
2. WHEN an imaging study is requested THEN the system SHALL create service_requests record with request_type='Imaging' and link to imaging_requests table
3. WHEN a procedure is scheduled THEN the system SHALL create service_requests record with request_type='Procedure' and link to procedures table
4. WHEN service results are available THEN the system SHALL update completed_at timestamp and link results through observations table
5. WHEN viewing pending requests THEN the system SHALL filter service_requests by status_id for outstanding requests
6. WHEN a service request is completed THEN the system SHALL update status_id and completed_at fields

### 7. Billing and Invoice Management

**User Story:** As a billing clerk, I want to generate accurate invoices based on services provided and patient insurance coverage, so that I can ensure proper revenue collection and insurance claims processing.

#### Acceptance Criteria

1. WHEN services are provided THEN the system SHALL create invoice_items records linked to the visit's invoice
2. WHEN generating invoices THEN the system SHALL use the existing invoices table with payment_type_id based on patient insurance
3. WHEN patient has insurance coverage THEN the system SHALL calculate discounts using percentage_discount and amount_discount fields
4. WHEN processing payments THEN the system SHALL update the received field and calculate remaining balance
5. WHEN insurance claims are needed THEN the system SHALL generate claims using invoice and patient identity data
6. WHEN viewing billing history THEN the system SHALL display invoices and invoice_items with total, discount, and received amounts

### 8. Interoperability and Data Export

**User Story:** As a healthcare IT administrator, I want to export visit data in comprehensive JSON format matching the visit-sample.json structure, so that I can share complete patient information with other systems and ensure data portability.

#### Acceptance Criteria

1. WHEN exporting a visit THEN the system SHALL generate a complete JSON document including patient demographics, encounters, vital signs, medical histories, physical examinations, diagnoses, prescriptions, laboratory results, imaging results, and invoices
2. WHEN exporting patient data THEN the system SHALL include nested address structure with province/district/commune/village gazetteer information and patient identifications array
3. WHEN generating visit exports THEN the system SHALL include all encounter types (outpatients, inpatients, emergencies, surgeries, progress_notes) with proper timestamps and staff information
4. WHEN exporting clinical data THEN the system SHALL include triages, vital signs observations, SOAP notes, and structured physical examination findings
5. WHEN exporting service results THEN the system SHALL include laboratory and imaging results with proper categorization, reference ranges, and verification details
6. WHEN generating invoice exports THEN the system SHALL include detailed service and medication billing information with payment status and discount calculations

### 9. Observation and Clinical Forms

**User Story:** As a healthcare provider, I want to record clinical observations and complete structured forms, so that I can document patient assessments and treatment plans systematically.

#### Acceptance Criteria

1. WHEN recording vital signs THEN the system SHALL create records in the observations table with appropriate terminology codes
2. WHEN completing clinical forms THEN the system SHALL use clinical_form_templates and store responses as observations
3. WHEN entering lab results THEN the system SHALL create observations linked to laboratory service requests with reference ranges
4. WHEN documenting assessments THEN the system SHALL link observations to specific encounters through encounter_id
5. WHEN viewing patient data THEN the system SHALL display observations chronologically across all patient visits
6. WHEN creating custom forms THEN the system SHALL use the existing clinical_form_templates table for form configuration