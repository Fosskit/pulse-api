# API Usage Examples

This document provides practical examples of using the EMR FHIR API for common healthcare workflows.

## Authentication

### Login and Get Access Token

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "doctor@hospital.com",
    "password": "secure_password"
  }'
```

Response:
```json
{
  "user": {
    "id": 1,
    "name": "Dr. John Smith",
    "email": "doctor@hospital.com"
  },
  "access_token": "1|abc123def456...",
  "access_expires_at": "2025-08-15T10:00:00.000000Z"
}
```

## Patient Management

### Create a New Patient

```bash
curl -X POST http://localhost:8000/api/v1/patients \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "facility_id": 1,
    "demographics": {
      "given_name": "Sophea",
      "family_name": "Chan",
      "date_of_birth": "1990-05-15",
      "gender": "female",
      "phone_number": "+855123456789",
      "email": "sophea.chan@email.com"
    },
    "address": {
      "province_id": 1,
      "district_id": 15,
      "commune_id": 150,
      "village_id": 1500,
      "address_line_1": "Street 123, House 45",
      "postal_code": "12000"
    },
    "insurance": {
      "card_number": "INS123456789",
      "card_type": "NSSF",
      "start_date": "2025-01-01",
      "end_date": "2025-12-31",
      "is_beneficiary": true
    }
  }'
```

### Search Patients

```bash
curl -X GET "http://localhost:8000/api/v1/patients?search=Sophea&filter[gender]=female" \
  -H "Authorization: Bearer 1|abc123def456..."
```

### Get Patient Details

```bash
curl -X GET http://localhost:8000/api/v1/patients/123 \
  -H "Authorization: Bearer 1|abc123def456..."
```

## Clinical Workflow

### Admit a Patient

```bash
curl -X POST http://localhost:8000/api/v1/visits \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "patient_id": 123,
    "facility_id": 1,
    "visit_type_id": 1,
    "admission_type_id": 2,
    "chief_complaint": "Chest pain and shortness of breath",
    "department_id": 5,
    "room_id": 101
  }'
```

### Create a Clinical Encounter

```bash
curl -X POST http://localhost:8000/api/v1/visits/456/encounters \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "encounter_type_id": 1,
    "department_id": 5,
    "room_id": 101,
    "clinical_form_template_id": 3,
    "chief_complaint": "Follow-up examination"
  }'
```

### Submit Clinical Form Data

```bash
curl -X POST http://localhost:8000/api/v1/encounters/789/forms \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "form_data": {
      "temperature": 37.2,
      "blood_pressure_systolic": 120,
      "blood_pressure_diastolic": 80,
      "pulse_rate": 72,
      "respiratory_rate": 16,
      "oxygen_saturation": 98
    }
  }'
```

## Medication Management

### Create a Prescription

```bash
curl -X POST http://localhost:8000/api/v1/visits/456/prescriptions \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "medication_concept_id": 101,
    "instructions": {
      "morning_dose": 1,
      "afternoon_dose": 0,
      "evening_dose": 1,
      "night_dose": 0,
      "days": 7,
      "special_instructions": "Take with food"
    },
    "quantity_requested": 14,
    "priority": "routine"
  }'
```

### Dispense Medication

```bash
curl -X POST http://localhost:8000/api/v1/medications/123/dispense \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "quantity_dispensed": 14,
    "dispenser_notes": "Patient counseled on proper usage",
    "dispensed_at": "2025-08-14T14:30:00Z"
  }'
```

### Record Medication Administration

```bash
curl -X POST http://localhost:8000/api/v1/medications/123/administration \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "administered_dose": 1,
    "administration_route": "oral",
    "administered_at": "2025-08-14T08:00:00Z",
    "administrator_notes": "Patient tolerated well"
  }'
```

## Service Requests

### Order Laboratory Tests

```bash
curl -X POST http://localhost:8000/api/v1/visits/456/service-requests \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "request_type": "Laboratory",
    "service_concept_id": 201,
    "priority": "urgent",
    "clinical_notes": "Rule out myocardial infarction",
    "laboratory_details": {
      "specimen_type": "blood",
      "collection_instructions": "Fasting required"
    }
  }'
```

### Order Imaging Study

```bash
curl -X POST http://localhost:8000/api/v1/visits/456/service-requests \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "request_type": "Imaging",
    "service_concept_id": 301,
    "priority": "routine",
    "clinical_notes": "Chest pain evaluation",
    "imaging_details": {
      "body_site": "chest",
      "contrast_required": false
    }
  }'
```

### Update Service Results

```bash
curl -X PUT http://localhost:8000/api/v1/service-requests/789/results \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "results": [
      {
        "observation_concept_id": 401,
        "value_number": 85.5,
        "unit": "mg/dL",
        "reference_range": "70-100 mg/dL",
        "status": "final"
      }
    ],
    "completion_notes": "All tests completed successfully"
  }'
```

## Billing and Invoicing

### Generate Invoice

```bash
curl -X POST http://localhost:8000/api/v1/visits/456/invoices \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "include_services": true,
    "include_medications": true,
    "billing_notes": "Standard consultation and treatment"
  }'
