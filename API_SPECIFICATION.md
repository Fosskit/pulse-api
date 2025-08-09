# API Specification Document
# EMR Backend System for Cambodia Healthcare

## Version Information
- **Version**: 1.0
- **Date**: 2025
- **API Version**: v1
- **Framework**: Laravel 12
- **Authentication**: Laravel Sanctum

---

## Table of Contents

1. [API Overview](#1-api-overview)
2. [Authentication](#2-authentication)
3. [Request/Response Format](#3-requestresponse-format)
4. [Error Handling](#4-error-handling)
5. [Endpoint Specifications](#5-endpoint-specifications)
6. [Data Validation](#6-data-validation)
7. [Rate Limiting](#7-rate-limiting)
8. [API Testing](#8-api-testing)

---

## 1. API Overview

### 1.1 Base URL Structure

**Production:** `https://api.emr.example.com/api/v1`
**Development:** `http://localhost:8000/api/v1`

### 1.2 API Design Principles

- **RESTful Architecture**: Standard HTTP methods and status codes
- **Resource-Based URLs**: Clear, predictable endpoint structure
- **JSON Communication**: All requests and responses in JSON format
- **Stateless**: Each request contains all necessary information
- **Versioned**: API versioning through URL path (`/v1/`)
- **Consistent**: Standardized response formats across all endpoints

### 1.3 Supported HTTP Methods

- `GET`: Retrieve resources
- `POST`: Create new resources
- `PUT`: Update entire resources
- `PATCH`: Partial resource updates
- `DELETE`: Remove resources

### 1.4 Content Types

**Request Content-Type:** `application/json`
**Response Content-Type:** `application/json`
**File Upload Content-Type:** `multipart/form-data`

---

## 2. Authentication

### 2.1 Authentication Method

The API uses **Laravel Sanctum** for token-based authentication.

### 2.2 Authentication Flow

#### 2.2.1 User Login
```http
POST /api/v1/login
Content-Type: application/json

{
  "email": "user@example.com",
  "password": "password123",
  "device_name": "Mobile App"
}
```

**Response:**
```json
{
  "data": {
    "user": {
      "id": 1,
      "ulid": "01HKXXX...",
      "name": "John Doe",
      "email": "user@example.com"
    },
    "token": "1|abc123...xyz789",
    "expires_at": "2025-01-15T10:30:00Z"
  }
}
```

#### 2.2.2 Token Usage
Include the token in all subsequent requests:

```http
Authorization: Bearer 1|abc123...xyz789
```

#### 2.2.3 User Registration
```http
POST /api/v1/register
Content-Type: application/json

{
  "name": "Jane Smith",
  "email": "jane@example.com",
  "password": "password123",
  "password_confirmation": "password123"
}
```

#### 2.2.4 Logout
```http
POST /api/v1/logout
Authorization: Bearer 1|abc123...xyz789
```

### 2.3 Token Management

#### 2.3.1 Token Refresh
```http
POST /api/v1/refresh
Authorization: Bearer 1|abc123...xyz789
```

#### 2.3.2 Device Management
```http
GET /api/v1/devices
Authorization: Bearer 1|abc123...xyz789

POST /api/v1/devices/disconnect
Authorization: Bearer 1|abc123...xyz789
{
  "device_id": "device_uuid"
}
```

---

## 3. Request/Response Format

### 3.1 Standard Response Structure

All API responses follow this consistent structure:

```json
{
  "data": {},
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  },
  "links": {
    "first": "http://api.example.com/api/v1/patients?page=1",
    "last": "http://api.example.com/api/v1/patients?page=10",
    "prev": null,
    "next": "http://api.example.com/api/v1/patients?page=2"
  }
}
```

### 3.2 Single Resource Response

```json
{
  "data": {
    "id": 1,
    "ulid": "01HKXXX...",
    "code": "PAT001",
    "facility_id": 1,
    "created_at": "2025-01-01T12:00:00Z",
    "updated_at": "2025-01-01T12:00:00Z"
  }
}
```

### 3.3 Collection Response

```json
{
  "data": [
    {
      "id": 1,
      "ulid": "01HKXXX...",
      "code": "PAT001"
    },
    {
      "id": 2,
      "ulid": "01HKYYY...",
      "code": "PAT002"
    }
  ],
  "meta": {
    "current_page": 1,
    "total": 2
  }
}
```

### 3.4 Query Parameters

#### 3.4.1 Pagination
- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)

#### 3.4.2 Filtering
- `filter[field]`: Filter by field value
- `filter[created_at][gte]`: Date range filtering
- `filter[status]`: Status filtering

#### 3.4.3 Sorting
- `sort`: Sort field (default: id)
- `sort_direction`: Sort direction (asc/desc, default: asc)

#### 3.4.4 Includes
- `include`: Related resources to include (comma-separated)

**Example:**
```
GET /api/v1/patients?page=2&per_page=20&filter[facility_id]=1&sort=created_at&sort_direction=desc&include=demographics,visits
```

---

## 4. Error Handling

### 4.1 Error Response Format

```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."],
      "password": ["The password must be at least 8 characters."]
    }
  }
}
```

### 4.2 HTTP Status Codes

| Status Code | Description |
|-------------|-------------|
| 200 | OK - Request successful |
| 201 | Created - Resource created successfully |
| 204 | No Content - Request successful, no content returned |
| 400 | Bad Request - Invalid request data |
| 401 | Unauthorized - Authentication required |
| 403 | Forbidden - Access denied |
| 404 | Not Found - Resource not found |
| 422 | Unprocessable Entity - Validation errors |
| 429 | Too Many Requests - Rate limit exceeded |
| 500 | Internal Server Error - Server error |

### 4.3 Error Codes

| Error Code | Description |
|------------|-------------|
| VALIDATION_ERROR | Input validation failed |
| AUTHENTICATION_FAILED | Invalid credentials |
| AUTHORIZATION_FAILED | Insufficient permissions |
| RESOURCE_NOT_FOUND | Requested resource not found |
| RATE_LIMIT_EXCEEDED | API rate limit exceeded |
| SERVER_ERROR | Internal server error |

---

## 5. Endpoint Specifications

### 5.1 Authentication Endpoints

#### 5.1.1 Login
```http
POST /api/v1/login
```

**Request Body:**
```json
{
  "email": "required|email",
  "password": "required|string",
  "device_name": "optional|string"
}
```

**Response (200):**
```json
{
  "data": {
    "user": {
      "id": 1,
      "ulid": "01HKXXX...",
      "name": "John Doe",
      "email": "user@example.com",
      "avatar": "https://example.com/avatar.jpg"
    },
    "token": "1|abc123...xyz789",
    "expires_at": "2025-01-15T10:30:00Z"
  }
}
```

#### 5.1.2 Register
```http
POST /api/v1/register
```

**Request Body:**
```json
{
  "name": "required|string|max:255",
  "email": "required|email|unique:users",
  "password": "required|string|min:8|confirmed"
}
```

#### 5.1.3 Logout
```http
POST /api/v1/logout
Authorization: Bearer {token}
```

**Response (204):** No content

#### 5.1.4 Get Current User
```http
GET /api/v1/user
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "ulid": "01HKXXX...",
    "name": "John Doe",
    "email": "user@example.com",
    "roles": ["doctor", "admin"],
    "permissions": ["patients.view", "patients.create"]
  }
}
```

### 5.2 Patient Management Endpoints

#### 5.2.1 List Patients
```http
GET /api/v1/patients
Authorization: Bearer {token}
```

**Query Parameters:**
- `page`: Page number
- `per_page`: Items per page (max 100)
- `filter[facility_id]`: Filter by facility
- `filter[active]`: Filter by active status
- `search`: Search in patient name/code
- `include`: Include related data (demographics,visits,addresses)

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "ulid": "01HKXXX...",
      "code": "PAT001",
      "facility_id": 1,
      "demographics": {
        "name": {
          "given": ["John"],
          "family": "Doe"
        },
        "birthdate": "1990-01-01",
        "sex": "Male"
      },
      "created_at": "2025-01-01T12:00:00Z",
      "updated_at": "2025-01-01T12:00:00Z"
    }
  ],
  "meta": {
    "current_page": 1,
    "last_page": 10,
    "per_page": 15,
    "total": 150
  }
}
```

#### 5.2.2 Create Patient
```http
POST /api/v1/patients
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:**
```json
{
  "code": "required|string|max:255",
  "facility_id": "required|integer|exists:facilities,id",
  "demographics": {
    "name": {
      "given": ["required|array"],
      "family": "required|string"
    },
    "birthdate": "required|date",
    "sex": "required|in:Male,Female",
    "nationality_id": "required|integer|exists:terms,id",
    "telephone": "nullable|string|max:20"
  },
  "addresses": [
    {
      "type_id": "required|integer|exists:terms,id",
      "use_id": "required|integer|exists:terms,id",
      "line1": "nullable|string|max:255",
      "city": "nullable|string|max:100",
      "province_id": "nullable|integer|exists:provinces,id"
    }
  ],
  "identities": [
    {
      "type_id": "required|integer|exists:terms,id",
      "value": "required|string|max:255",
      "system": "nullable|string|max:255"
    }
  ]
}
```

**Response (201):**
```json
{
  "data": {
    "id": 1,
    "ulid": "01HKXXX...",
    "code": "PAT001",
    "facility_id": 1,
    "demographics": {
      "name": {
        "given": ["John"],
        "family": "Doe"
      },
      "birthdate": "1990-01-01",
      "sex": "Male",
      "nationality_id": 1,
      "telephone": "+855123456789"
    },
    "created_at": "2025-01-01T12:00:00Z",
    "updated_at": "2025-01-01T12:00:00Z"
  }
}
```

#### 5.2.3 Get Patient
```http
GET /api/v1/patients/{id}
Authorization: Bearer {token}
```

**Query Parameters:**
- `include`: Include related data (demographics,visits,addresses,identities,cards)

**Response (200):**
```json
{
  "data": {
    "id": 1,
    "ulid": "01HKXXX...",
    "code": "PAT001",
    "facility_id": 1,
    "demographics": {
      "name": {
        "given": ["John"],
        "family": "Doe"
      },
      "birthdate": "1990-01-01",
      "sex": "Male",
      "nationality_id": 1,
      "telephone": "+855123456789"
    },
    "addresses": [
      {
        "id": 1,
        "type_id": 1,
        "use_id": 1,
        "line1": "123 Main St",
        "city": "Phnom Penh",
        "province_id": 1
      }
    ],
    "created_at": "2025-01-01T12:00:00Z",
    "updated_at": "2025-01-01T12:00:00Z"
  }
}
```

#### 5.2.4 Update Patient
```http
PUT /api/v1/patients/{id}
Authorization: Bearer {token}
Content-Type: application/json
```

**Request Body:** Same as Create Patient

**Response (200):** Updated patient data

#### 5.2.5 Delete Patient
```http
DELETE /api/v1/patients/{id}
Authorization: Bearer {token}
```

**Response (204):** No content

#### 5.2.6 Search Patients
```http
GET /api/v1/patients/search/{query}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "ulid": "01HKXXX...",
      "code": "PAT001",
      "demographics": {
        "name": {
          "given": ["John"],
          "family": "Doe"
        }
      }
    }
  ]
}
```

#### 5.2.7 Patient Summary
```http
GET /api/v1/patients/{id}/summary
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": {
    "patient": {
      "id": 1,
      "code": "PAT001",
      "demographics": {}
    },
    "summary": {
      "total_visits": 5,
      "active_visits": 1,
      "last_visit": "2025-01-01T12:00:00Z",
      "conditions": 3,
      "medications": 2
    }
  }
}
```

### 5.3 Visit Management Endpoints

#### 5.3.1 List Visits
```http
GET /api/v1/visits
Authorization: Bearer {token}
```

**Query Parameters:**
- `filter[patient_id]`: Filter by patient
- `filter[facility_id]`: Filter by facility
- `filter[status]`: Filter by status (active, discharged)
- `filter[admitted_at][gte]`: Date range filtering
- `include`: Include related data (patient,encounters,caretakers)

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "ulid": "01HKXXX...",
      "patient_id": 1,
      "facility_id": 1,
      "visit_type_id": 1,
      "admission_type_id": 1,
      "admitted_at": "2025-01-01T10:00:00Z",
      "discharged_at": null,
      "status": "active",
      "patient": {
        "code": "PAT001",
        "demographics": {
          "name": {
            "given": ["John"],
            "family": "Doe"
          }
        }
      }
    }
  ]
}
```

#### 5.3.2 Create Visit
```http
POST /api/v1/visits
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "patient_id": "required|integer|exists:patients,id",
  "facility_id": "required|integer|exists:facilities,id",
  "visit_type_id": "nullable|integer|exists:terms,id",
  "admission_type_id": "required|integer|exists:terms,id",
  "admitted_at": "required|datetime",
  "caretakers": [
    {
      "name": "required|string|max:255",
      "relationship_id": "required|integer|exists:terms,id",
      "telecom": "nullable|array"
    }
  ]
}
```

#### 5.3.3 Discharge Visit
```http
POST /api/v1/visits/{id}/discharge
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "discharged_at": "required|datetime",
  "discharge_type_id": "required|integer|exists:terms,id",
  "visit_outcome_id": "nullable|integer|exists:terms,id",
  "notes": "nullable|string"
}
```

#### 5.3.4 Visit Timeline
```http
GET /api/v1/visits/{id}/timeline
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": {
    "visit": {
      "id": 1,
      "admitted_at": "2025-01-01T10:00:00Z"
    },
    "timeline": [
      {
        "datetime": "2025-01-01T10:00:00Z",
        "type": "admission",
        "description": "Patient admitted"
      },
      {
        "datetime": "2025-01-01T11:00:00Z",
        "type": "encounter",
        "description": "Consultation with Dr. Smith"
      },
      {
        "datetime": "2025-01-01T12:00:00Z",
        "type": "observation",
        "description": "Vital signs recorded"
      }
    ]
  }
}
```

### 5.4 Encounter Management Endpoints

#### 5.4.1 List Encounters
```http
GET /api/v1/encounters
Authorization: Bearer {token}
```

**Query Parameters:**
- `filter[visit_id]`: Filter by visit
- `filter[practitioner_id]`: Filter by practitioner
- `filter[status_id]`: Filter by status
- `include`: Include related data (visit,practitioner,observations,conditions)

#### 5.4.2 Create Encounter
```http
POST /api/v1/encounters
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "visit_id": "required|integer|exists:visits,id",
  "practitioner_id": "nullable|integer|exists:practitioners,id",
  "department_id": "nullable|integer|exists:departments,id",
  "room_id": "nullable|integer|exists:rooms,id",
  "encounter_type_id": "required|integer|exists:terms,id",
  "status_id": "required|integer|exists:terms,id",
  "class_id": "required|integer|exists:terms,id",
  "priority_id": "nullable|integer|exists:terms,id",
  "started_at": "required|datetime",
  "reason": "nullable|array",
  "diagnosis": "nullable|array"
}
```

#### 5.4.3 Get Encounter Observations
```http
GET /api/v1/encounters/{id}/observations
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "category_id": 1,
      "code_id": 1,
      "status_id": 1,
      "effective_datetime": "2025-01-01T12:00:00Z",
      "value_type": "quantity",
      "value_data": {
        "value": 120,
        "unit": "mmHg"
      }
    }
  ]
}
```

### 5.5 Observation Management Endpoints

#### 5.5.1 Create Observation
```http
POST /api/v1/encounters/{encounter_id}/observations
Authorization: Bearer {token}
```

**Request Body:**
```json
{
  "category_id": "required|integer|exists:terms,id",
  "code_id": "required|integer|exists:terms,id",
  "status_id": "required|integer|exists:terms,id",
  "effective_datetime": "required|datetime",
  "value_type": "required|in:quantity,string,boolean,datetime,codeable_concept",
  "value_data": "required|array",
  "unit_id": "nullable|integer|exists:terms,id",
  "reference_range": "nullable|array",
  "interpretation": "nullable|array",
  "note": "nullable|string",
  "method_id": "nullable|integer|exists:terms,id",
  "performer_id": "nullable|integer|exists:practitioners,id"
}
```

#### 5.5.2 Update Observation
```http
PUT /api/v1/observations/{id}
Authorization: Bearer {token}
```

**Request Body:** Same as Create Observation

### 5.6 Reference Data Endpoints

#### 5.6.1 Get Taxonomy Values
```http
GET /api/v1/taxonomy/{type}
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Male",
      "code": "M"
    },
    {
      "id": 2,
      "name": "Female",
      "code": "F"
    }
  ]
}
```

#### 5.6.2 Get Facilities
```http
GET /api/v1/facilities
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "General Hospital",
      "code": "GH001"
    }
  ]
}
```

#### 5.6.3 Get Gazetteers
```http
GET /api/v1/gazetteers/{type}
Authorization: Bearer {token}
```

**Parameters:**
- `{type}`: province, district, commune, village

**Response (200):**
```json
{
  "data": [
    {
      "id": 1,
      "name": "Phnom Penh",
      "code": "PP",
      "parent_id": null
    }
  ]
}
```

### 5.7 Dashboard Endpoints

#### 5.7.1 Dashboard Statistics
```http
GET /api/v1/dashboard/stats
Authorization: Bearer {token}
```

**Response (200):**
```json
{
  "data": {
    "total_patients": 1500,
    "active_visits": 45,
    "today_registrations": 12,
    "pending_discharges": 8
  }
}
```

#### 5.7.2 Active Patients
```http
GET /api/v1/dashboard/active-patients
Authorization: Bearer {token}
```

#### 5.7.3 Recent Activity
```http
GET /api/v1/dashboard/recent-activity
Authorization: Bearer {token}
```

### 5.8 Clinical Forms Endpoints

#### 5.8.1 List Clinical Forms
```http
GET /api/v1/clinical-forms
Authorization: Bearer {token}
```

#### 5.8.2 Create Clinical Form
```http
POST /api/v1/clinical-forms
Authorization: Bearer {token}
```

#### 5.8.3 Preview Clinical Form
```http
GET /api/v1/clinical-forms/{id}/preview
Authorization: Bearer {token}
```

---

## 6. Data Validation

### 6.1 Common Validation Rules

#### 6.1.1 Required Fields
- All primary identifiers (IDs, codes)
- Essential demographic information
- Clinical timestamps
- Status indicators

#### 6.1.2 Data Types
- **Strings**: Max length validation
- **Integers**: Positive integers for IDs
- **Dates**: Valid date format (ISO 8601)
- **Emails**: Valid email format
- **Phone Numbers**: Valid format with country code

#### 6.1.3 Business Rules
- Patient code unique within facility
- Discharge date after admission date
- Encounter dates within visit date range
- Age calculation from birthdate

### 6.2 Custom Validation Rules

#### 6.2.1 Cambodia Phone Number
```php
'telephone' => 'nullable|regex:/^\+855[0-9]{8,9}$/'
```

#### 6.2.2 Patient Code Format
```php
'code' => 'required|regex:/^[A-Z]{3}[0-9]{3,6}$/'
```

#### 6.2.3 Date Range Validation
```php
'discharged_at' => 'nullable|date|after:admitted_at'
```

---

## 7. Rate Limiting

### 7.1 Rate Limit Rules

| Endpoint Group | Limit | Window |
|----------------|-------|--------|
| Authentication | 5 requests | 1 minute |
| General API | 60 requests | 1 minute |
| File Upload | 10 requests | 1 minute |
| Search | 30 requests | 1 minute |

### 7.2 Rate Limit Headers

Response headers indicate current rate limit status:

```http
X-RateLimit-Limit: 60
X-RateLimit-Remaining: 59
X-RateLimit-Reset: 1640995200
```

### 7.3 Rate Limit Exceeded Response

```json
{
  "error": {
    "code": "RATE_LIMIT_EXCEEDED",
    "message": "Too many requests. Please try again later.",
    "retry_after": 60
  }
}
```

---

## 8. API Testing

### 8.1 Test Environment

**Base URL:** `http://localhost:8000/api/v1`

