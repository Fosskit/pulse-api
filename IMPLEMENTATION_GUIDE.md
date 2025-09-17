# Implementation Guide
# EMR Backend System for Cambodia Healthcare

## Version Information
- **Version**: 1.0
- **Date**: 2025
- **Framework**: Laravel 12
- **PHP Version**: 8.2+

---

## Table of Contents

1. [Getting Started](#1-getting-started)
2. [Environment Setup](#2-environment-setup)
3. [Database Setup](#3-database-setup)
4. [Laravel Configuration](#4-laravel-configuration)
5. [Implementation Steps](#5-implementation-steps)
6. [Testing Strategy](#6-testing-strategy)
7. [Deployment Guide](#7-deployment-guide)
8. [Troubleshooting](#8-troubleshooting)

---

## 1. Getting Started

### 1.1 Prerequisites

**System Requirements:**
- PHP 8.2 or higher
- Composer 2.0+
- Node.js 18+ and NPM
- MySQL 8.0+ or PostgreSQL 14+
- Redis 6.0+ (for caching and sessions)
- Git version control

**Development Tools:**
- IDE/Editor (VS Code, PhpStorm)
- API testing tool (Postman, Insomnia)
- Database management tool (phpMyAdmin, Adminer)

### 1.2 Project Structure Overview

```
emr-backend/
├── app/
│   ├── Actions/           # Business actions
│   ├── Console/          # Artisan commands
│   ├── Helpers/          # Helper classes
│   ├── Http/
│   │   ├── Controllers/  # API controllers
│   │   ├── Middleware/   # Custom middleware
│   │   ├── Requests/     # Form request validation
│   │   └── Resources/    # API resources
│   ├── Models/           # Eloquent models
│   ├── Providers/        # Service providers
│   └── Rules/            # Custom validation rules
├── config/               # Configuration files
├── database/
│   ├── factories/        # Model factories
│   ├── migrations/       # Database migrations
│   └── seeders/          # Database seeders
├── resources/
│   ├── lang/             # Localization files
│   └── views/            # View templates
├── routes/
│   ├── api.php           # API routes
│   ├── web.php           # Web routes
│   └── console.php       # Console routes
├── storage/              # File storage
├── tests/
│   ├── Feature/          # Feature tests
│   └── Unit/             # Unit tests
└── vendor/               # Composer dependencies
```

---

## 2. Environment Setup

### 2.1 Create New Laravel Project

```bash
# Create new Laravel 12 project
composer create-project laravel/laravel emr-backend "^12.0"
cd emr-backend

# Install additional packages
composer require laravel/sanctum
composer require laravel/passport
composer require spatie/laravel-permission
composer require spatie/laravel-activitylog
composer require spatie/laravel-query-builder
composer require jenssegers/agent
composer require dedoc/scramble

# Development dependencies
composer require --dev laravel/pail
composer require --dev laravel/sail
```

### 2.2 Environment Configuration

Create `.env` file from `.env.example`:

```bash
cp .env.example .env
```

Configure environment variables:

```env
# Application
APP_NAME="EMR Backend System"
APP_ENV=local
APP_KEY=base64:generated_key_here
APP_DEBUG=true
APP_URL=http://localhost:8000
APP_TIMEZONE=Asia/Phnom_Penh

# Frontend URL (for email links)
FRONTEND_URL=http://localhost:3000

# Database
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=emr_backend
DB_USERNAME=root
DB_PASSWORD=password

# Redis
REDIS_HOST=127.0.0.1
REDIS_PASSWORD=null
REDIS_PORT=6379

# Cache
CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

# Mail
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailtrap.io
MAIL_PORT=2525
MAIL_USERNAME=null
MAIL_PASSWORD=null
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS="noreply@emr.example.com"
MAIL_FROM_NAME="${APP_NAME}"

# Sanctum
SANCTUM_STATEFUL_DOMAINS=localhost,127.0.0.1,::1

# Logging
LOG_CHANNEL=stack
LOG_DEPRECATIONS_CHANNEL=null
LOG_LEVEL=debug
```

### 2.3 Generate Application Key

```bash
php artisan key:generate
```

---

## 3. Database Setup

### 3.1 Create Database

```sql
-- MySQL
CREATE DATABASE emr_backend CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

-- PostgreSQL
CREATE DATABASE emr_backend WITH ENCODING 'UTF8';
```

### 3.2 Configure Database Connection

Update `.env` with correct database credentials and test connection:

```bash
php artisan migrate:status
```

### 3.3 Install Sanctum

```bash
php artisan vendor:publish --provider="Laravel\Sanctum\SanctumServiceProvider"
```

---

## 4. Laravel Configuration

### 4.1 Configure Service Providers

Add to `config/app.php`:

```php
'providers' => [
    // ... existing providers
    Spatie\Permission\PermissionServiceProvider::class,
    Spatie\Activitylog\ActivitylogServiceProvider::class,
    Spatie\QueryBuilder\QueryBuilderServiceProvider::class,
],
```

### 4.2 Configure AppServiceProvider

Update `app/Providers/AppServiceProvider.php`:

```php
<?php

namespace App\Providers;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\ServiceProvider;
use Laravel\Sanctum\Sanctum;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Common fields macro
        Blueprint::macro('commonFields', function () {
            $this->timestamp('created_at')->useCurrent()->index();
            $this->foreignId('created_by')->nullable()->index();
            $this->timestamp('updated_at')->nullable()->useCurrentOnUpdate()->index();
            $this->foreignId('updated_by')->nullable()->index();
            $this->softDeletes($column = 'deleted_at', $precision = 0)->index();
            $this->foreignId('deleted_by')->nullable()->index();
        });

        // Configure Sanctum
        Sanctum::usePersonalAccessTokenModel(
            \App\Models\PersonalAccessToken::class
        );
    }
}
```

### 4.3 Configure Middleware

Update `bootstrap/app.php`:

```php
<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        $middleware->api(prepend: [
            \Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful::class,
        ]);

        $middleware->alias([
            'verified' => \App\Http\Middleware\EnsureEmailIsVerified::class,
        ]);

        $middleware->throttle([
            'api' => 60,
            'login' => 5,
            'uploads' => 10,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        //
    })->create();
```

---

## 5. Implementation Steps

### 5.1 Step 1: Create Base Models

#### 5.1.1 Create User Model Extensions

```bash
php artisan make:model PersonalAccessToken
```

```php
<?php
// app/Models/PersonalAccessToken.php

namespace App\Models;

use Laravel\Sanctum\PersonalAccessToken as SanctumPersonalAccessToken;

class PersonalAccessToken extends SanctumPersonalAccessToken
{
    protected $fillable = [
        'name',
        'token',
        'abilities',
        'expires_at',
        'device_name',
        'device_type',
        'ip_address',
        'user_agent',
    ];

    protected $casts = [
        'abilities' => 'json',
        'last_used_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
```

#### 5.1.2 Create Base Model Class

```bash
php artisan make:model BaseModel
```

```php
<?php
// app/Models/BaseModel.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

abstract class BaseModel extends Model
{
    use SoftDeletes, LogsActivity;

    protected $guarded = ['id'];

    protected $casts = [
        'created_at' => 'datetime',
        'updated_at' => 'datetime',
        'deleted_at' => 'datetime',
    ];

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->logOnly(['*'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }

    // Relationships for audit fields
    public function creator()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function updater()
    {
        return $this->belongsTo(User::class, 'updated_by');
    }

    public function deleter()
    {
        return $this->belongsTo(User::class, 'deleted_by');
    }
}
```

### 5.2 Step 2: Create Master Data Models

#### 5.2.1 Terminology Model

```bash
php artisan make:model Terminology
```

```php
<?php
// app/Models/Terminology.php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

class Terminology extends BaseModel
{
    protected $fillable = [
        'name',
        'code',
        'version',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function conceptCategories(): HasMany
    {
        return $this->hasMany(ConceptCategory::class);
    }
}
```

#### 5.2.2 Province Model

```bash
php artisan make:model Province
```

```php
<?php
// app/Models/Province.php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Province extends Model
{
    protected $fillable = [
        'name',
        'name_en',
        'code',
    ];

    public function districts(): HasMany
    {
        return $this->hasMany(District::class);
    }
}
```

### 5.3 Step 3: Create Patient Management Models

#### 5.3.1 Patient Model

```bash
php artisan make:model Patient
```

```php
<?php
// app/Models/Patient.php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Patient extends BaseModel
{
    protected $fillable = [
        'ulid',
        'code',
        'facility_id',
    ];

    protected static function boot()
    {
        parent::boot();
        
        static::creating(function ($model) {
            if (empty($model->ulid)) {
                $model->ulid = str()->ulid();
            }
        });
    }

    // Relationships
    public function facility(): BelongsTo
    {
        return $this->belongsTo(Facility::class);
    }

    public function demographics(): HasOne
    {
        return $this->hasOne(PatientDemographic::class);
    }

    public function addresses(): HasMany
    {
        return $this->hasMany(PatientAddress::class);
    }

    public function identities(): HasMany
    {
        return $this->hasMany(PatientIdentity::class);
    }

    public function visits(): HasMany
    {
        return $this->hasMany(Visit::class);
    }

    public function cards(): HasMany
    {
        return $this->hasMany(Card::class);
    }

    // Scopes
    public function scopeByFacility($query, $facilityId)
    {
        return $query->where('facility_id', $facilityId);
    }

    public function scopeByCode($query, $code)
    {
        return $query->where('code', $code);
    }
}
```

### 5.4 Step 4: Create API Controllers

#### 5.4.1 Base API Controller

```bash
php artisan make:controller Api/V1/BaseController
```

```php
<?php
// app/Http/Controllers/Api/V1/BaseController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\JsonResource;

class BaseController extends Controller
{
    protected function success($data = null, $message = null, $code = 200): JsonResponse
    {
        $response = [];
        
        if ($data !== null) {
            $response['data'] = $data;
        }
        
        if ($message !== null) {
            $response['message'] = $message;
        }

        return response()->json($response, $code);
    }

    protected function error($message, $code = 400, $errors = null): JsonResponse
    {
        $response = [
            'error' => [
                'message' => $message,
                'code' => $code,
            ]
        ];

        if ($errors !== null) {
            $response['error']['details'] = $errors;
        }

        return response()->json($response, $code);
    }
}
```

#### 5.4.2 Patient Controller

```bash
php artisan make:controller Api/V1/PatientController --api
```

```php
<?php
// app/Http/Controllers/Api/V1/PatientController.php

namespace App\Http\Controllers\Api\V1;

use App\Http\Requests\PatientRequest;
use App\Http\Resources\PatientResource;
use App\Models\Patient;
use Illuminate\Http\Request;
use Spatie\QueryBuilder\QueryBuilder;

class PatientController extends BaseController
{
    public function index(Request $request)
    {
        $patients = QueryBuilder::for(Patient::class)
            ->allowedFilters(['facility_id', 'code'])
            ->allowedIncludes(['demographics', 'addresses', 'visits'])
            ->allowedSorts(['id', 'code', 'created_at'])
            ->defaultSort('-created_at')
            ->paginate($request->get('per_page', 15));

        return PatientResource::collection($patients);
    }

    public function store(PatientRequest $request)
    {
        $patient = Patient::create($request->validated());

        // Create demographics if provided
        if ($request->has('demographics')) {
            $patient->demographics()->create($request->demographics);
        }

        // Create addresses if provided
        if ($request->has('addresses')) {
            foreach ($request->addresses as $address) {
                $patient->addresses()->create($address);
            }
        }

        return new PatientResource($patient->load(['demographics', 'addresses']));
    }

    public function show(Patient $patient)
    {
        return new PatientResource($patient->load([
            'demographics',
            'addresses',
            'identities',
            'visits'
        ]));
    }

    public function update(PatientRequest $request, Patient $patient)
    {
        $patient->update($request->validated());

        // Update demographics if provided
        if ($request->has('demographics')) {
            $patient->demographics()->updateOrCreate(
                ['patient_id' => $patient->id],
                $request->demographics
            );
        }

        return new PatientResource($patient->load(['demographics', 'addresses']));
    }

    public function destroy(Patient $patient)
    {
        $patient->delete();
        
        return response()->noContent();
    }

    public function search(Request $request, $query)
    {
        $patients = Patient::with('demographics')
            ->where('code', 'LIKE', "%{$query}%")
            ->orWhereHas('demographics', function ($q) use ($query) {
                $q->whereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.given[0]')) LIKE ?", ["%{$query}%"])
                  ->orWhereRaw("JSON_UNQUOTE(JSON_EXTRACT(name, '$.family')) LIKE ?", ["%{$query}%"]);
            })
            ->limit(20)
            ->get();

        return PatientResource::collection($patients);
    }
}
```

### 5.5 Step 5: Create Form Requests

```bash
php artisan make:request PatientRequest
```

```php
<?php
// app/Http/Requests/PatientRequest.php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PatientRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $rules = [
            'code' => 'required|string|max:255',
            'facility_id' => 'required|integer|exists:facilities,id',
        ];

        if ($this->has('demographics')) {
            $rules = array_merge($rules, [
                'demographics.name' => 'required|array',
                'demographics.name.given' => 'required|array',
                'demographics.name.family' => 'required|string|max:255',
                'demographics.birthdate' => 'required|date',
                'demographics.sex' => 'required|in:Male,Female',
                'demographics.nationality_id' => 'required|integer|exists:terms,id',
                'demographics.telephone' => 'nullable|string|max:20',
            ]);
        }

        if ($this->has('addresses')) {
            $rules = array_merge($rules, [
                'addresses' => 'array',
                'addresses.*.type_id' => 'required|integer|exists:terms,id',
                'addresses.*.use_id' => 'required|integer|exists:terms,id',
                'addresses.*.line1' => 'nullable|string|max:255',
                'addresses.*.city' => 'nullable|string|max:100',
                'addresses.*.province_id' => 'nullable|integer|exists:provinces,id',
            ]);
        }

        return $rules;
    }
}
```

### 5.6 Step 6: Create API Resources

```bash
php artisan make:resource PatientResource
```

```php
<?php
// app/Http/Resources/PatientResource.php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PatientResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'ulid' => $this->ulid,
            'code' => $this->code,
            'facility_id' => $this->facility_id,
            'demographics' => new PatientDemographicResource($this->whenLoaded('demographics')),
            'addresses' => PatientAddressResource::collection($this->whenLoaded('addresses')),
            'identities' => PatientIdentityResource::collection($this->whenLoaded('identities')),
            'visits' => VisitResource::collection($this->whenLoaded('visits')),
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
        ];
    }
}
```

### 5.7 Step 7: Run Migrations and Seeders

```bash
# Run migrations
php artisan migrate

# Publish permission migrations
php artisan vendor:publish --provider="Spatie\Permission\PermissionServiceProvider"

# Create seeders
php artisan make:seeder DatabaseSeeder
php artisan make:seeder UserSeeder
php artisan make:seeder TerminologySeeder
php artisan make:seeder ProvinceSeeder

# Run seeders
php artisan db:seed
```

---

## 6. Testing Strategy

### 6.1 Setup Testing Environment

#### 6.1.1 Configure Test Database

Add to `.env.testing`:

```env
DB_CONNECTION=sqlite
DB_DATABASE=:memory:
CACHE_DRIVER=array
QUEUE_CONNECTION=sync
SESSION_DRIVER=array
```

#### 6.1.2 Create Test Base Class

```php
<?php
// tests/TestCase.php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        
        $this->seed();
    }

    protected function authenticatedUser()
    {
        $user = \App\Models\User::factory()->create();
        $this->actingAs($user, 'sanctum');
        return $user;
    }
}
```

### 6.2 Feature Tests

#### 6.2.1 Patient API Tests

```bash
php artisan make:test PatientApiTest
```

```php
<?php
// tests/Feature/PatientApiTest.php

namespace Tests\Feature;

use App\Models\Patient;
use App\Models\Facility;
use Tests\TestCase;

class PatientApiTest extends TestCase
{
    public function test_can_list_patients()
    {
        $this->authenticatedUser();
        
        Patient::factory()->count(3)->create();

        $response = $this->getJson('/api/v1/patients');

        $response->assertStatus(200)
                 ->assertJsonStructure([
                     'data' => [
                         '*' => ['id', 'ulid', 'code', 'facility_id']
                     ]
                 ]);
    }

    public function test_can_create_patient()
    {
        $this->authenticatedUser();
        
        $facility = Facility::factory()->create();

        $patientData = [
            'code' => 'PAT001',
            'facility_id' => $facility->id,
            'demographics' => [
                'name' => [
                    'given' => ['John'],
                    'family' => 'Doe'
                ],
                'birthdate' => '1990-01-01',
                'sex' => 'Male',
                'nationality_id' => 1,
            ]
        ];

        $response = $this->postJson('/api/v1/patients', $patientData);

        $response->assertStatus(201)
                 ->assertJsonFragment(['code' => 'PAT001']);

        $this->assertDatabaseHas('patients', ['code' => 'PAT001']);
    }

    public function test_cannot_create_patient_without_authentication()
    {
        $response = $this->postJson('/api/v1/patients', []);

        $response->assertStatus(401);
    }
}
```

### 6.3 Unit Tests

#### 6.3.1 Model Tests

```bash
php artisan make:test PatientModelTest --unit
```

```php
<?php
// tests/Unit/PatientModelTest.php

namespace Tests\Unit;

use App\Models\Patient;
use App\Models\Facility;
use Tests\TestCase;

class PatientModelTest extends TestCase
{
    public function test_patient_has_ulid_when_created()
    {
        $patient = Patient::factory()->create();

        $this->assertNotNull($patient->ulid);
        $this->assertEquals(26, strlen($patient->ulid));
    }

    public function test_patient_belongs_to_facility()
    {
        $facility = Facility::factory()->create();
        $patient = Patient::factory()->create(['facility_id' => $facility->id]);

        $this->assertInstanceOf(Facility::class, $patient->facility);
        $this->assertEquals($facility->id, $patient->facility->id);
    }

    public function test_scope_by_facility()
    {
        $facility1 = Facility::factory()->create();
        $facility2 = Facility::factory()->create();
        
        Patient::factory()->create(['facility_id' => $facility1->id]);
        Patient::factory()->create(['facility_id' => $facility2->id]);

        $patients = Patient::byFacility($facility1->id)->get();

        $this->assertCount(1, $patients);
        $this->assertEquals($facility1->id, $patients->first()->facility_id);
    }
}
```

### 6.4 Run Tests

```bash
# Run all tests
php artisan test

# Run specific test file
php artisan test tests/Feature/PatientApiTest.php

# Run with coverage
php artisan test --coverage

# Run in parallel
php artisan test --parallel
```

---

## 7. Deployment Guide

### 7.1 Production Environment Setup

#### 7.1.1 Server Requirements

**Minimum Specifications:**
- CPU: 2 cores
- RAM: 4GB
- Storage: 50GB SSD
- PHP 8.2+ with extensions: BCMath, Ctype, Fileinfo, JSON, Mbstring, OpenSSL, PDO, Tokenizer, XML

#### 7.1.2 Production Environment Variables

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://api.emr.example.com

LOG_CHANNEL=daily
LOG_LEVEL=info

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis

REDIS_HOST=127.0.0.1
REDIS_PASSWORD=secure_password

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=emr_production
DB_USERNAME=emr_user
DB_PASSWORD=secure_database_password

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=noreply@emr.example.com
MAIL_PASSWORD=secure_mail_password
MAIL_ENCRYPTION=tls
```

### 7.2 Deployment Steps

#### 7.2.1 Initial Deployment

```bash
# Clone repository
git clone https://github.com/your-org/emr-backend.git
cd emr-backend

# Install dependencies
composer install --no-dev --optimize-autoloader

# Set permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Copy environment file
cp .env.production .env

# Generate application key
php artisan key:generate

# Run migrations
php artisan migrate --force

# Seed essential data
php artisan db:seed --class=ProductionSeeder

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Create symbolic link for storage
php artisan storage:link
```

#### 7.2.2 Web Server Configuration

**Nginx Configuration:**

```nginx
server {
    listen 80;
    listen [::]:80;
    server_name api.emr.example.com;
    root /var/www/emr-backend/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";

    index index.php;

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.2-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }
}
```

### 7.3 SSL Configuration

```bash
# Install Certbot
sudo apt install certbot python3-certbot-nginx

# Obtain SSL certificate
sudo certbot --nginx -d api.emr.example.com

# Auto-renewal
sudo crontab -e
# Add: 0 12 * * * /usr/bin/certbot renew --quiet
```

### 7.4 Process Management

#### 7.4.1 Queue Workers with Supervisor

Create `/etc/supervisor/conf.d/emr-worker.conf`:

```ini
[program:emr-worker]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/emr-backend/artisan queue:work redis --sleep=3 --tries=3 --max-time=3600
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/emr-backend/storage/logs/worker.log
stopwaitsecs=3600
```

```bash
sudo supervisorctl reread
sudo supervisorctl update
sudo supervisorctl start emr-worker:*
```

### 7.5 Monitoring and Logging

#### 7.5.1 Log Management

```bash
# Set up log rotation
sudo nano /etc/logrotate.d/emr-backend

# Content:
/var/www/emr-backend/storage/logs/*.log {
    daily
    missingok
    rotate 14
    compress
    notifempty
    create 0640 www-data www-data
}
```

#### 7.5.2 Health Checks

Create monitoring endpoint:

```php
// routes/web.php
Route::get('/health', function () {
    return response()->json([
        'status' => 'ok',
        'timestamp' => now(),
        'version' => config('app.version'),
        'database' => DB::connection()->getPdo() ? 'connected' : 'disconnected',
        'cache' => Cache::store()->get('health-check') !== null ? 'working' : 'not working'
    ]);
});
```

---

## 8. Troubleshooting

### 8.1 Common Issues

#### 8.1.1 Database Connection Issues

**Problem:** `SQLSTATE[HY000] [2002] Connection refused`

**Solutions:**
```bash
# Check MySQL service
sudo systemctl status mysql

# Restart MySQL
sudo systemctl restart mysql

# Check port availability
sudo netstat -tlnp | grep :3306

# Test connection
mysql -u username -p -h hostname
```

#### 8.1.2 Permission Issues

**Problem:** `The stream or file could not be opened in append mode`

**Solutions:**
```bash
# Fix storage permissions
sudo chown -R www-data:www-data storage bootstrap/cache
sudo chmod -R 775 storage bootstrap/cache

# Clear cache
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear
```

#### 8.1.3 Memory Issues

**Problem:** `Fatal error: Allowed memory size exhausted`

**Solutions:**
```bash
# Increase PHP memory limit
sudo nano /etc/php/8.2/fpm/php.ini
# Set: memory_limit = 512M

# Restart PHP-FPM
sudo systemctl restart php8.2-fpm

# Optimize Composer
composer install --optimize-autoloader --no-dev
```

#### 8.1.4 Queue Issues

**Problem:** Jobs not processing

**Solutions:**
```bash
# Check queue status
php artisan queue:monitor

# Restart queue workers
sudo supervisorctl restart emr-worker:*

# Check failed jobs
php artisan queue:failed

# Retry failed jobs
php artisan queue:retry all
```

### 8.2 Performance Optimization

#### 8.2.1 Database Optimization

```bash
# Enable query log temporarily
mysql -u root -p
SET global general_log = 1;
SET global log_output = 'table';

# Check slow queries
SELECT * FROM mysql.slow_log;

# Add indexes for slow queries
php artisan make:migration add_indexes_for_performance
```

#### 8.2.2 Caching Optimization

```bash
# Enable OPcache
sudo nano /etc/php/8.2/fpm/php.ini
# Add:
opcache.enable=1
opcache.memory_consumption=128
opcache.max_accelerated_files=4000

# Cache configuration
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 8.3 Debugging Tools

#### 8.3.1 Enable Debug Mode

```env
APP_DEBUG=true
LOG_LEVEL=debug
```

#### 8.3.2 Laravel Telescope (Development)

```bash
composer require laravel/telescope --dev
php artisan telescope:install
php artisan migrate
```

#### 8.3.3 Query Debugging

```php
// Enable query logging
DB::enableQueryLog();

// Your code here

// Get queries
dd(DB::getQueryLog());
```

---

## 9. Maintenance

### 9.1 Regular Tasks

#### 9.1.1 Database Backup

```bash
#!/bin/bash
# backup.sh
DATE=$(date +%Y%m%d_%H%M%S)
BACKUP_DIR="/var/backups/emr"
mkdir -p $BACKUP_DIR

mysqldump -u $DB_USER -p$DB_PASS $DB_NAME > $BACKUP_DIR/emr_backup_$DATE.sql
gzip $BACKUP_DIR/emr_backup_$DATE.sql

# Keep only last 7 days
find $BACKUP_DIR -name "*.sql.gz" -mtime +7 -delete
```

#### 9.1.2 Log Cleanup

```bash
# Add to crontab
0 2 * * * find /var/www/emr-backend/storage/logs -name "*.log" -mtime +30 -delete
```

### 9.2 Updates and Deployment

#### 9.2.1 Zero-Downtime Deployment

```bash
#!/bin/bash
# deploy.sh

# Pull latest code
git pull origin main

# Install dependencies
composer install --no-dev --optimize-autoloader

# Run migrations
php artisan migrate --force

# Clear and cache
php artisan config:cache
php artisan route:cache
php artisan view:cache

# Restart services
sudo supervisorctl restart emr-worker:*
sudo systemctl reload php8.2-fpm
```

---

This implementation guide provides a comprehensive roadmap for building the EMR Backend System using Laravel 12, ensuring scalability, security, and maintainability for Cambodia's healthcare infrastructure.