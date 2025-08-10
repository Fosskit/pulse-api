# Implementation Plan

- [x] 1. Set up API versioning and base infrastructure






  - Create versioned route structure with v1 prefix for all endpoints
  - Implement base controller with standardized response methods
  - Set up API middleware for authentication, rate limiting, and CORS
  - Configure Scramble for OpenAPI documentation generation
  - _Requirements: All requirements need versioned API endpoints_

- [x] 2. Enhance Patient Management with comprehensive demographics and insurance





  - [x] 2.1 Extend Patient model with relationships and methods


    - Add relationships to demographics, addresses, identities, and visits
    - Implement activeInsurance() method for current coverage determination
    - Add search scopes for patient code, demographics, and identity codes
    - _Requirements: 1.1, 1.2, 1.6_

  - [x] 2.2 Create Patient API endpoints with Action-based architecture


    - Implement CreatePatientAction for patient registration with demographics
    - Create UpdatePatientAction for patient information updates
    - Build SearchPatientsAction with filtering by demographics and identities
    - Develop GetPatientDetailsAction for comprehensive patient data retrieval
    - _Requirements: 1.1, 1.2, 1.3, 1.5, 1.6_



  - [x] 2.3 Implement patient insurance and payment type logic

    - Create logic to determine payment_type_id based on active insurance cards
    - Implement support for multiple patient_identities with date validity
    - Add automatic beneficiary status detection for invoice generation
    - _Requirements: 1.2, 1.3, 1.4_

- [x] 3. Implement Cambodia Gazetteer address management




  - [x] 3.1 Create Gazetteer API endpoints with hierarchical filtering


    - Implement GetProvincesAction to retrieve all provinces
    - Create GetDistrictsByProvinceAction with parent_id filtering
    - Build GetCommunesByDistrictAction and GetVillagesByCommuneAction
    - Add address validation logic in GazetteerService
    - _Requirements: 2.1, 2.2, 2.3, 2.4_

  - [x] 3.2 Enhance PatientAddress model with gazetteer relationships


    - Add relationships to province, district, commune, and village gazetteers
    - Implement address search functionality through gazetteer relationships
    - Create address validation methods using gazetteer hierarchy
    - _Requirements: 2.5, 2.6_

- [x] 4. Build Facility Management system




  - [x] 4.1 Create facility, department, and room management endpoints


    - Implement GetFacilitiesAction for facility listing
    - Create GetFacilityDepartmentsAction and GetDepartmentRoomsAction
    - Build CheckRoomAvailabilityAction for scheduling support
    - _Requirements: 3.1, 3.2, 3.4_

  - [x] 4.2 Implement room availability and transfer logic


    - Create room availability checking for appointments and transfers
    - Implement patient transfer validation with destination room verification
    - Add facility utilization reporting functionality
    - _Requirements: 3.4, 3.5, 3.6_

- [x] 5. Develop Clinical Workflow Management





  - [x] 5.1 Implement visit management with admission and discharge


    - Create AdmitPatientAction to generate visit and initial encounter
    - Implement DischargePatientAction with discharge details and outcomes
    - Add visit timeline and status tracking functionality
    - _Requirements: 4.1, 4.5, 4.6_



  - [x] 5.2 Build encounter management system





    - Implement CreateEncounterAction for clinical activities
    - Create TransferPatientAction for department transfers
    - Build encounter linking to clinical forms and observations
    - Add encounter chronological display with timestamps
    - _Requirements: 4.2, 4.3, 4.4, 4.6_

- [ ] 6. Enhance Clinical Forms integration with encounter workflow
  - [ ] 6.1 Integrate existing ClinicalFormTemplate system with encounters
    - Create ProcessFormSubmissionAction to handle form data processing
    - Implement automatic observation generation from form submissions
    - Link form submissions to specific encounters and visits
    - _Requirements: 4.4, 9.2, 9.4_

  - [ ] 6.2 Build form data validation and observation creation
    - Implement ValidateFormDataAction using form schema validation rules
    - Create GenerateObservationsAction using existing FHIR mapping
    - Add form completion tracking and clinical documentation workflow
    - _Requirements: 9.1, 9.2, 9.3, 9.5, 9.6_

