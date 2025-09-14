# EMR FHIR API Documentation

## Overview

The EMR FHIR API is a comprehensive Electronic Medical Record system built on simplified HL7 FHIR standards. This API provides endpoints for managing patient information, clinical workflows, medications, service requests, billing, and facility management.

## API Version

Current API version: **v1**

All API endpoints are prefixed with `/api/v1/`

## Base URLs

- **Development**: `http://localhost:8000/api/v1`
- **Production**: `https://api.emr-fhir.com/api/v1`

## Authentication

The API uses Laravel Sanctum for authentication. Include the bearer token in the Authorization header:

```
Authorization: Bearer {your-token}
```

### Getting an Access Token

```bash
curl -X POST http://localhost:8000/api/v1/auth/login \
  -H "Content-Type: application/json" \
  -d '{
    "email": "user@example.com",
    "password": "password"
  }'
```

Response:
```json
{
  "user": {
    "id": 1,
    "name": "John Doe",
    "email": "user@example.com"
  },
  "access_token": "1|abc123...",
  "access_expires_at": "2025-08-15T10:00:00.000000Z"
}
```

## API Features

### Core Modules

1. **Patient Management** - Comprehensive patient demographics, insurance, and addressing
2. **Clinical Workflows** - Visits, encounters, and clinical documentation
3. **Medication Management** - Prescriptions, dispensing, and administration tracking
4. **Service Requests** - Laboratory tests, imaging studies, and procedures
5. **Billing & Invoicing** - Insurance-based billing and payment processing
6. **Facility Management** - Healthcare facilities, departments, and rooms
7. **Clinical Forms** - Dynamic form templates with FHIR observation mapping
8. **Data Export** - Comprehensive JSON export capabilities

### Key Features

- **FHIR-Compliant**: Based on HL7 FHIR standards for interoperability
- **Cambodia Gazetteer**: Built-in support for Cambodia's official address hierarchy
- **Insurance Integration**: Automatic payment type determination based on patient insurance
- **Clinical Forms**: Dynamic form schemas with automatic FHIR observation generation
- **Comprehensive Audit Trail**: Full activity logging for all patient data access
- **Role-Based Access Control**: Granular permissions using Spatie Laravel Permission

## Response Format

All API responses follow a consistent format:

### Success Response
```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Response data
  }
}
```

### Error Response
```json
{
  "error": {
    "code": "VALIDATION_ERROR",
    "message": "The given data was invalid.",
    "details": {
      "field_name": ["Specific validation error message"]
    },
    "trace_id": "uuid-for-tracking"
  }
}
```

## Rate Limiting

The API implements rate limiting to ensure fair usage:

- **Authenticated requests**: 1000 requests per minute
- **Unauthenticated requests**: 100 requests per minute

Rate limit headers are included in responses:
- `X-RateLimit-Limit`: Maximum requests allowed
- `X-RateLimit-Remaining`: Remaining requests in current window
- `X-RateLimit-Reset`: Time when rate limit resets

## Pagination

List endpoints support pagination with the following parameters:

- `page`: Page number (default: 1)
- `per_page`: Items per page (default: 15, max: 100)

Pagination response format:
```json
{
  "data": [...],
  "links": {
    "first": "http://api.example.com/api/v1/patients?page=1",
    "last": "http://api.example.com/api/v1/patients?page=10",
    "prev": null,
    "next": "http://api.example.com/api/v1/patients?page=2"
  },
  "meta": {
    "current_page": 1,
    "from": 1,
    "last_page": 10,
    "per_page": 15,
    "to": 15,
    "total": 150
  }
}
```

## Filtering and Searching

Many endpoints support filtering and searching using query parameters:

### Common Filter Parameters

- `search`: General text search across relevant fields
- `sort`: Sort field (prefix with `-` for descending order)
- `filter[field]`: Filter by specific field values

Example:
```
GET /api/v1/patients?search=john&sort=-created_at&filter[active]=1
```

## Error Codes

| HTTP Status | Error Code | Description |
|-------------|------------|-------------|
| 400 | BAD_REQUEST | Invalid request format |
| 401 | UNAUTHORIZED | Authentication required |
| 403 | FORBIDDEN | Insufficient permissions |
| 404 | NOT_FOUND | Resource not found |
| 409 | CONFLICT | Business rule violation |
| 422 | VALIDATION_ERROR | Invalid input data |
| 429 | RATE_LIMIT_EXCEEDED | Too many requests |
| 500 | INTERNAL_ERROR | Server error |

## Interactive Documentation

Visit the interactive API documentation at:
- **Development**: `http://localhost:8000/docs/api`
- **Production**: `https://api.emr-fhir.com/docs/api`

The interactive documentation provides:
- Complete endpoint documentation
- Request/response examples
- Try-it-out functionality
- Schema definitions
- Authentication testing

## SDK and Client Libraries

Currently, the API can be consumed using standard HTTP clients. Official SDKs are planned for:
- PHP
- JavaScript/TypeScript
- Python
- Java

## Support

For API support and questions:
- Email: support@emr-fhir.local
- Documentation: [API Documentation](http://localhost:8000/docs/api)
- GitHub Issues: [Report Issues](https://github.com/your-org/emr-fhir-api/issues)