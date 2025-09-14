# EMR FHIR API Deployment Guide

This directory contains all the necessary files and configurations for deploying the EMR FHIR API system in production.

## Quick Start

### Prerequisites

- Docker and Docker Compose installed
- Git (for updates)
- At least 4GB RAM and 20GB disk space
- SSL certificates (or use the generated self-signed ones)

### Deployment Steps

1. **Clone the repository**
   ```bash
   git clone <repository-url>
   cd emr-fhir-api
   ```

2. **Configure environment**
   ```bash
   cp deploy/.env.production .env
   # Edit .env with your configuration
   nano .env
   ```

3. **Run deployment script**
   ```bash
   chmod +x deploy/scripts/deploy.sh
   ./deploy/scripts/deploy.sh
   ```

4. **Access the application**
   - API: https://localhost
   - Documentation: https://localhost/docs/api
   - Health Check: https://localhost/health
   - Monitoring: http://localhost:9090

## File Structure

```
deploy/
├── docker-compose.yml          # Main Docker Compose configuration
├── Dockerfile                  # Application container definition
├── .env.production            # Production environment template
├── README.md                  # This file
├── nginx/
│   └── conf.d/
│       └── default.conf       # Nginx configuration
├── php/
│   └── local.ini              # PHP configuration
├── postgres/
│   └── postgresql.conf        # PostgreSQL configuration
├── redis/
│   └── redis.conf             # Redis configuration
└── scripts/
    ├── deploy.sh              # Main deployment script
    └── entrypoint.sh          # Container entrypoint script
```

## Services

### Application Stack

- **app**: PHP-FPM application server
- **webserver**: Nginx reverse proxy and web server
- **database**: PostgreSQL database
- **redis**: Redis cache and session store
- **queue**: Laravel queue worker
- **scheduler**: Laravel task scheduler
- **monitoring**: Prometheus monitoring

### Ports

- **80/443**: HTTP/HTTPS (Nginx)
- **5432**: PostgreSQL (internal)
- **6379**: Redis (internal)
- **8080**: Health check endpoint
- **9090**: Prometheus monitoring

## Configuration

### Environment Variables

Key environment variables to configure in `.env`:

```bash
# Application
APP_NAME="EMR FHIR API"
APP_ENV=production
APP_KEY=base64:...
APP_URL=https://your-domain.com

# Database
DB_DATABASE=emr_fhir_production
DB_USERNAME=emr_fhir_user
DB_PASSWORD=secure_password

# Redis
REDIS_PASSWORD=secure_redis_password

# Mail
MAIL_HOST=smtp.your-provider.com
MAIL_USERNAME=your-email@domain.com
MAIL_PASSWORD=your-email-password

# Security
SANCTUM_STATEFUL_DOMAINS=your-domain.com
```

### SSL Certificates

Place your SSL certificates in `deploy/nginx/ssl/`:
- `cert.pem`: SSL certificate
- `key.pem`: Private key

Or use the auto-generated self-signed certificates for development.

## Management Commands

### Basic Operations

```bash
# Start services
docker-compose -f deploy/docker-compose.yml up -d

# Stop services
docker-compose -f deploy/docker-compose.yml down

# Restart services
docker-compose -f deploy/docker-compose.yml restart

# View logs
docker-compose -f deploy/docker-compose.yml logs -f

# View specific service logs
docker-compose -f deploy/docker-compose.yml logs -f app
```

### Application Management

```bash
# Run artisan commands
docker exec emr-fhir-api php artisan migrate
docker exec emr-fhir-api php artisan config:cache
docker exec emr-fhir-api php artisan queue:work

# Access application shell
docker exec -it emr-fhir-api bash

# Access database
docker exec -it emr-fhir-database psql -U emr_fhir_user -d emr_fhir_production
```

### Deployment Script Commands

```bash
# Full deployment
./deploy/scripts/deploy.sh

# Create backup only
./deploy/scripts/deploy.sh backup

# Health check only
./deploy/scripts/deploy.sh health

# View logs
./deploy/scripts/deploy.sh logs

# Stop services
./deploy/scripts/deploy.sh stop

# Restart services
./deploy/scripts/deploy.sh restart

# Update application
./deploy/scripts/deploy.sh update
```

## Monitoring and Logging

### Application Logs

