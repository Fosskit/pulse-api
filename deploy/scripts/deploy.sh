#!/bin/bash

# EMR FHIR API Deployment Script
# This script handles the complete deployment process

set -e

# Configuration
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
PROJECT_DIR="$(cd "$SCRIPT_DIR/../.." && pwd)"
DEPLOY_DIR="$PROJECT_DIR/deploy"
BACKUP_DIR="/var/backups/emr-fhir"
LOG_FILE="/var/log/emr-fhir-deploy.log"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Logging function
log() {
    echo -e "${GREEN}[$(date +'%Y-%m-%d %H:%M:%S')]${NC} $1" | tee -a "$LOG_FILE"
}

error() {
    echo -e "${RED}[$(date +'%Y-%m-%d %H:%M:%S')] ERROR:${NC} $1" | tee -a "$LOG_FILE"
    exit 1
}

warning() {
    echo -e "${YELLOW}[$(date +'%Y-%m-%d %H:%M:%S')] WARNING:${NC} $1" | tee -a "$LOG_FILE"
}

info() {
    echo -e "${BLUE}[$(date +'%Y-%m-%d %H:%M:%S')] INFO:${NC} $1" | tee -a "$LOG_FILE"
}

# Check if running as root
check_root() {
    if [[ $EUID -eq 0 ]]; then
        error "This script should not be run as root for security reasons"
    fi
}

# Check prerequisites
check_prerequisites() {
    log "Checking prerequisites..."
    
    # Check if Docker is installed
    if ! command -v docker &> /dev/null; then
        error "Docker is not installed. Please install Docker first."
    fi
    
    # Check if Docker Compose is installed
    if ! command -v docker-compose &> /dev/null; then
        error "Docker Compose is not installed. Please install Docker Compose first."
    fi
    
    # Check if .env file exists
    if [[ ! -f "$PROJECT_DIR/.env" ]]; then
        warning ".env file not found. Copying from .env.production template..."
        cp "$DEPLOY_DIR/.env.production" "$PROJECT_DIR/.env"
        warning "Please edit .env file with your configuration before continuing."
        read -p "Press Enter to continue after editing .env file..."
    fi
    
    log "Prerequisites check completed"
}

# Create necessary directories
create_directories() {
    log "Creating necessary directories..."
    
    mkdir -p "$BACKUP_DIR"
    mkdir -p "$(dirname "$LOG_FILE")"
    mkdir -p "$PROJECT_DIR/storage/logs"
    mkdir -p "$PROJECT_DIR/storage/app/public"
    mkdir -p "$PROJECT_DIR/bootstrap/cache"
    mkdir -p "$DEPLOY_DIR/nginx/ssl"
    
    log "Directories created"
}

# Generate SSL certificates (self-signed for development)
generate_ssl_certificates() {
    log "Generating SSL certificates..."
    
    SSL_DIR="$DEPLOY_DIR/nginx/ssl"
    
    if [[ ! -f "$SSL_DIR/cert.pem" ]] || [[ ! -f "$SSL_DIR/key.pem" ]]; then
        openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
            -keyout "$SSL_DIR/key.pem" \
            -out "$SSL_DIR/cert.pem" \
            -subj "/C=KH/ST=Phnom Penh/L=Phnom Penh/O=EMR FHIR/OU=IT Department/CN=localhost"
        
        log "SSL certificates generated"
    else
        log "SSL certificates already exist"
    fi
}

# Backup existing data
backup_data() {
    log "Creating backup..."
    
    BACKUP_TIMESTAMP=$(date +%Y%m%d_%H%M%S)
    BACKUP_PATH="$BACKUP_DIR/backup_$BACKUP_TIMESTAMP"
    
    mkdir -p "$BACKUP_PATH"
    
    # Backup database if container exists
    if docker ps -a --format 'table {{.Names}}' | grep -q emr-fhir-database; then
        log "Backing up database..."
        docker exec emr-fhir-database pg_dump -U "$DB_USERNAME" "$DB_DATABASE" > "$BACKUP_PATH/database.sql"
    fi
    
    # Backup storage directory
    if [[ -d "$PROJECT_DIR/storage" ]]; then
        log "Backing up storage directory..."
        cp -r "$PROJECT_DIR/storage" "$BACKUP_PATH/"
    fi
    
    # Backup .env file
    if [[ -f "$PROJECT_DIR/.env" ]]; then
        cp "$PROJECT_DIR/.env" "$BACKUP_PATH/"
    fi
    
    log "Backup created at $BACKUP_PATH"
}

# Build and start services
deploy_services() {
    log "Building and starting services..."
    
    cd "$PROJECT_DIR"
    
    # Load environment variables
    source .env
    
    # Build images
    docker-compose -f deploy/docker-compose.yml build --no-cache
    
    # Start services
    docker-compose -f deploy/docker-compose.yml up -d
    
    log "Services started"
}

