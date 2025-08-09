# Software Requirements Specification (SRS)
# EMR Backend System for Cambodia Healthcare

## Version Information
- **Version**: 1.0
- **Date**: 2025
- **Target Framework**: Laravel 12
- **Language**: PHP 8.2+
- **Database**: MySQL/PostgreSQL

---

## Table of Contents

1. [Introduction & Overview](#1-introduction--overview)
2. [System Architecture](#2-system-architecture)
3. [Functional Requirements](#3-functional-requirements)
4. [Technical Requirements](#4-technical-requirements)
5. [Implementation Guidelines](#5-implementation-guidelines)
6. [Data Models Specification](#6-data-models-specification)
7. [Security Requirements](#7-security-requirements)
8. [Performance Requirements](#8-performance-requirements)
9. [Cambodia-Specific Requirements](#9-cambodia-specific-requirements)

---

## 1. Introduction & Overview

### 1.1 System Purpose and Scope

The Electronic Medical Record (EMR) Backend System is designed to serve Cambodia's healthcare infrastructure, providing a comprehensive digital platform for managing patient records, clinical workflows, and healthcare operations. The system supports the full spectrum of healthcare delivery from primary care clinics to tertiary hospitals.

### 1.2 Target Audience

**Primary Users:**
- Healthcare providers (doctors, nurses, medical assistants)
- Administrative staff (registration, billing, records management)
- Healthcare facility administrators
- System administrators
- Integration developers (third-party systems)

**Secondary Users:**
- Patients (limited access via patient portals)
- Government health officials (reporting and analytics)
- Insurance providers (claims processing)

### 1.3 Technology Stack Specifications

**Backend Framework:**
- Laravel 12.x
- PHP 8.2 or higher
- Composer for dependency management

**Database:**
- Primary: MySQL 8.0+ or PostgreSQL 14+
- Redis for caching and sessions
- Full-text search capabilities

**Authentication & Authorization:**
- Laravel Sanctum for API authentication
- Laravel Passport for OAuth2 (optional)
- Spatie Laravel Permission for role-based access control

**Additional Packages:**
- Spatie Laravel Activity Log for audit trails
- Spatie Laravel Query Builder for API filtering
- Dedoc Scramble for API documentation
- Laravel Socialite for social authentication

### 1.4 System Boundaries

**Included:**
- Patient registration and demographics management
- Clinical workflow management
- Medication management
- Laboratory and imaging orders
- Billing and invoicing
- Reporting and analytics
- Master data management
- User management and authentication

**Excluded:**
- Medical device integrations (HL7/FHIR - separate module)
- Payment processing gateways
- External laboratory system integrations
- Telemedicine capabilities

---

## 2. System Architecture

### 2.1 Overall System Design

The EMR system follows a modular, service-oriented architecture built on Laravel's MVC pattern with the following architectural principles:

- **RESTful API Design**: All client interactions via HTTP REST API
- **Domain-Driven Design**: Business logic organized by healthcare domains
- **Event-Driven Architecture**: Asynchronous processing for non-critical operations
- **Multi-tenancy**: Support for multiple healthcare facilities
- **Microservices Ready**: Modular structure for future service extraction

### 2.2 Laravel 12 Framework Requirements

**Core Components:**
- Eloquent ORM for database abstraction
- Laravel Artisan CLI for system management
- Laravel Queue for background job processing
- Laravel Cache for performance optimization
- Laravel Validation for input validation
- Laravel Middleware for request filtering

**Required Laravel Features:**
- Database migrations and seeders
- Model factories for testing
- API resources for data transformation
- Form requests for validation
- Service providers for dependency injection
- Event listeners for business logic decoupling

### 2.3 Database Design Specifications

**Database Architecture:**
- Normalized relational database design
- Soft delete support for all clinical data
- Complete audit trail tracking (created_by, updated_by, deleted_by)
- Timestamp tracking for all operations
- Foreign key constraints for referential integrity
- Indexing strategy for performance optimization

**Common Field Pattern:**
All primary business entities include:
```php
$table->timestamp('created_at')->useCurrent()->index();
$table->foreignId('created_by')->nullable()->index();
$table->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->index();
$table->foreignId('updated_by')->nullable()->index();
$table->softDeletes($column = 'deleted_at', $precision = 0)->index();
$table->foreignId('deleted_by')->nullable()->index();
```

### 2.4 API Architecture Patterns

**RESTful Design Principles:**
- Resource-based URLs (`/api/v1/patients`, `/api/v1/visits`)
- HTTP methods for operations (GET, POST, PUT, DELETE)
- JSON request/response format
- Consistent error response structure
- API versioning support (v1, v2, etc.)

**Authentication Pattern:**
- Bearer token authentication via Laravel Sanctum
- Device-specific tokens for mobile applications
- Token expiration and refresh mechanisms
- Rate limiting for API protection

**Response Standardization:**
```json
{
  "data": {},
  "meta": {
    "current_page": 1,
    "total": 100
  },
  "links": {
    "first": "url",
    "last": "url"
  }
}
```

---

## 3. Functional Requirements

### 3.1 Master Data Management

**FR-MD-001: Terminology Management**
- System shall manage medical terminologies and coding systems
- Support for ICD-10, ICD-11, and Cambodia-specific medical codes
- Hierarchical concept organization with parent-child relationships
- Multi-language support (English, Khmer)
- Version control for terminology updates

**FR-MD-002: Geographic Data Management**
- Complete Cambodia administrative hierarchy: Province → District → Commune → Village
- Gazetteer support for geographic locations
- Address standardization and validation
- GPS coordinate support for facilities

**FR-MD-003: Healthcare Facility Management**
- Organization hierarchy support
- Facility registration and licensing tracking
- Department and room management
- Service capability definition
- Operating hours and capacity management

### 3.2 Patient Management

**FR-PM-001: Patient Registration**
- Unique patient identification across the system
- Support for multiple identifier types (National ID, Passport, etc.)
- Patient demographics with full name support (Given, Family, Middle)
- Contact information management (phone, email, address)
- Emergency contact registration

**FR-PM-002: Patient Demographics**
- Birth date and age calculation
- Gender/sex designation
- Nationality and ethnicity tracking
- Language preferences
- Marital status and occupation
- Insurance information

**FR-PM-003: Patient Identity Management**
- Multiple identity document support
- Identity verification workflows
- Duplicate patient detection and merging
- Patient card issuance and management
- Photo identification support

### 3.3 Clinical Workflow Management

**FR-CW-001: Visit Management**
- Patient visit registration and scheduling
- Admission type classification (Emergency, Scheduled, etc.)
- Visit status tracking (Active, Discharged, Transferred)
- Discharge planning and documentation
- Visit outcome recording

**FR-CW-002: Encounter Management**
- Clinical encounter documentation
- Provider assignment and tracking
- Encounter type classification
- Service location recording
- Time and duration tracking

**FR-CW-003: Clinical Observations**
- Vital signs recording (Blood pressure, Temperature, etc.)
- Laboratory result documentation
- Clinical assessment notes
- Diagnosis and condition tracking
- Progress note documentation

### 3.4 Medication Management

**FR-MM-001: Medication Ordering**
- Electronic prescription creation
- Drug interaction checking
- Allergy alerts and contraindications
- Dosage calculation and validation
- Prescription modification tracking

**FR-MM-002: Medication Administration**
- Medication dispensing workflows
- Administration recording and verification
- Medication reconciliation
- Adverse reaction reporting
- Inventory management integration

### 3.5 Diagnostic Services

**FR-DS-001: Laboratory Services**
- Laboratory test ordering
- Specimen collection tracking
- Result reporting and interpretation
- Reference range validation
- Critical value alerts

**FR-DS-002: Imaging Services**
- Radiology order management
- Image study tracking
- Report generation and approval
- DICOM image reference support
- Comparison with previous studies

### 3.6 Financial Management

**FR-FM-001: Billing and Invoicing**
- Service charge calculation
- Insurance claim generation
- Payment processing integration
- Revenue reporting
- Accounts receivable management

**FR-FM-002: Cost Management**
- Service pricing management
- Insurance plan configuration
- Copayment calculation
- Financial assistance programs
- Cost reporting and analytics

---

## 4. Technical Requirements

### 4.1 Database Schema Specifications

**TR-DB-001: Entity Relationship Design**
All entities must follow standardized relationship patterns:
- Primary keys as auto-incrementing integers
- ULID support for external references
- Foreign key constraints with appropriate cascade rules
- Junction tables for many-to-many relationships
- JSON columns for flexible data structures

**TR-DB-002: Data Integrity Requirements**
- NOT NULL constraints on required fields
- CHECK constraints for data validation
- Unique constraints on business keys
- Default values for system fields
- Proper indexing for query performance

### 4.2 API Endpoint Definitions

**TR-API-001: Authentication Endpoints**
```
POST /api/v1/login
POST /api/v1/register
POST /api/v1/logout
GET  /api/v1/user
POST /api/v1/refresh
```

**TR-API-002: Patient Management Endpoints**
```
GET    /api/v1/patients
POST   /api/v1/patients
GET    /api/v1/patients/{id}
PUT    /api/v1/patients/{id}
DELETE /api/v1/patients/{id}
GET    /api/v1/patients/search/{query}
```

**TR-API-003: Clinical Workflow Endpoints**
```
GET    /api/v1/visits
POST   /api/v1/visits
GET    /api/v1/visits/{id}
PUT    /api/v1/visits/{id}
POST   /api/v1/visits/{id}/discharge
GET    /api/v1/encounters
POST   /api/v1/encounters
```

### 4.3 Authentication and Authorization

**TR-AUTH-001: Token-Based Authentication**
- Laravel Sanctum implementation
- Device-specific token generation
- Token expiration management (24 hours default)
- Refresh token support
- Multi-device login support

**TR-AUTH-002: Role-Based Access Control**
- Spatie Laravel Permission implementation
- Role hierarchy support
- Permission-based access control
- Context-aware permissions (facility-specific)
- Dynamic permission checking

### 4.4 Data Models and Relationships

**TR-DM-001: Model Structure Requirements**
- All models extend base Model class
- Implement HasFactory trait for testing
- Use SoftDeletes trait for clinical data
- Define fillable fields explicitly
- Implement proper relationship methods

**TR-DM-002: Relationship Patterns**
- BelongsTo for parent relationships
- HasMany for child collections
- BelongsToMany for many-to-many
- MorphMany for polymorphic relationships
- HasManyThrough for indirect relationships

---

## 5. Implementation Guidelines

### 5.1 Coding Standards

**IG-CS-001: PSR Compliance**
- PSR-4 autoloading standard
- PSR-12 extended coding style
- Laravel coding conventions
- Consistent naming conventions
- Proper documentation standards

**IG-CS-002: Code Organization**
- Controllers in app/Http/Controllers/V1/
- Models in app/Models/
- Resources in app/Http/Resources/
- Requests in app/Http/Requests/
- Services in app/Services/

### 5.2 Testing Requirements

**IG-TR-001: Test Coverage**
- Unit tests for all business logic
- Feature tests for API endpoints
- Model factory definitions
- Database seeder implementations
- Integration test scenarios

**IG-TR-002: Test Structure**
- PHPUnit for test framework
- Feature tests in tests/Feature/
- Unit tests in tests/Unit/
- Database testing with transactions
- Mock external dependencies

### 5.3 Security Considerations

**IG-SC-001: Input Validation**
- Validate all user inputs
- Sanitize data before storage
- Use Laravel validation rules
- Implement custom validation rules
- Error message standardization

**IG-SC-002: Data Protection**
- Encrypt sensitive patient data
- Implement audit logging
- Use secure password hashing
- HTTPS enforcement
- SQL injection prevention

### 5.4 Performance Requirements

**IG-PR-001: Database Optimization**
- Proper indexing strategy
- Query optimization
- Eager loading for relationships
- Database query monitoring
- Connection pooling

**IG-PR-002: Caching Strategy**
- Redis for session storage
- Cache frequently accessed data
- API response caching
- Database query caching
- Cache invalidation strategies

---

## 6. Data Models Specification

### 6.1 Core Entity Definitions

**Patient Entity**
```php
class Patient extends Model
{
    protected $fillable = ['code', 'facility_id'];
    
    // Relationships
    public function facility(): BelongsTo
    public function demographics(): HasOne
    public function addresses(): HasMany
    public function identities(): HasMany
    public function visits(): HasMany
    public function cards(): HasMany
}
```

**Visit Entity**
```php
class Visit extends Model
{
    protected $fillable = [
        'patient_id', 'facility_id', 'visit_type_id',
        'admission_type_id', 'admitted_at', 'discharged_at',
        'discharge_type_id', 'visit_outcome_id'
    ];
    
    protected $casts = [
        'admitted_at' => 'datetime',
        'discharged_at' => 'datetime'
    ];
}
```

### 6.2 Relationship Mappings

**Patient Relationships**
- Patient → Facility (BelongsTo)
- Patient → Demographics (HasOne)
- Patient → Addresses (HasMany)
- Patient → Identities (HasMany)
- Patient → Visits (HasMany)
- Patient → Cards (HasMany)

**Visit Relationships**
- Visit → Patient (BelongsTo)
- Visit → Facility (BelongsTo)
- Visit → Encounters (HasMany)
- Visit → Caretakers (BelongsToMany)
- Visit → Subjects (BelongsToMany)

### 6.3 Field Specifications with Types and Constraints

**Patients Table**
```sql
CREATE TABLE patients (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ulid CHAR(26) NOT NULL,
    code VARCHAR(255) NOT NULL,
    facility_id BIGINT UNSIGNED NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    
    INDEX idx_patients_ulid (ulid),
    INDEX idx_patients_code (code),
    INDEX idx_patients_facility_id (facility_id),
    FOREIGN KEY (facility_id) REFERENCES facilities(id)
);
```

**Patient Demographics Table**
```sql
CREATE TABLE patient_demographics (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    patient_id BIGINT UNSIGNED NOT NULL,
    name JSON NOT NULL,
    birthdate DATE NOT NULL,
    telecom JSON NULL,
    address JSON NULL,
    sex ENUM('Female', 'Male') NOT NULL,
    nationality_id BIGINT UNSIGNED NOT NULL,
    telephone VARCHAR(20) NULL,
    died_at DATETIME NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    
    INDEX idx_demographics_patient_id (patient_id),
    INDEX idx_demographics_birthdate (birthdate),
    INDEX idx_demographics_sex (sex),
    INDEX idx_demographics_nationality_id (nationality_id),
    INDEX idx_demographics_telephone (telephone),
    INDEX idx_demographics_died_at (died_at),
    FOREIGN KEY (patient_id) REFERENCES patients(id)
);
```

### 6.4 Business Rules for Each Entity

**Patient Business Rules**
- Each patient must be associated with a primary facility
- Patient code must be unique within the facility
- ULID must be globally unique
- Patient cannot be hard deleted if visits exist

**Visit Business Rules**
- Admitted date cannot be in the future
- Discharged date must be after admitted date
- Only one active visit per patient per facility
- Visit type and admission type are required
- Discharge type required only when discharged

**Encounter Business Rules**
- Must be associated with an active visit
- Cannot extend beyond visit date range
- Practitioner must have appropriate privileges
- Service location must be valid for the facility

---

## 7. Security Requirements

### 7.1 Authentication Security

**SR-AUTH-001: Password Security**
- Minimum 8 characters with complexity requirements
- Password hashing using Laravel's Hash facade
- Password history tracking (last 5 passwords)
- Account lockout after failed attempts
- Password expiration policies

**SR-AUTH-002: Session Management**
- Secure session configuration
- Session timeout after inactivity
- Single sign-on support
- Device registration and tracking
- Logout from all devices capability

### 7.2 Data Security

**SR-DATA-001: Encryption**
- Encrypt patient identifiable information
- Database encryption at rest
- TLS encryption in transit
- Key management and rotation
- Backup encryption

**SR-DATA-002: Access Control**
- Role-based access control implementation
- Principle of least privilege
- Context-aware permissions
- Audit trail for all access
- Permission inheritance rules

### 7.3 Compliance Requirements

**SR-COMP-001: Healthcare Data Protection**
- HIPAA-equivalent privacy protections
- Data anonymization capabilities
- Patient consent management
- Data retention policies
- Right to be forgotten implementation

---

## 8. Performance Requirements

### 8.1 Response Time Requirements

**PR-RT-001: API Response Times**
- Patient search: < 200ms for simple queries
- Patient registration: < 500ms
- Visit creation: < 300ms
- Clinical data entry: < 200ms
- Report generation: < 2 seconds

### 8.2 Scalability Requirements

**PR-SCALE-001: System Capacity**
- Support 10,000 concurrent users
- Handle 1 million patient records
- Process 100,000 visits per day
- Store 10 years of clinical data
- 99.9% uptime availability

### 8.3 Database Performance

**PR-DB-001: Query Performance**
- All queries under 100ms average
- Proper indexing on search fields
- Connection pooling implementation
- Query caching for static data
- Database monitoring and alerting

---

## 9. Cambodia-Specific Requirements

### 9.1 Language Support

**CSR-LANG-001: Multilingual Implementation**
- Primary language: Khmer
- Secondary language: English
- Unicode support for Khmer text
- Locale-specific date/time formatting
- Currency formatting (Riel/USD)

### 9.2 Regulatory Compliance

**CSR-REG-001: Healthcare Regulations**
- Ministry of Health guidelines compliance
- National health information standards
- Medical practice regulations
- Pharmaceutical regulations
- Quality assurance requirements

### 9.3 Administrative Structure

**CSR-ADMIN-001: Geographic Organization**
- 25 Provinces (Khet)
- 162 Districts (Srok/Khan)
- 1,652 Communes (Khum/Sangkat)
- 14,073 Villages (Phum)
- Administrative boundary validation

### 9.4 Healthcare System Integration

**CSR-HEALTH-001: National Integration**
- Health facility licensing integration
- Practitioner registration validation
- National patient identifier support
- Disease surveillance reporting
- Health statistics compilation

---

## 10. Appendices

### 10.1 Glossary of Terms

**Clinical Terms:**
- **EMR**: Electronic Medical Record
- **EHR**: Electronic Health Record
- **HL7**: Health Level 7 International
- **FHIR**: Fast Healthcare Interoperability Resources
- **ICD**: International Classification of Diseases

**Technical Terms:**
- **API**: Application Programming Interface
- **REST**: Representational State Transfer
- **CRUD**: Create, Read, Update, Delete
- **ORM**: Object-Relational Mapping
- **MVC**: Model-View-Controller

### 10.2 References

- Laravel 12 Documentation: https://laravel.com/docs
- Cambodia Administrative Divisions
- WHO Health Information Standards
- HL7 FHIR Specification
- Healthcare Data Security Guidelines

### 10.3 Document Control

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 1.0 | 2025 | Development Team | Initial version |

---

*This document serves as the comprehensive technical specification for implementing the EMR Backend System using Laravel 12 framework, specifically designed for Cambodia's healthcare infrastructure.*