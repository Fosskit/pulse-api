# EMR FHIR API Integration Guide

This guide provides comprehensive information for integrating with the EMR FHIR API, including setup, authentication, data models, and best practices.

## Table of Contents

1. [Getting Started](#getting-started)
2. [Authentication Setup](#authentication-setup)
3. [Data Models and Relationships](#data-models-and-relationships)
4. [Clinical Workflow Integration](#clinical-workflow-integration)
5. [Error Handling and Logging](#error-handling-and-logging)
6. [Performance Optimization](#performance-optimization)
7. [Security Considerations](#security-considerations)
8. [Testing and Validation](#testing-and-validation)

## Getting Started

### Prerequisites

- HTTP client library for your programming language
- Understanding of RESTful APIs
- Basic knowledge of HL7 FHIR concepts
- Valid API credentials

### Base Configuration

```javascript
// JavaScript/Node.js example
const API_BASE_URL = 'http://localhost:8000/api/v1';
const API_TOKEN = 'your-access-token';

const apiClient = axios.create({
  baseURL: API_BASE_URL,
  headers: {
    'Authorization': `Bearer ${API_TOKEN}`,
    'Content-Type': 'application/json',
    'Accept': 'application/json'
  }
});
```

```php
// PHP example
$apiBaseUrl = 'http://localhost:8000/api/v1';
$apiToken = 'your-access-token';

$client = new GuzzleHttp\Client([
    'base_uri' => $apiBaseUrl,
    'headers' => [
        'Authorization' => 'Bearer ' . $apiToken,
        'Content-Type' => 'application/json',
        'Accept' => 'application/json'
    ]
]);
```

```python
# Python example
import requests

API_BASE_URL = 'http://localhost:8000/api/v1'
API_TOKEN = 'your-access-token'

session = requests.Session()
session.headers.update({
    'Authorization': f'Bearer {API_TOKEN}',
    'Content-Type': 'application/json',
    'Accept': 'application/json'
})
```

## Authentication Setup

### 1. User Registration and Login

```javascript
// Register a new user
async function registerUser(userData) {
  try {
    const response = await axios.post(`${API_BASE_URL}/auth/register`, {
      name: userData.name,
      email: userData.email,
      password: userData.password,
      password_confirmation: userData.password
    });
    return response.data;
  } catch (error) {
    handleApiError(error);
  }
}

// Login and get access token
async function login(email, password) {
  try {
    const response = await axios.post(`${API_BASE_URL}/auth/login`, {
      email,
      password
    });
    
    // Store token securely
    localStorage.setItem('api_token', response.data.access_token);
    localStorage.setItem('token_expires_at', response.data.access_expires_at);
    
    return response.data;
  } catch (error) {
    handleApiError(error);
  }
}
```

### 2. Token Management

```javascript
// Check if token is expired
function isTokenExpired() {
  const expiresAt = localStorage.getItem('token_expires_at');
  if (!expiresAt) return true;
  
  return new Date() >= new Date(expiresAt);
}

// Refresh token or re-authenticate
async function ensureValidToken() {
  if (isTokenExpired()) {
    // Redirect to login or refresh token
    await redirectToLogin();
  }
}

// Automatic token refresh interceptor
apiClient.interceptors.response.use(
  response => response,
  async error => {
    if (error.response?.status === 401) {
      await redirectToLogin();
    }
    return Promise.reject(error);
  }
);
```

## Data Models and Relationships

### Core Entity Relationships

```
Patient
├── Demographics (1:1)
├── Addresses (1:many)
├── Identities/Insurance (1:many)
└── Visits (1:many)
    ├── Encounters (1:many)
    │   └── Observations (1:many)
    ├── Medication Requests (1:many)
    │   ├── Instructions (1:1)
    │   └── Dispenses (1:many)
    ├── Service Requests (1:many)
    │   ├── Laboratory Requests (1:1)
    │   ├── Imaging Requests (1:1)
    │   └── Procedures (1:1)
    └── Invoices (1:many)
        └── Invoice Items (1:many)
```

### Patient Data Structure

```javascript
const patientStructure = {
  id: 123,
  code: "P000123",
  facility_id: 1,
  created_at: "2025-08-14T10:00:00Z",
  
  // Demographics (embedded or separate endpoint)
  demographics: {
    given_name: "Sophea",
    family_name: "Chan",
    date_of_birth: "1990-05-15",
    gender: "female",
    phone_number: "+855123456789",
    email: "sophea.chan@email.com"
  },
  
  // Addresses
  addresses: [{
    id: 456,
    province_id: 1,
    district_id: 15,
    commune_id: 150,
    village_id: 1500,
    address_line_1: "Street 123, House 45",
    postal_code: "12000",
    is_primary: true
  }],
  
  // Insurance/Identity cards
  identities: [{
    id: 789,
    card_id: 101,
    identity_code: "INS123456789",
    start_date: "2025-01-01",
    end_date: "2025-12-31",
    is_beneficiary: true
  }]
};
```

### Visit and Encounter Structure

```javascript
const visitStructure = {
  id: 456,
  patient_id: 123,
  facility_id: 1,
  visit_type_id: 1,
  admission_type_id: 2,
  admitted_at: "2025-08-14T08:00:00Z",
  discharged_at: null,
  
  encounters: [{
    id: 789,
    visit_id: 456,
    encounter_type_id: 1,
    department_id: 5,
    room_id: 101,
    started_at: "2025-08-14T08:30:00Z",
    ended_at: "2025-08-14T09:00:00Z",
    
    observations: [{
      id: 1001,
      encounter_id: 789,
      observation_concept_id: 401,
      value_number: 37.2,
      unit: "°C",
      recorded_at: "2025-08-14T08:35:00Z"
    }]
  }]
};
```

## Clinical Workflow Integration

### 1. Patient Registration Workflow

```javascript
class PatientRegistrationService {
  async registerPatient(patientData) {
    try {
      // Step 1: Validate address using gazetteer
      await this.validateAddress(patientData.address);
      
      // Step 2: Create patient with demographics
      const patient = await apiClient.post('/patients', {
        facility_id: patientData.facility_id,
        demographics: patientData.demographics,
        address: patientData.address,
        insurance: patientData.insurance
      });
      
      // Step 3: Log activity
      console.log(`Patient registered: ${patient.data.code}`);
      
      return patient.data;
    } catch (error) {
      this.handleRegistrationError(error);
    }
  }
  
  async validateAddress(address) {
    // Validate gazetteer hierarchy
    const province = await apiClient.get(`/gazetteers/provinces`);
    const districts = await apiClient.get(`/gazetteers/districts/${address.province_id}`);
    // ... validate complete hierarchy
  }
}
```

### 2. Clinical Encounter Workflow

```javascript
class ClinicalEncounterService {
  async createEncounter(visitId, encounterData) {
    try {
      // Step 1: Check room availability
      const availability = await apiClient.get(`/rooms/${encounterData.room_id}/availability`);
      if (!availability.data.available) {
        throw new Error('Room not available');
      }
      
      // Step 2: Create encounter
      const encounter = await apiClient.post(`/visits/${visitId}/encounters`, encounterData);
      
      // Step 3: Submit clinical form if provided
      if (encounterData.form_data) {
        await this.submitClinicalForm(encounter.data.id, encounterData.form_data);
      }
      
      return encounter.data;
    } catch (error) {
      this.handleEncounterError(error);
    }
  }
  
  async submitClinicalForm(encounterId, formData) {
    return await apiClient.post(`/encounters/${encounterId}/forms`, {
      form_data: formData
    });
  }
}
```

### 3. Medication Management Workflow

```javascript
class MedicationService {
  async prescribeMedication(visitId, medicationData) {
    try {
      // Step 1: Validate medication safety
      await this.validateMedicationSafety(visitId, medicationData);
      
      // Step 2: Create prescription
      const prescription = await apiClient.post(`/visits/${visitId}/prescriptions`, medicationData);
      
      // Step 3: Generate medication instructions
      const instructions = await this.generateInstructions(prescription.data);
      
      return { prescription: prescription.data, instructions };
    } catch (error) {
      this.handleMedicationError(error);
    }
  }
  
  async dispenseMedication(medicationId, dispenseData) {
    return await apiClient.post(`/medications/${medicationId}/dispense`, dispenseData);
  }
  
  async recordAdministration(medicationId, administrationData) {
    return await apiClient.post(`/medications/${medicationId}/administration`, administrationData);
  }
}
```

## Error Handling and Logging

### Comprehensive Error Handler

```javascript
class ApiErrorHandler {
  static handleApiError(error) {
    const response = error.response;
    
    if (!response) {
      // Network error
      console.error('Network error:', error.message);
      throw new Error('Network connection failed');
    }
    
    const { status, data } = response;
    const errorInfo = {
      status,
      code: data.error?.code || 'UNKNOWN_ERROR',
      message: data.error?.message || data.message || 'Unknown error',
      details: data.error?.details || {},
      traceId: data.error?.trace_id
    };
    
    // Log error with trace ID for debugging
    console.error('API Error:', errorInfo);
    
    switch (status) {
      case 400:
        throw new BadRequestError(errorInfo);
      case 401:
        throw new UnauthorizedError(errorInfo);
      case 403:
        throw new ForbiddenError(errorInfo);
      case 404:
        throw new NotFoundError(errorInfo);
      case 422:
        throw new ValidationError(errorInfo);
      case 429:
        throw new RateLimitError(errorInfo);
      case 500:
        throw new ServerError(errorInfo);
      default:
        throw new ApiError(errorInfo);
    }
  }
}

// Custom error classes
class ApiError extends Error {
  constructor(errorInfo) {
    super(errorInfo.message);
    this.name = this.constructor.name;
    this.status = errorInfo.status;
    this.code = errorInfo.code;
    this.details = errorInfo.details;
    this.traceId = errorInfo.traceId;
  }
}

class ValidationError extends ApiError {
  getFieldErrors() {
    return this.details;
  }
}
```

### Logging Integration

```javascript
class ApiLogger {
  static logRequest(config) {
    console.log(`API Request: ${config.method?.toUpperCase()} ${config.url}`, {
      headers: config.headers,
      data: config.data,
      timestamp: new Date().toISOString()
    });
  }
  
  static logResponse(response) {
    console.log(`API Response: ${response.status} ${response.config.url}`, {
      data: response.data,
      duration: response.config.metadata?.endTime - response.config.metadata?.startTime,
      timestamp: new Date().toISOString()
    });
  }
  
  static logError(error) {
    console.error('API Error:', {
      url: error.config?.url,
      method: error.config?.method,
      status: error.response?.status,
      message: error.message,
      traceId: error.response?.data?.error?.trace_id,
      timestamp: new Date().toISOString()
    });
  }
}

// Add interceptors for automatic logging
apiClient.interceptors.request.use(config => {
  config.metadata = { startTime: Date.now() };
  ApiLogger.logRequest(config);
  return config;
});

apiClient.interceptors.response.use(
  response => {
    response.config.metadata.endTime = Date.now();
    ApiLogger.logResponse(response);
    return response;
  },
  error => {
    ApiLogger.logError(error);
    return Promise.reject(error);
  }
);
```

## Performance Optimization

### 1. Caching Strategy

```javascript
class ApiCache {
  constructor() {
    this.cache = new Map();
    this.ttl = new Map();
  }
  
  set(key, value, ttlMs = 300000) { // 5 minutes default
    this.cache.set(key, value);
    this.ttl.set(key, Date.now() + ttlMs);
  }
  
  get(key) {
    if (this.ttl.get(key) < Date.now()) {
      this.cache.delete(key);
      this.ttl.delete(key);
      return null;
    }
    return this.cache.get(key);
  }
  
  clear() {
    this.cache.clear();
    this.ttl.clear();
  }
}

// Cache reference data
class ReferenceDataService {
  constructor() {
    this.cache = new ApiCache();
  }
  
  async getProvinces() {
    const cacheKey = 'provinces';
    let provinces = this.cache.get(cacheKey);
    
    if (!provinces) {
      const response = await apiClient.get('/gazetteers/provinces');
      provinces = response.data;
      this.cache.set(cacheKey, provinces, 3600000); // 1 hour
    }
    
    return provinces;
  }
  
  async getFacilities() {
    const cacheKey = 'facilities';
    let facilities = this.cache.get(cacheKey);
    
    if (!facilities) {
      const response = await apiClient.get('/facilities');
      facilities = response.data;
      this.cache.set(cacheKey, facilities, 1800000); // 30 minutes
    }
    
    return facilities;
  }
}
```

### 2. Batch Operations

```javascript
class BatchOperationService {
  async batchCreatePatients(patientsData) {
    const batchSize = 10;
    const results = [];
    
    for (let i = 0; i < patientsData.length; i += batchSize) {
      const batch = patientsData.slice(i, i + batchSize);
      const batchPromises = batch.map(patientData => 
        this.createPatientWithRetry(patientData)
      );
      
      const batchResults = await Promise.allSettled(batchPromises);
      results.push(...batchResults);
      
      // Rate limiting - wait between batches
      if (i + batchSize < patientsData.length) {
        await this.delay(1000); // 1 second delay
      }
    }
    
    return results;
  }
  
  async createPatientWithRetry(patientData, maxRetries = 3) {
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        return await apiClient.post('/patients', patientData);
      } catch (error) {
        if (attempt === maxRetries || error.response?.status !== 429) {
          throw error;
        }
        
        // Exponential backoff for rate limiting
        const delay = Math.pow(2, attempt) * 1000;
        await this.delay(delay);
      }
    }
  }
  
  delay(ms) {
    return new Promise(resolve => setTimeout(resolve, ms));
  }
}
```

## Security Considerations

### 1. Secure Token Storage

```javascript
class SecureTokenStorage {
  static setToken(token, expiresAt) {
    // Use secure storage (not localStorage in production)
    if (typeof window !== 'undefined') {
      // Browser environment - use secure cookie or encrypted storage
      document.cookie = `api_token=${token}; Secure; HttpOnly; SameSite=Strict`;
    } else {
      // Node.js environment - use environment variables or secure vault
      process.env.API_TOKEN = token;
    }
  }
  
  static getToken() {
    if (typeof window !== 'undefined') {
      // Extract from secure cookie
      return this.getCookieValue('api_token');
    } else {
      return process.env.API_TOKEN;
    }
  }
  
  static getCookieValue(name) {
    const value = `; ${document.cookie}`;
    const parts = value.split(`; ${name}=`);
    if (parts.length === 2) return parts.pop().split(';').shift();
  }
}
```

### 2. Input Validation

```javascript
class InputValidator {
  static validatePatientData(data) {
    const errors = {};
    
    // Validate required fields
    if (!data.demographics?.given_name) {
      errors.given_name = ['Given name is required'];
    }
    
    if (!data.demographics?.family_name) {
      errors.family_name = ['Family name is required'];
    }
    
    // Validate date format
    if (data.demographics?.date_of_birth && !this.isValidDate(data.demographics.date_of_birth)) {
      errors.date_of_birth = ['Invalid date format'];
    }
    
    // Validate email format
    if (data.demographics?.email && !this.isValidEmail(data.demographics.email)) {
      errors.email = ['Invalid email format'];
    }
    
    if (Object.keys(errors).length > 0) {
      throw new ValidationError({ details: errors });
    }
  }
  
  static isValidDate(dateString) {
    const date = new Date(dateString);
    return date instanceof Date && !isNaN(date);
  }
  
  static isValidEmail(email) {
    const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
    return emailRegex.test(email);
  }
}
```

## Testing and Validation

### Unit Testing Example

```javascript
// Jest test example
describe('PatientService', () => {
  let patientService;
  let mockApiClient;
  
  beforeEach(() => {
    mockApiClient = {
      post: jest.fn(),
      get: jest.fn(),
      put: jest.fn(),
      delete: jest.fn()
    };
    patientService = new PatientService(mockApiClient);
  });
  
  test('should create patient successfully', async () => {
    const patientData = {
      facility_id: 1,
      demographics: {
        given_name: 'John',
        family_name: 'Doe',
        date_of_birth: '1990-01-01',
        gender: 'male'
      }
    };
    
    const expectedResponse = {
      data: { id: 123, code: 'P000123', ...patientData }
    };
    
    mockApiClient.post.mockResolvedValue(expectedResponse);
    
    const result = await patientService.createPatient(patientData);
    
    expect(mockApiClient.post).toHaveBeenCalledWith('/patients', patientData);
    expect(result).toEqual(expectedResponse.data);
  });
  
  test('should handle validation errors', async () => {
    const invalidPatientData = {
      facility_id: 1,
      demographics: {
        // Missing required fields
      }
    };
    
    const validationError = {
      response: {
        status: 422,
        data: {
          error: {
            code: 'VALIDATION_ERROR',
            message: 'The given data was invalid.',
            details: {
              given_name: ['The given name field is required.']
            }
          }
        }
      }
    };
    
    mockApiClient.post.mockRejectedValue(validationError);
    
    await expect(patientService.createPatient(invalidPatientData))
      .rejects.toThrow(ValidationError);
  });
});
```

### Integration Testing

```javascript
describe('API Integration Tests', () => {
  let apiClient;
  let authToken;
  
  beforeAll(async () => {
    // Setup test environment
    apiClient = axios.create({
      baseURL: 'http://localhost:8000/api/v1',
      timeout: 10000
    });
    
    // Authenticate for tests
    const loginResponse = await apiClient.post('/auth/login', {
      email: 'test@example.com',
      password: 'password'
    });
    
    authToken = loginResponse.data.access_token;
    apiClient.defaults.headers.common['Authorization'] = `Bearer ${authToken}`;
  });
  
  test('complete patient workflow', async () => {
    // 1. Create patient
    const patientResponse = await apiClient.post('/patients', {
      facility_id: 1,
      demographics: {
        given_name: 'Integration',
        family_name: 'Test',
        date_of_birth: '1990-01-01',
        gender: 'male'
      }
    });
    
    expect(patientResponse.status).toBe(201);
    const patientId = patientResponse.data.id;
    
    // 2. Admit patient
    const visitResponse = await apiClient.post('/visits', {
      patient_id: patientId,
      facility_id: 1,
      visit_type_id: 1,
      admission_type_id: 1
    });
    
    expect(visitResponse.status).toBe(201);
    const visitId = visitResponse.data.id;
    
    // 3. Create encounter
    const encounterResponse = await apiClient.post(`/visits/${visitId}/encounters`, {
      encounter_type_id: 1,
      department_id: 1,
      room_id: 1
    });
    
    expect(encounterResponse.status).toBe(201);
    
    // 4. Export visit data
    const exportResponse = await apiClient.get(`/visits/${visitId}/export`);
    expect(exportResponse.status).toBe(200);
    expect(exportResponse.data.visit.patient.id).toBe(patientId);
  });
});
```

## Best Practices Summary

1. **Authentication**: Always use secure token storage and implement token refresh
2. **Error Handling**: Implement comprehensive error handling with proper logging
3. **Caching**: Cache reference data to improve performance
4. **Validation**: Validate input data before sending to API
5. **Rate Limiting**: Implement exponential backoff for rate-limited requests
6. **Logging**: Log all API interactions with trace IDs for debugging
7. **Testing**: Write comprehensive unit and integration tests
8. **Security**: Never expose sensitive data in logs or client-side code
9. **Performance**: Use batch operations for bulk data processing
10. **Monitoring**: Implement health checks and monitoring for production systems

This integration guide provides a solid foundation for building robust applications that integrate with the EMR FHIR API.