# Wait for services to be ready
wait_for_services() {
    log "Waiting for services to be ready..."
    
    # Wait for database
    log "Waiting for database..."
    timeout=60
    while ! docker exec emr-fhir-database pg_isready -U "$DB_USERNAME" -d "$DB_DATABASE" &> /dev/null; do
        if [[ $timeout -le 0 ]]; then
            error "Database failed to start within timeout"
        fi
        sleep 2
        ((timeout--))
    done
    
    # Wait for Redis
    log "Waiting for Redis..."
    timeout=30
    while ! docker exec emr-fhir-redis redis-cli ping &> /dev/null; do
        if [[ $timeout -le 0 ]]; then
            error "Redis failed to start within timeout"
        fi
        sleep 2
        ((timeout--))
    done
    
    # Wait for application
    log "Waiting for application..."
    timeout=60
    while ! curl -k -f https://localhost/health &> /dev/null; do
        if [[ $timeout -le 0 ]]; then
            error "Application failed to start within timeout"
        fi
        sleep 5
        ((timeout--))
    done
    
    log "All services are ready"
}

# Run post-deployment tasks
post_deployment() {
    log "Running post-deployment tasks..."
    
    # Generate API documentation
    docker exec emr-fhir-api php artisan scramble:export
    
    # Clear and cache configuration
    docker exec emr-fhir-api php artisan config:cache
    docker exec emr-fhir-api php artisan route:cache
    docker exec emr-fhir-api php artisan view:cache
    
    # Run any pending migrations
    docker exec emr-fhir-api php artisan migrate --force
    
    # Create storage link
    docker exec emr-fhir-api php artisan storage:link
    
    log "Post-deployment tasks completed"
}

# Health check
health_check() {
    log "Performing health check..."
    
    # Check application health
    if curl -k -f https://localhost/health &> /dev/null; then
        log "Application health check: PASSED"
    else
        error "Application health check: FAILED"
    fi
    
    # Check API documentation
    if curl -k -f https://localhost/docs/api &> /dev/null; then
        log "API documentation check: PASSED"
    else
        warning "API documentation check: FAILED"
    fi
    
    # Check database connection
    if docker exec emr-fhir-api php artisan tinker --execute="DB::connection()->getPdo();" &> /dev/null; then
        log "Database connection check: PASSED"
    else
        error "Database connection check: FAILED"
    fi
    
    log "Health check completed"
}

# Display deployment information
display_info() {
    log "Deployment completed successfully!"
    echo ""
    echo -e "${GREEN}=== EMR FHIR API Deployment Information ===${NC}"
    echo -e "${BLUE}Application URL:${NC} https://localhost"
    echo -e "${BLUE}API Documentation:${NC} https://localhost/docs/api"
    echo -e "${BLUE}Health Check:${NC} https://localhost/health"
    echo -e "${BLUE}Monitoring:${NC} http://localhost:9090"
    echo ""
    echo -e "${YELLOW}Services:${NC}"
    docker-compose -f deploy/docker-compose.yml ps
    echo ""
    echo -e "${YELLOW}Logs:${NC}"
    echo "  Application: docker logs emr-fhir-api"
    echo "  Database: docker logs emr-fhir-database"
    echo "  Web Server: docker logs emr-fhir-webserver"
    echo "  Queue: docker logs emr-fhir-queue"
    echo ""
    echo -e "${YELLOW}Management Commands:${NC}"
    echo "  Stop services: docker-compose -f deploy/docker-compose.yml down"
    echo "  View logs: docker-compose -f deploy/docker-compose.yml logs -f"
    echo "  Restart services: docker-compose -f deploy/docker-compose.yml restart"
    echo ""
}

# Cleanup function
cleanup() {
    log "Cleaning up..."
    # Remove old images
    docker image prune -f
    # Remove old volumes (be careful with this in production)
    # docker volume prune -f
}

# Main deployment function
main() {
    log "Starting EMR FHIR API deployment..."
    
    check_root
    check_prerequisites
    create_directories
    generate_ssl_certificates
    backup_data
    deploy_services
    wait_for_services
    post_deployment
    health_check
    cleanup
    display_info
    
    log "Deployment completed successfully!"
}

# Handle script arguments
case "${1:-}" in
    "backup")
        backup_data
        ;;
    "health")
        health_check
        ;;
    "logs")
        docker-compose -f deploy/docker-compose.yml logs -f
        ;;
    "stop")
        docker-compose -f deploy/docker-compose.yml down
        ;;
    "restart")
        docker-compose -f deploy/docker-compose.yml restart
        ;;
    "update")
        log "Updating application..."
        git pull
        docker-compose -f deploy/docker-compose.yml build --no-cache
        docker-compose -f deploy/docker-compose.yml up -d
        post_deployment
        health_check
        ;;
    *)
        main
        ;;
esac