- [ ] 7. Implement Medication Management system
  - [ ] 7.1 Create medication prescription and instruction management
    - Implement CreatePrescriptionAction for medication requests
    - Build medication instruction creation with dosage scheduling
    - Add medication history retrieval for patient visits
    - _Requirements: 5.1, 5.2, 5.5_

  - [ ] 7.2 Build medication dispensing and administration tracking
    - Create DispenseMedicationAction for pharmacy dispensing
    - Implement medication administration recording (create medication_administrations table)
    - Add medication validation against allergies and drug interactions
    - _Requirements: 5.3, 5.4, 5.6_

- [ ] 8. Develop Service Request Management
  - [ ] 8.1 Create service request system for lab, imaging, and procedures
    - Implement CreateServiceRequestAction for different request types
    - Build service request linking to laboratory_requests, imaging_requests, procedures tables
    - Add service request status tracking and completion workflow
    - _Requirements: 6.1, 6.2, 6.3, 6.5, 6.6_

  - [ ] 8.2 Implement service results and completion tracking
    - Create UpdateServiceResultsAction for test results recording
    - Build result linking through observations table with reference ranges
    - Add pending request filtering and completion status updates
    - _Requirements: 6.4, 6.5, 6.6_

- [ ] 9. Build Billing and Invoice Management
  - [ ] 9.1 Create invoice generation based on services and insurance
    - Implement GenerateInvoiceAction for visit-based billing
    - Build automatic invoice_items creation for services provided
    - Add payment_type_id determination based on patient insurance status
    - _Requirements: 7.1, 7.2, 7.4_

  - [ ] 9.2 Implement insurance discount calculation and payment processing
    - Create CalculateDiscountsAction for insurance coverage discounts
    - Implement RecordPaymentAction for payment transaction processing
    - Build billing history retrieval with totals and remaining balances
    - Add insurance claims generation using patient identity data
    - _Requirements: 7.3, 7.4, 7.5, 7.6_

- [ ] 10. Develop comprehensive data export system
  - [ ] 10.1 Create visit export matching visit-sample.json structure
    - Implement ExportVisitAction to generate complete JSON documents
    - Build comprehensive data aggregation including all related entities
    - Add nested address structure with gazetteer information
    - Include patient identifications array with card details
    - _Requirements: 8.1, 8.2_

  - [ ] 10.2 Implement detailed clinical and service data export
    - Create export for all encounter types (outpatients, inpatients, emergencies, surgeries, progress_notes)
    - Build triages, vital signs, SOAP notes, and physical examination export
    - Add laboratory and imaging results with verification details
    - Include detailed invoice information with services and medications
    - _Requirements: 8.3, 8.4, 8.5, 8.6_

- [ ] 11. Implement comprehensive testing suite
  - [ ] 11.1 Create unit tests for all Action classes
    - Write tests for patient management actions
    - Create tests for clinical workflow actions
    - Build tests for medication and service request actions
    - Add tests for billing and export actions
    - _Requirements: All requirements need comprehensive testing_

  - [ ] 11.2 Build feature tests for all API endpoints
    - Create versioned API endpoint tests for v1 routes
    - Implement authentication and authorization testing
    - Build database interaction and JSON response validation tests
    - Add integration tests for complete clinical workflows
    - _Requirements: All requirements need API endpoint testing_

- [ ] 12. Set up comprehensive error handling and logging
  - Create standardized error response format with trace IDs
  - Implement global exception handler for consistent API responses
  - Add comprehensive logging for all clinical operations
  - Build error tracking and monitoring for production support
  - _Requirements: All requirements need proper error handling_

- [ ] 13. Configure security and authentication
  - Set up Laravel Passport OAuth2 authentication
  - Implement role-based access control using Spatie Laravel Permission
  - Add API rate limiting and request validation
  - Configure audit logging for all patient data access and modifications
  - _Requirements: All requirements need secure access control_

- [ ] 14. Create API documentation and deployment preparation
  - Generate comprehensive OpenAPI documentation using Scramble
  - Create API usage examples and integration guides
  - Set up database migrations for PostgreSQL deployment
  - Build deployment scripts and environment configuration
  - _Requirements: All requirements need proper documentation_