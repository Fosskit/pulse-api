# Database Migration Preparation for PostgreSQL

This directory contains PostgreSQL-specific setup files and migration preparation scripts for the EMR FHIR system.

## Files Overview

- `postgresql_setup.sql` - PostgreSQL-specific configurations, indexes, views, and functions
- `migration_checklist.md` - Pre-deployment checklist for database migrations
- `performance_tuning.sql` - PostgreSQL performance optimization settings

## Pre-Deployment Steps

### 1. Database Setup

```bash
# Create database and user
sudo -u postgres psql
CREATE DATABASE emr_fhir_production;
CREATE USER emr_fhir_user WITH PASSWORD 'secure_password';
GRANT ALL PRIVILEGES ON DATABASE emr_fhir_production TO emr_fhir_user;
\q
```

### 2. Apply PostgreSQL Setup

```bash
# Apply PostgreSQL-specific configurations
psql -U emr_fhir_user -d emr_fhir_production -f database/migrations/preparation/postgresql_setup.sql
```

### 3. Run Laravel Migrations

```bash
# Set environment for production
export DB_CONNECTION=pgsql
export DB_HOST=localhost
export DB_PORT=5432
export DB_DATABASE=emr_fhir_production
export DB_USERNAME=emr_fhir_user
export DB_PASSWORD=secure_password

# Run migrations
php artisan migrate --force
```

### 4. Seed Reference Data

```bash
# Seed essential reference data
php artisan db:seed --class=ConceptCategorySeeder --force
php artisan db:seed --class=TerminologySeeder --force
php artisan db:seed --class=ConceptSeeder --force
php artisan db:seed --class=GazetteerSeeder --force
php artisan db:seed --class=FacilitySeeder --force
```

## Migration Order

The migrations should be applied in the following order:

1. **Core System Tables**
   - users, cache, jobs tables
   - concept_categories, terminologies, concepts, terms
   - gazetteers, facilities, departments, rooms, services

2. **Patient Management Tables**
   - organizations, cards, patients, practitioners
   - patient_demographics, patient_addresses, patient_identities

3. **Clinical Workflow Tables**
   - visits, encounters, conditions, observations
   - clinical_form_templates

4. **Medication Management Tables**
   - medication_instructions, medication_requests
   - medication_dispenses, medication_administrations

5. **Service Request Tables**
   - service_requests, laboratory_requests
   - imaging_requests, procedures

6. **Billing Tables**
   - invoices, invoice_items, invoice_payments

7. **Authentication and Authorization**
   - OAuth tables (passport)
   - permission tables (spatie)
   - personal_access_tokens (sanctum)
   - activity_log (audit trail)

## Performance Considerations

### Index Strategy

The PostgreSQL setup includes comprehensive indexes for:
- Patient search and lookup
- Clinical data retrieval
- Billing and reporting queries
- Audit trail queries

### Query Optimization

- Full-text search indexes for patient names and demographics
- Composite indexes for common filter combinations
- Partial indexes for active records only
- GIN indexes for JSON data and text search

### Views and Functions

Pre-built views and functions for:
- Patient summary with address and insurance
- Visit summary with counts
- Patient search function with ranking
- Active insurance lookup
- Complete visit data export

## Monitoring and Maintenance

### Daily Tasks
- Monitor slow queries using pg_stat_statements
- Check database size growth
- Verify backup completion
- Clean up expired tokens

### Weekly Tasks
- Analyze table statistics
- Reindex if necessary
- Review query performance
- Update table statistics

### Monthly Tasks
- Full database backup verification
- Performance tuning review
- Index usage analysis
- Cleanup old audit logs (if retention policy exists)

## Backup Strategy

### Daily Backups
```bash
# Full database backup
pg_dump -U emr_fhir_user -h localhost emr_fhir_production > backup_$(date +%Y%m%d).sql

# Compressed backup
pg_dump -U emr_fhir_user -h localhost emr_fhir_production | gzip > backup_$(date +%Y%m%d).sql.gz
```

### Point-in-Time Recovery
```bash
# Enable WAL archiving in postgresql.conf
archive_mode = on
archive_command = 'cp %p /path/to/archive/%f'
wal_level = replica
```

## Security Considerations

### Database Security
- Use strong passwords for database users
- Limit database user privileges
- Enable SSL connections
- Configure pg_hba.conf for secure access
- Regular security updates

### Application Security
- Use connection pooling
- Implement query timeouts
- Sanitize all inputs
- Use prepared statements
- Monitor for SQL injection attempts

## Troubleshooting

### Common Issues

1. **Migration Timeout**
   - Increase `max_execution_time` in PHP
   - Run migrations in smaller batches
   - Use `--step` flag for individual migrations

2. **Index Creation Fails**
   - Check for duplicate data
   - Ensure sufficient disk space
   - Use `CONCURRENTLY` for large tables

3. **Performance Issues**
   - Check query execution plans
   - Verify index usage
   - Monitor connection pool
   - Review PostgreSQL configuration

### Rollback Procedures

```bash
# Rollback specific migration
php artisan migrate:rollback --step=1

# Rollback to specific batch
php artisan migrate:rollback --batch=5

# Fresh migration (development only)
php artisan migrate:fresh --seed
```

## Environment-Specific Configurations

### Development
- Use SQLite for local development
- Enable query logging
- Use smaller datasets

### Staging
- Mirror production configuration
- Use production-like data volumes
- Test migration procedures

### Production
- Use PostgreSQL with replication
- Enable comprehensive monitoring
- Implement backup verification
- Use connection pooling
- Configure resource limits

## Contact and Support

For database migration issues:
- Check Laravel logs: `storage/logs/laravel.log`
- Check PostgreSQL logs: `/var/log/postgresql/`
- Monitor system resources during migration
- Contact database administrator for production issues