- **Application**: `docker logs emr-fhir-api`
- **Web Server**: `docker logs emr-fhir-webserver`
- **Database**: `docker logs emr-fhir-database`
- **Queue**: `docker logs emr-fhir-queue`

### Log Files

- **Application**: `storage/logs/laravel.log`
- **PHP Errors**: `storage/logs/php_errors.log`
- **Nginx Access**: `/var/log/nginx/access.log`
- **Nginx Error**: `/var/log/nginx/error.log`

### Monitoring

- **Prometheus**: http://localhost:9090
- **Health Check**: https://localhost/health
- **API Status**: https://localhost/docs/api

## Backup and Recovery

### Automated Backups

The deployment script automatically creates backups before deployment:
- Database dump
- Storage directory
- Environment configuration

### Manual Backup

```bash
# Database backup
docker exec emr-fhir-database pg_dump -U emr_fhir_user emr_fhir_production > backup.sql

# Storage backup
tar -czf storage_backup.tar.gz storage/

# Full backup
./deploy/scripts/deploy.sh backup
```

### Recovery

```bash
# Restore database
docker exec -i emr-fhir-database psql -U emr_fhir_user -d emr_fhir_production < backup.sql

# Restore storage
tar -xzf storage_backup.tar.gz
```

## Security Considerations

### Network Security

- All services run in isolated Docker network
- Only necessary ports exposed
- SSL/TLS encryption for all external communication
- Rate limiting configured in Nginx

### Application Security

- Laravel Sanctum authentication
- Role-based access control
- Input validation and sanitization
- SQL injection protection
- XSS protection headers
- CSRF protection

### Database Security

- Dedicated database user with limited privileges
- Connection encryption
- Regular security updates
- Backup encryption

### Container Security

- Non-root user in containers
- Minimal base images
- Regular image updates
- Secret management through environment variables

## Performance Optimization

### Database Optimization

- Comprehensive indexing strategy
- Query optimization
- Connection pooling
- Regular maintenance tasks

### Application Optimization

- OPcache enabled
- Redis caching
- Asset optimization
- Gzip compression

### Infrastructure Optimization

- Nginx reverse proxy
- Load balancing ready
- CDN integration ready
- Horizontal scaling support

## Troubleshooting

### Common Issues

1. **Services won't start**
   - Check Docker daemon status
   - Verify port availability
   - Check environment variables
   - Review logs for errors

2. **Database connection failed**
   - Verify database credentials
   - Check database service status
   - Ensure database is ready before app starts

3. **SSL certificate errors**
   - Verify certificate files exist
   - Check certificate validity
   - Ensure proper file permissions

4. **Performance issues**
   - Monitor resource usage
   - Check database query performance
   - Review application logs
   - Verify cache configuration

### Debug Mode

For debugging, temporarily enable debug mode:

```bash
# Edit .env
APP_DEBUG=true
LOG_LEVEL=debug

# Restart application
docker-compose -f deploy/docker-compose.yml restart app
```

**Important**: Disable debug mode in production!

## Scaling and High Availability

### Horizontal Scaling

The application is designed for horizontal scaling:

- Stateless application design
- External session storage (Redis)
- Database connection pooling
- Queue processing separation

### Load Balancing

Configure load balancer to distribute traffic across multiple app instances:

```yaml
# Add to docker-compose.yml
app_2:
  # Same configuration as app service
  container_name: emr-fhir-api-2
```

### Database Replication

For high availability, configure PostgreSQL replication:

- Master-slave replication
- Read replicas for reporting
- Automatic failover

## Maintenance

### Regular Tasks

- **Daily**: Monitor logs, check backups
- **Weekly**: Update dependencies, review performance
- **Monthly**: Security updates, database maintenance
- **Quarterly**: Full system review, disaster recovery testing

### Updates

```bash
# Update application code
git pull origin main

# Update and restart services
./deploy/scripts/deploy.sh update

# Update Docker images
docker-compose -f deploy/docker-compose.yml pull
docker-compose -f deploy/docker-compose.yml up -d
```

## Support

For deployment issues:

1. Check the logs first
2. Review this documentation
3. Check Docker and system resources
4. Contact system administrator
5. Review Laravel and PostgreSQL documentation

## License

This deployment configuration is part of the EMR FHIR API system and follows the same license terms.