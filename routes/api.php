<?php

use App\Http\Controllers\V1\VisitController;
use App\Http\Controllers\V1\AccountController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ClinicalFormController;
use App\Http\Controllers\V1\DashboardController;
use App\Http\Controllers\V1\EncounterController;
use App\Http\Controllers\V1\HealthController;
use App\Http\Controllers\V1\ObservationController;
use App\Http\Controllers\V1\PatientController;
use App\Http\Controllers\V1\UploadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// API Health Check
Route::get('/', function () {
    return response()->json([
        'success' => true,
        'message' => 'EMR FHIR API is running',
        'version' => 'v1',
        'timestamp' => now()->utc()->format('Y-m-d H:i:s')
    ]);
});

/*
|--------------------------------------------------------------------------
| API Version 1 Routes
|--------------------------------------------------------------------------
|
| All v1 API routes with proper middleware stack for authentication,
| rate limiting, and CORS support.
|
*/

Route::prefix('v1')->name('api.v1.')->middleware(['api.version:v1'])->group(function () {
    
    /*
    |--------------------------------------------------------------------------
    | Health Check Routes (Public)
    |--------------------------------------------------------------------------
    */
    Route::get('health', [HealthController::class, 'health'])->name('health');
    Route::get('version', [HealthController::class, 'version'])->name('version');
    
    /*
    |--------------------------------------------------------------------------
    | Authentication Routes (Public)
    |--------------------------------------------------------------------------
    */
    Route::prefix('auth')->name('auth.')->group(function () {
        // OAuth Provider Routes
        Route::get('login/{provider}/redirect', [AuthController::class, 'redirect'])
            ->name('provider.redirect');
        Route::get('login/{provider}/callback', [AuthController::class, 'callback'])
            ->name('provider.callback');
        
        // Standard Authentication
        Route::post('login', [AuthController::class, 'login'])
            ->middleware('throttle:login')
            ->name('login');
        Route::post('register', [AuthController::class, 'register'])
            ->name('register');
        
        // Password Reset
        Route::post('forgot-password', [AuthController::class, 'sendResetPasswordLink'])
            ->middleware('throttle:5,1')
            ->name('password.email');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->name('password.store');
        
        // Email Verification
        Route::post('verification-notification', [AuthController::class, 'verificationNotification'])
            ->middleware('throttle:verification-notification')
            ->name('verification.send');
        Route::get('verify-email/{ulid}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'throttle:6,1'])
            ->name('verification.verify');
    });

    /*
    |--------------------------------------------------------------------------
    | Protected Routes (Require Authentication)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function () {
        
        // Authentication Management
        Route::prefix('auth')->name('auth.')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('user', [AuthController::class, 'user'])->name('user');
            Route::get('devices', [AuthController::class, 'devices'])->name('devices');
            Route::post('devices/disconnect', [AuthController::class, 'deviceDisconnect'])
                ->name('devices.disconnect');
        });

        // Account Management
        Route::prefix('account')->name('account.')->group(function () {
            Route::post('update', [AccountController::class, 'update'])->name('update');
            Route::post('password', [AccountController::class, 'password'])->name('password');
        });

        // File Upload
        Route::middleware(['throttle:uploads'])->group(function () {
            Route::post('upload', [UploadController::class, 'image'])->name('upload.image');
        });

        // Dashboard
        Route::prefix('dashboard')->name('dashboard.')->group(function () {
            Route::get('stats', [DashboardController::class, 'stats'])->name('stats');
            Route::get('active-patients', [DashboardController::class, 'activePatients'])
                ->name('active-patients');
            Route::get('recent-activity', [DashboardController::class, 'recentActivity'])
                ->name('recent-activity');
        });

        // Patient Management
        Route::apiResource('patients', PatientController::class);
        Route::prefix('patients')->name('patients.')->group(function () {
            Route::get('search/{query}', [PatientController::class, 'search'])->name('search');
            Route::get('{patient}/summary', [PatientController::class, 'summary'])->name('summary');
            Route::get('{patient}/visits', [PatientController::class, 'visits'])->name('visits');
        });

        // Visit Management
        Route::apiResource('visits', VisitController::class);
        Route::prefix('visits')->name('visits.')->group(function () {
            Route::get('{visit}/timeline', [VisitController::class, 'timeline'])->name('timeline');
            Route::post('{visit}/discharge', [VisitController::class, 'discharge'])->name('discharge');
        });

        // Clinical Forms
        Route::apiResource('clinical-forms', ClinicalFormController::class);
        Route::prefix('clinical-forms')->name('clinical-forms.')->group(function () {
            Route::get('{form}/preview', [ClinicalFormController::class, 'preview'])->name('preview');
            Route::post('{form}/duplicate', [ClinicalFormController::class, 'duplicate'])->name('duplicate');
            Route::put('{form}/activate', [ClinicalFormController::class, 'activate'])->name('activate');
            Route::put('{form}/deactivate', [ClinicalFormController::class, 'deactivate'])->name('deactivate');
        });
        Route::get('clinical-forms-statistics', [ClinicalFormController::class, 'statistics'])
            ->name('clinical-forms.statistics');

        // Encounters & Observations
        Route::apiResource('encounters', EncounterController::class);
        Route::prefix('encounters')->name('encounters.')->group(function () {
            Route::get('{encounter}/observations', [EncounterController::class, 'observations'])
                ->name('observations');
            Route::post('{encounter}/forms', [EncounterController::class, 'submitForm'])
                ->name('forms.submit');
        });

        Route::apiResource('observations', ObservationController::class)->only(['show', 'update', 'destroy']);

        // Reference Data & Taxonomy
        Route::prefix('reference')->name('reference.')->group(function () {
            Route::get('taxonomy/{type}', function($type) {
                return \App\Models\TaxonomyValue::whereHas('term', fn($q) => $q->where('code', $type))
                    ->orderBy('name')
                    ->get(['id', 'name', 'code']);
            })->name('taxonomy');

            Route::get('facilities', function() {
                return \App\Models\Facility::orderBy('name')->get(['id', 'name', 'code']);
            })->name('facilities');

            // Gazetteer (Cambodia Address System)
            Route::prefix('gazetteers')->name('gazetteers.')->group(function () {
                Route::get('provinces', function() {
                    return \App\Models\Gazetteer::where('type', 'Province')
                        ->orderBy('name')
                        ->get(['id', 'name', 'code', 'parent_id']);
                })->name('provinces');
                
                Route::get('districts/{province_id}', function($province_id) {
                    return \App\Models\Gazetteer::where('type', 'District')
                        ->where('parent_id', $province_id)
                        ->orderBy('name')
                        ->get(['id', 'name', 'code', 'parent_id']);
                })->name('districts');
                
                Route::get('communes/{district_id}', function($district_id) {
                    return \App\Models\Gazetteer::where('type', 'Commune')
                        ->where('parent_id', $district_id)
                        ->orderBy('name')
                        ->get(['id', 'name', 'code', 'parent_id']);
                })->name('communes');
                
                Route::get('villages/{commune_id}', function($commune_id) {
                    return \App\Models\Gazetteer::where('type', 'Village')
                        ->where('parent_id', $commune_id)
                        ->orderBy('name')
                        ->get(['id', 'name', 'code', 'parent_id']);
                })->name('villages');
            });
        });
    });
});