```

### Record Payment

```bash
curl -X PUT http://localhost:8000/api/v1/invoices/123/payment \
  -H "Authorization: Bearer 1|abc123def456..." \
  -H "Content-Type: application/json" \
  -d '{
    "amount": 150.00,
    "payment_method": "cash",
    "payment_reference": "CASH-2025-001",
    "payment_notes": "Full payment received"
  }'
```

## Facility and Reference Data

### Get Provinces (Cambodia Gazetteer)

```bash
curl -X GET http://localhost:8000/api/v1/gazetteers/provinces \
  -H "Authorization: Bearer 1|abc123def456..."
```

### Get Districts by Province

```bash
curl -X GET http://localhost:8000/api/v1/gazetteers/districts/1 \
  -H "Authorization: Bearer 1|abc123def456..."
```

### Get Facilities

```bash
curl -X GET http://localhost:8000/api/v1/facilities \
  -H "Authorization: Bearer 1|abc123def456..."
```

### Check Room Availability

```bash
curl -X GET http://localhost:8000/api/v1/rooms/101/availability \
  -H "Authorization: Bearer 1|abc123def456..."
```

## Data Export

### Export Complete Visit Data

```bash
curl -X GET http://localhost:8000/api/v1/visits/456/export \
  -H "Authorization: Bearer 1|abc123def456..."
```

Response includes comprehensive visit data:
```json
{
  "visit": {
    "id": 456,
    "patient": {
      "code": "P000123",
      "demographics": {...},
      "addresses": [...],
      "identifications": [...]
    },
    "encounters": [...],
    "medications": [...],
    "service_requests": [...],
    "invoices": [...]
  }
}
```

### Export All Patient Visits

```bash
curl -X GET http://localhost:8000/api/v1/patients/123/export \
  -H "Authorization: Bearer 1|abc123def456..."
```

## Complete Clinical Workflow Example

Here's a complete example of a patient journey from admission to discharge:

### 1. Create Patient
```bash
# Create patient with demographics and insurance
curl -X POST http://localhost:8000/api/v1/patients \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...patient_data...}'
```

### 2. Admit Patient
```bash
# Admit patient for treatment
curl -X POST http://localhost:8000/api/v1/visits \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...admission_data...}'
```

### 3. Clinical Assessment
```bash
# Create encounter and submit vital signs
curl -X POST http://localhost:8000/api/v1/visits/456/encounters \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...encounter_data...}'

curl -X POST http://localhost:8000/api/v1/encounters/789/forms \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...vital_signs_data...}'
```

### 4. Order Tests and Medications
```bash
# Order laboratory tests
curl -X POST http://localhost:8000/api/v1/visits/456/service-requests \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...lab_order_data...}'

# Prescribe medications
curl -X POST http://localhost:8000/api/v1/visits/456/prescriptions \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...prescription_data...}'
```

### 5. Record Results and Administration
```bash
# Update lab results
curl -X PUT http://localhost:8000/api/v1/service-requests/789/results \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...lab_results_data...}'

# Record medication administration
curl -X POST http://localhost:8000/api/v1/medications/123/administration \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...administration_data...}'
```

### 6. Generate Invoice and Process Payment
```bash
# Generate invoice
curl -X POST http://localhost:8000/api/v1/visits/456/invoices \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...invoice_data...}'

# Record payment
curl -X PUT http://localhost:8000/api/v1/invoices/123/payment \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{...payment_data...}'
```

### 7. Discharge Patient
```bash
# Discharge patient
curl -X PUT http://localhost:8000/api/v1/visits/456/discharge \
  -H "Authorization: Bearer TOKEN" \
  -H "Content-Type: application/json" \
  -d '{
    "discharge_type_id": 1,
    "visit_outcome_id": 2,
    "discharge_notes": "Patient recovered fully, discharged home"
  }'
```

### 8. Export Complete Visit Data
```bash
# Export comprehensive visit data
curl -X GET http://localhost:8000/api/v1/visits/456/export \
  -H "Authorization: Bearer TOKEN"
```

## Error Handling Examples

### Validation Error Response
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "email": ["The email field is required."],
      "date_of_birth": ["The date of birth must be a valid date."]
    },
    "trace_id": "550e8400-e29b-41d4-a716-446655440000"
  }
}
```

### Authentication Error Response
```json
{
  "error": {
    "code": "UNAUTHORIZED",
    "message": "Authentication required.",
    "trace_id": "550e8400-e29b-41d4-a716-446655440001"
  }
}
```

### Resource Not Found Response
```json
{
  "error": {
    "code": "NOT_FOUND",
    "message": "Patient not found.",
    "trace_id": "550e8400-e29b-41d4-a716-446655440002"
  }
}
```

## Best Practices

1. **Always include proper authentication headers**
2. **Handle rate limiting gracefully with exponential backoff**
3. **Validate data on the client side before sending requests**
4. **Use appropriate HTTP methods (GET, POST, PUT, DELETE)**
5. **Include meaningful error handling in your applications**
6. **Store and use trace IDs for debugging and support**
7. **Implement proper logging for API interactions**
8. **Use pagination for large data sets**
9. **Cache reference data (facilities, gazetteers) appropriately**
10. **Follow FHIR standards when extending the API**