### 8.2 Test Data

Use the provided seeders to populate test data:

```bash
php artisan db:seed --class=TestDataSeeder
```

### 8.3 Authentication for Testing

Use the test user credentials:

```json
{
  "email": "test@example.com",
  "password": "password"
}
```

### 8.4 Postman Collection

A comprehensive Postman collection is available with:
- Pre-configured requests for all endpoints
- Environment variables for different stages
- Automated tests for response validation
- Authentication flow examples

### 8.5 API Documentation

Interactive API documentation is available at:
- **Development:** `http://localhost:8000/docs/api`
- **Production:** `https://api.emr.example.com/docs/api`

Generated using Laravel Scramble with:
- Request/response examples
- Authentication requirements
- Field descriptions
- Error scenarios

---

## 9. Appendices

### 9.1 Status Codes Reference

Complete list of HTTP status codes used in the API with their meanings and usage contexts.

### 9.2 Error Codes Reference

Detailed list of custom error codes with descriptions and troubleshooting information.

### 9.3 Field Reference

Complete field specifications for all resources including data types, constraints, and examples.

### 9.4 Changelog

Version history with breaking changes, new features, and deprecations.

---

*This API specification provides complete documentation for integrating with the EMR Backend System, enabling developers to build robust healthcare applications for Cambodia's healthcare infrastructure.*