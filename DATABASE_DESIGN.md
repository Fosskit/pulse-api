# Database Design Document
# EMR Backend System for Cambodia Healthcare

## Version Information
- **Version**: 1.0
- **Date**: 2025
- **Database Engine**: MySQL 8.0+ / PostgreSQL 14+
- **Framework**: Laravel 12

---

## Table of Contents

1. [Database Overview](#1-database-overview)
2. [Entity Relationship Diagram](#2-entity-relationship-diagram)
3. [Table Specifications](#3-table-specifications)
4. [Relationships and Constraints](#4-relationships-and-constraints)
5. [Indexing Strategy](#5-indexing-strategy)
6. [Data Types and Constraints](#6-data-types-and-constraints)
7. [Migration Implementation](#7-migration-implementation)

---

## 1. Database Overview

### 1.1 Database Architecture

The EMR database follows a normalized relational design with the following principles:
- Third Normal Form (3NF) compliance
- Referential integrity through foreign key constraints
- Soft delete implementation for all clinical data
- Complete audit trail for all operations
- JSON columns for flexible data structures where appropriate
- ULID support for external system integration

### 1.2 Common Patterns

**Standard Fields Pattern (commonFields)**
Every primary business entity includes these standard audit and lifecycle fields:

```sql
created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
created_by BIGINT UNSIGNED NULL,
updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
updated_by BIGINT UNSIGNED NULL,
deleted_at TIMESTAMP NULL,
deleted_by BIGINT UNSIGNED NULL
```

**Indexing Strategy:**
- Primary keys as auto-incrementing BIGINT
- Index on all foreign keys
- Index on commonly searched fields
- Index on temporal fields (dates, timestamps)
- Composite indexes for multi-column searches

### 1.3 Database Modules

The database is organized into logical modules:

1. **Master Data Module**: Terminologies, Concepts, Geographic data
2. **Healthcare Facility Module**: Organizations, Facilities, Departments
3. **Patient Module**: Patient demographics, addresses, identities
4. **Clinical Module**: Visits, Encounters, Observations, Conditions
5. **Medication Module**: Instructions, Requests, Dispensing
6. **Service Module**: Laboratory, Imaging, Procedures
7. **Financial Module**: Invoicing, Billing
8. **User Management Module**: Authentication, Authorization

---

## 2. Entity Relationship Diagram

### 2.1 Core Entities Overview

```
Organizations
    ↓
Facilities → Departments → Rooms
    ↓           ↓
Patients → Visits → Encounters → Observations
    ↓              ↓
Demographics   Conditions
    ↓
Addresses
```

### 2.2 Master Data Relationships

```
Terminologies → Concept Categories → Concepts → Terms
                                        ↓
                                  Used throughout system
```

### 2.3 Geographic Relationships

```
Provinces → Districts → Communes → Villages
                ↓
            Gazetteers (Used for addresses)
```

### 2.4 Clinical Workflow Relationships

```
Patient → Visit → Encounter → Observation
                    ↓            ↓
                Condition    Laboratory/Imaging
                    ↓
                Medication (Requests/Dispenses)
```

---

## 3. Table Specifications

### 3.1 Master Data Tables

#### 3.1.1 Terminologies Table
```sql
CREATE TABLE terminologies (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) NOT NULL UNIQUE,
    version VARCHAR(50) NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    
    INDEX idx_terminologies_code (code),
    INDEX idx_terminologies_active (is_active),
    INDEX idx_terminologies_created_at (created_at),
    INDEX idx_terminologies_created_by (created_by),
    INDEX idx_terminologies_updated_at (updated_at),
    INDEX idx_terminologies_updated_by (updated_by),
    INDEX idx_terminologies_deleted_at (deleted_at),
    INDEX idx_terminologies_deleted_by (deleted_by)
);
```

#### 3.1.2 Concept Categories Table
```sql
CREATE TABLE concept_categories (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    terminology_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(100) NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    
    INDEX idx_concept_categories_terminology_id (terminology_id),
    INDEX idx_concept_categories_code (code),
    INDEX idx_concept_categories_active (is_active),
    UNIQUE KEY uk_concept_categories_terminology_code (terminology_id, code),
    FOREIGN KEY (terminology_id) REFERENCES terminologies(id) ON DELETE CASCADE
);
```

#### 3.1.3 Concepts Table
```sql
CREATE TABLE concepts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL,
    system_id BIGINT UNSIGNED NOT NULL,
    concept_category_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    parent_id BIGINT UNSIGNED NULL,
    sort_order INT DEFAULT 0,
    is_active BOOLEAN DEFAULT TRUE,
    description TEXT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_concepts_code (code),
    INDEX idx_concepts_system_id (system_id),
    INDEX idx_concepts_concept_category_id (concept_category_id),
    INDEX idx_concepts_parent_id (parent_id),
    INDEX idx_concepts_active (is_active),
    UNIQUE KEY uk_concepts_category_code (concept_category_id, code),
    FOREIGN KEY (concept_category_id) REFERENCES concept_categories(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_id) REFERENCES concepts(id) ON DELETE SET NULL
);
```

#### 3.1.4 Terms Table
```sql
CREATE TABLE terms (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    concept_id BIGINT UNSIGNED NOT NULL,
    language_code VARCHAR(10) NOT NULL DEFAULT 'en',
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    is_active BOOLEAN DEFAULT TRUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_terms_concept_id (concept_id),
    INDEX idx_terms_language_code (language_code),
    INDEX idx_terms_active (is_active),
    UNIQUE KEY uk_terms_concept_language (concept_id, language_code),
    FOREIGN KEY (concept_id) REFERENCES concepts(id) ON DELETE CASCADE
);
```

### 3.2 Geographic Tables

#### 3.2.1 Provinces Table
```sql
CREATE TABLE provinces (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NULL,
    code VARCHAR(20) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_provinces_code (code),
    INDEX idx_provinces_name (name)
);
```

#### 3.2.2 Districts Table
```sql
CREATE TABLE districts (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    province_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NULL,
    code VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_districts_province_id (province_id),
    INDEX idx_districts_code (code),
    INDEX idx_districts_name (name),
    UNIQUE KEY uk_districts_province_code (province_id, code),
    FOREIGN KEY (province_id) REFERENCES provinces(id) ON DELETE CASCADE
);
```

#### 3.2.3 Communes Table
```sql
CREATE TABLE communes (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    district_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NULL,
    code VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_communes_district_id (district_id),
    INDEX idx_communes_code (code),
    INDEX idx_communes_name (name),
    UNIQUE KEY uk_communes_district_code (district_id, code),
    FOREIGN KEY (district_id) REFERENCES districts(id) ON DELETE CASCADE
);
```

#### 3.2.4 Villages Table
```sql
CREATE TABLE villages (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    commune_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(255) NOT NULL,
    name_en VARCHAR(255) NULL,
    code VARCHAR(20) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_villages_commune_id (commune_id),
    INDEX idx_villages_code (code),
    INDEX idx_villages_name (name),
    UNIQUE KEY uk_villages_commune_code (commune_id, code),
    FOREIGN KEY (commune_id) REFERENCES communes(id) ON DELETE CASCADE
);
```

#### 3.2.5 Gazetteers Table
```sql
CREATE TABLE gazetteers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    type VARCHAR(50) NOT NULL,
    name VARCHAR(255) NOT NULL,
    code VARCHAR(50) NULL,
    parent_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    
    INDEX idx_gazetteers_type (type),
    INDEX idx_gazetteers_code (code),
    INDEX idx_gazetteers_parent_id (parent_id),
    INDEX idx_gazetteers_name (name),
    FOREIGN KEY (parent_id) REFERENCES gazetteers(id) ON DELETE SET NULL
);
```

### 3.3 Healthcare Facility Tables

#### 3.3.1 Organizations Table
```sql
CREATE TABLE organizations (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ulid CHAR(26) NOT NULL UNIQUE,
    name VARCHAR(255) NOT NULL,
    identifier VARCHAR(100) UNIQUE,
    type VARCHAR(100) NULL,
    active BOOLEAN DEFAULT TRUE,
    telecom JSON NULL,
    address JSON NULL,
    contact JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    
    INDEX idx_organizations_ulid (ulid),
    INDEX idx_organizations_identifier (identifier),
    INDEX idx_organizations_active (active),
    INDEX idx_organizations_type (type),
    INDEX idx_organizations_created_at (created_at),
    INDEX idx_organizations_created_by (created_by),
    INDEX idx_organizations_updated_at (updated_at),
    INDEX idx_organizations_updated_by (updated_by),
    INDEX idx_organizations_deleted_at (deleted_at),
    INDEX idx_organizations_deleted_by (deleted_by)
);
```

#### 3.3.2 Facilities Table
```sql
CREATE TABLE facilities (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(255) NOT NULL UNIQUE,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    
    INDEX idx_facilities_code (code),
    INDEX idx_facilities_created_at (created_at),
    INDEX idx_facilities_created_by (created_by),
    INDEX idx_facilities_updated_at (updated_at),
    INDEX idx_facilities_updated_by (updated_by),
    INDEX idx_facilities_deleted_at (deleted_at),
    INDEX idx_facilities_deleted_by (deleted_by)
);
```

### 3.4 Patient Management Tables

#### 3.4.1 Patients Table
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
    INDEX idx_patients_created_at (created_at),
    INDEX idx_patients_created_by (created_by),
    INDEX idx_patients_updated_at (updated_at),
    INDEX idx_patients_updated_by (updated_by),
    INDEX idx_patients_deleted_at (deleted_at),
    INDEX idx_patients_deleted_by (deleted_by),
    FOREIGN KEY (facility_id) REFERENCES facilities(id)
);
```

#### 3.4.2 Patient Demographics Table
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
    FOREIGN KEY (patient_id) REFERENCES patients(id) ON DELETE CASCADE
);
```

### 3.5 Clinical Workflow Tables

#### 3.5.1 Visits Table
```sql
CREATE TABLE visits (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    ulid CHAR(26) NOT NULL,
    patient_id BIGINT UNSIGNED NOT NULL,
    facility_id BIGINT UNSIGNED NOT NULL,
    visit_type_id BIGINT UNSIGNED NULL,
    admission_type_id BIGINT UNSIGNED NOT NULL,
    admitted_at DATETIME NOT NULL,
    discharged_at DATETIME NULL,
    discharge_type_id BIGINT UNSIGNED NULL,
    visit_outcome_id BIGINT UNSIGNED NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    
    INDEX idx_visits_ulid (ulid),
    INDEX idx_visits_patient_id (patient_id),
    INDEX idx_visits_facility_id (facility_id),
    INDEX idx_visits_visit_type_id (visit_type_id),
    INDEX idx_visits_admission_type_id (admission_type_id),
    INDEX idx_visits_admitted_at (admitted_at),
    INDEX idx_visits_discharged_at (discharged_at),
    INDEX idx_visits_discharge_type_id (discharge_type_id),
    INDEX idx_visits_visit_outcome_id (visit_outcome_id),
    FOREIGN KEY (patient_id) REFERENCES patients(id),
    FOREIGN KEY (facility_id) REFERENCES facilities(id) ON DELETE CASCADE,
    FOREIGN KEY (visit_type_id) REFERENCES terms(id) ON DELETE CASCADE,
    FOREIGN KEY (admission_type_id) REFERENCES terms(id) ON DELETE CASCADE,
    FOREIGN KEY (discharge_type_id) REFERENCES terms(id) ON DELETE CASCADE,
    FOREIGN KEY (visit_outcome_id) REFERENCES terms(id) ON DELETE CASCADE
);
```

#### 3.5.2 Encounters Table
```sql
CREATE TABLE encounters (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    visit_id BIGINT UNSIGNED NOT NULL,
    practitioner_id BIGINT UNSIGNED NULL,
    department_id BIGINT UNSIGNED NULL,
    room_id BIGINT UNSIGNED NULL,
    encounter_type_id BIGINT UNSIGNED NOT NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    class_id BIGINT UNSIGNED NOT NULL,
    priority_id BIGINT UNSIGNED NULL,
    started_at DATETIME NOT NULL,
    ended_at DATETIME NULL,
    reason JSON NULL,
    diagnosis JSON NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    created_by BIGINT UNSIGNED NULL,
    updated_at TIMESTAMP NULL ON UPDATE CURRENT_TIMESTAMP,
    updated_by BIGINT UNSIGNED NULL,
    deleted_at TIMESTAMP NULL,
    deleted_by BIGINT UNSIGNED NULL,
    
    INDEX idx_encounters_visit_id (visit_id),
    INDEX idx_encounters_practitioner_id (practitioner_id),
    INDEX idx_encounters_department_id (department_id),
    INDEX idx_encounters_room_id (room_id),
    INDEX idx_encounters_type_id (encounter_type_id),
    INDEX idx_encounters_status_id (status_id),
    INDEX idx_encounters_class_id (class_id),
    INDEX idx_encounters_priority_id (priority_id),
    INDEX idx_encounters_started_at (started_at),
    INDEX idx_encounters_ended_at (ended_at),
    FOREIGN KEY (visit_id) REFERENCES visits(id) ON DELETE CASCADE,
    FOREIGN KEY (practitioner_id) REFERENCES practitioners(id),
    FOREIGN KEY (department_id) REFERENCES departments(id),
    FOREIGN KEY (room_id) REFERENCES rooms(id),
    FOREIGN KEY (encounter_type_id) REFERENCES terms(id),
    FOREIGN KEY (status_id) REFERENCES terms(id),
    FOREIGN KEY (class_id) REFERENCES terms(id),
    FOREIGN KEY (priority_id) REFERENCES terms(id)
);
```

---

## 4. Relationships and Constraints

### 4.1 Primary Key Constraints

All tables use auto-incrementing BIGINT UNSIGNED primary keys:
- Provides sufficient range for large-scale systems
- Ensures uniqueness and order
- Optimizes join performance

### 4.2 Foreign Key Relationships

**Cascade Rules:**
- `ON DELETE CASCADE`: Child records are automatically deleted
- `ON DELETE SET NULL`: Foreign key is set to NULL
- `ON DELETE RESTRICT`: Prevents deletion if references exist

**Key Relationships:**
- Patient → Facility (RESTRICT)
- Visit → Patient (RESTRICT)
- Visit → Facility (CASCADE)
- Encounter → Visit (CASCADE)
- Observation → Encounter (CASCADE)
- Condition → Encounter (CASCADE)

### 4.3 Unique Constraints

**Business Key Uniqueness:**
- Patient code unique within facility
- Facility code globally unique
- Practitioner code globally unique
- Identity values unique within type and system

### 4.4 Check Constraints

**Data Validation:**
- Discharge date must be after admission date
- End dates must be after start dates
- Effective dates cannot be in the future
- Age calculation from birthdate must be reasonable

---

## 5. Indexing Strategy

### 5.1 Primary Indexes

**Auto-created indexes:**
- Primary key indexes on all tables
- Unique constraint indexes
- Foreign key indexes

### 5.2 Secondary Indexes

**Search Optimization:**
- Patient name search (JSON functional index)
- Date range queries (temporal columns)
- Status-based filtering
- Geographic location searches

**Composite Indexes:**
```sql
-- Patient search by facility and code
CREATE INDEX idx_patients_facility_code ON patients(facility_id, code);

-- Visit date range queries
CREATE INDEX idx_visits_facility_date ON visits(facility_id, admitted_at);

-- Active encounters by practitioner
CREATE INDEX idx_encounters_practitioner_active ON encounters(practitioner_id, status_id, started_at);
```

### 5.3 JSON Indexes

**MySQL 8.0+ JSON functional indexes:**
```sql
-- Patient name search
CREATE INDEX idx_demographics_given_name ON patient_demographics((JSON_UNQUOTE(JSON_EXTRACT(name, '$.given[0]'))));

-- Practitioner name search
CREATE INDEX idx_practitioners_family_name ON practitioners((JSON_UNQUOTE(JSON_EXTRACT(name, '$.family'))));
```

### 5.4 Full-Text Indexes

**Text Search Optimization:**
```sql
-- Clinical notes search
CREATE FULLTEXT INDEX ft_observations_note ON observations(note);

-- Condition descriptions
CREATE FULLTEXT INDEX ft_conditions_note ON conditions(note);
```

---

## 6. Data Types and Constraints

### 6.1 Standard Data Types

**Numeric Types:**
- `BIGINT UNSIGNED`: Primary keys, foreign keys
- `INT`: Counters, small numbers
- `DECIMAL(10,2)`: Currency amounts
- `BOOLEAN`: True/false flags

**String Types:**
- `VARCHAR(255)`: Names, descriptions
- `VARCHAR(100)`: Codes, identifiers
- `VARCHAR(20)`: Phone numbers, postal codes
- `TEXT`: Long descriptions, notes
- `CHAR(26)`: ULID values

**Temporal Types:**
- `TIMESTAMP`: Audit timestamps
- `DATETIME`: Clinical timestamps
- `DATE`: Birth dates, start/end dates

**Structured Types:**
- `JSON`: Complex data structures
- `ENUM`: Fixed value lists

### 6.2 JSON Structure Specifications

**Patient Name JSON:**
```json
{
  "use": "official",
  "family": "Surname",
  "given": ["FirstName", "MiddleName"],
  "prefix": ["Mr.", "Dr."],
  "suffix": ["Jr.", "III"]
}
```

**Address JSON:**
```json
{
  "use": "home",
  "type": "postal",
  "line": ["123 Main St", "Apt 4B"],
  "city": "Phnom Penh",
  "district": "Daun Penh",
  "province": "Phnom Penh",
  "postalCode": "12000",
  "country": "KH"
}
```

**Telecom JSON:**
```json
[
  {
    "system": "phone",
    "value": "+855123456789",
    "use": "mobile",
    "rank": 1
  },
  {
    "system": "email",
    "value": "patient@example.com",
    "use": "work",
    "rank": 2
  }
]
```

---

## 7. Migration Implementation

### 7.1 Migration Order

**Foundation Migrations:**
1. User and authentication tables
2. Master data tables (terminologies, concepts)
3. Geographic tables (provinces, districts, communes, villages)
4. Organization and facility tables
5. Patient tables
6. Practitioner tables
7. Clinical workflow tables
8. Service and medication tables
9. Financial tables
10. Audit and logging tables

### 7.2 Laravel Migration Examples

**Base Migration Pattern:**
```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('table_name', function (Blueprint $table) {
            $table->id();
            // Business fields
            $table->commonFields();
            
            // Indexes
            // Foreign keys
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('table_name');
    }
};
```

**Common Fields Macro:**
```php
// In AppServiceProvider boot() method
Blueprint::macro('commonFields', function () {
    $this->timestamp('created_at')->useCurrent()->index();
    $this->foreignId('created_by')->nullable()->index();
    $this->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->index();
    $this->foreignId('updated_by')->nullable()->index();
    $this->softDeletes($column = 'deleted_at', $precision = 0)->index();
    $this->foreignId('deleted_by')->nullable()->index();
});
```

---

This database design provides a comprehensive foundation for the EMR system, ensuring data integrity, performance, and scalability while supporting Cambodia's specific healthcare requirements.