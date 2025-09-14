# EMR FHIR API Documentation

Welcome to the comprehensive documentation for the EMR FHIR API system. This documentation provides everything you need to understand, integrate with, and deploy the API.

## Documentation Structure

### 📚 API Documentation
- **[API Overview](api-overview.md)** - Complete API overview, authentication, and core concepts
- **[API Examples](api-examples.md)** - Practical usage examples for all major workflows
- **[Integration Guide](integration-guide.md)** - Comprehensive integration guide with code examples
- **[Interactive Documentation](http://localhost:8000/docs/api)** - Live API documentation with try-it-out functionality

### 🚀 Deployment
- **[Deployment Guide](../deploy/README.md)** - Complete deployment instructions and configuration
- **[Database Setup](../database/migrations/preparation/README.md)** - PostgreSQL migration and setup guide
- **[Error Handling](error-handling-and-logging.md)** - Error handling and logging documentation

### 📋 Quick Reference

#### Core API Endpoints

| Module | Base Path | Description |
|--------|-----------|-------------|
| Authentication | `/api/v1/auth` | User authentication and authorization |
| Patients | `/api/v1/patients` | Patient management and demographics |
| Visits | `/api/v1/visits` | Patient visits and admissions |
| Encounters | `/api/v1/encounters` | Clinical encounters and activities |
| Medications | `/api/v1/medications` | Medication management and prescriptions |
| Service Requests | `/api/v1/service-requests` | Lab tests, imaging, and procedures |
| Invoices | `/api/v1/invoices` | Billing and invoice management |
| Reference Data | `/api/v1/gazetteers`, `/api/v1/facilities` | Gazetteer and facility data |

#### Authentication

All API requests require authentication using Laravel Sanctum:

```bash
curl -H "Authorization: Bearer YOUR_TOKEN" \
     -H "Content-Type: application/json" \
     https://api.emr-fhir.com/api/v1/patients
```

#### Response Format

All responses follow a consistent structure:

```json
{
  "success": true,
  "message": "Operation completed successfully",
  "data": {
    // Response data
  }
}
```

### 🏥 Clinical Workflows

#### Patient Registration
1. Create patient with demographics
2. Add address using Cambodia gazetteer
3. Register insurance/identity cards
4. Verify patient information

#### Clinical Encounter
1. Admit patient (create visit)
2. Create clinical encounter
3. Submit clinical forms and observations
4. Order medications and services
5. Record results and administration
6. Generate invoice and process payment
7. Discharge patient

#### Medication Management
1. Create prescription
2. Dispense medication
3. Record administration
4. Track medication history

#### Service Requests
1. Order laboratory tests
2. Order imaging studies
3. Schedule procedures
4. Record and review results

### 🔧 System Features

#### Core Capabilities
- **FHIR-Compliant**: Based on HL7 FHIR R4 standards
- **Cambodia Gazetteer**: Built-in address hierarchy support
- **Insurance Integration**: Automatic payment type determination
- **Clinical Forms**: Dynamic form schemas with FHIR mapping
- **Comprehensive Audit**: Full activity logging
- **Role-Based Access**: Granular permission system

#### Technical Features
- **API Versioning**: URL-based versioning (v1, v2, etc.)
- **Rate Limiting**: Configurable request limits
- **Caching**: Redis-based caching for performance
- **Queue Processing**: Background job processing
- **Real-time Monitoring**: Health checks and metrics
- **Comprehensive Testing**: Unit and integration tests

### 📊 Data Export

The system provides comprehensive data export capabilities:

```bash
# Export complete visit data
GET /api/v1/visits/{id}/export

# Export all patient visits
GET /api/v1/patients/{id}/export
```

Export includes:
- Patient demographics and addresses
- Insurance and identity information
- All encounters and observations
- Medication history
- Service requests and results
- Billing and payment information

### 🔒 Security

#### Authentication & Authorization
- Laravel Sanctum token-based authentication
- Role-based access control (RBAC)
- API rate limiting
- Request validation and sanitization

#### Data Protection
- Encryption at rest and in transit
- Audit logging for all patient data access
- GDPR/HIPAA compliance considerations
- Secure session management

#### API Security
- HTTPS enforcement
- CORS configuration
- XSS and CSRF protection
- Input validation and sanitization

### 🚀 Getting Started

#### For Developers
1. Read the [API Overview](api-overview.md)
2. Check out [API Examples](api-examples.md)
3. Follow the [Integration Guide](integration-guide.md)
4. Use the [Interactive Documentation](http://localhost:8000/docs/api)

#### For System Administrators
1. Review [Deployment Guide](../deploy/README.md)
2. Set up [Database Migrations](../database/migrations/preparation/README.md)
3. Configure monitoring and logging
4. Set up backup and recovery procedures

#### For Healthcare Professionals
1. Understand the clinical workflows
2. Review data export capabilities
3. Learn about audit and compliance features
4. Explore reporting and analytics options

### 📈 Performance

#### Optimization Features
- Database indexing strategy
- Redis caching layer
- Query optimization
- Asset compression
- CDN integration ready

#### Monitoring
- Application performance monitoring
- Database query analysis
- Error tracking and alerting
- Health check endpoints

### 🔄 Updates and Maintenance

#### Regular Updates
- Security patches
- Feature enhancements
- Performance improvements
- Bug fixes

#### Maintenance Tasks
- Database optimization
- Cache management
- Log rotation
- Backup verification

### 📞 Support

#### Documentation
- **API Reference**: [Interactive Documentation](http://localhost:8000/docs/api)
- **OpenAPI Spec**: [api-v1.json](../api-v1.json)
- **Postman Collection**: Available on request

#### Contact
- **Email**: support@emr-fhir.local
- **Documentation Issues**: [GitHub Issues](https://github.com/your-org/emr-fhir-api/issues)
- **Feature Requests**: [GitHub Discussions](https://github.com/your-org/emr-fhir-api/discussions)

### 📝 Contributing

We welcome contributions to improve the API and documentation:

1. Fork the repository
2. Create a feature branch
3. Make your changes
4. Add tests for new functionality
5. Update documentation
6. Submit a pull request

### 📄 License

This project is licensed under the MIT License. See the LICENSE file for details.

---

## Quick Links

- 🌐 **[Live API Documentation](http://localhost:8000/docs/api)**
- 📖 **[API Overview](api-overview.md)**
- 💡 **[Usage Examples](api-examples.md)**
- 🔧 **[Integration Guide](integration-guide.md)**
- 🚀 **[Deployment Guide](../deploy/README.md)**
- 🏥 **[Health Check](http://localhost:8000/health)**

---

*Last updated: August 14, 2025*