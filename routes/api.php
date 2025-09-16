<?php

use App\Http\Controllers\V1\VisitController;
use App\Http\Controllers\V1\AccountController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ClinicalFormController;
use App\Http\Controllers\V1\DashboardController;
use App\Http\Controllers\V1\DepartmentController;
use App\Http\Controllers\V1\EncounterController;
use App\Http\Controllers\V1\ExportController;
use App\Http\Controllers\V1\FacilityController;
use App\Http\Controllers\V1\InvoiceController;
use App\Http\Controllers\V1\PaymentController;
use App\Http\Controllers\V1\TransferController;
use App\Http\Controllers\V1\GazetteerController;
use App\Http\Controllers\V1\HealthController;
use App\Http\Controllers\V1\ObservationController;
use App\Http\Controllers\SystemHealthController;
use App\Http\Controllers\V1\PatientController;
use App\Http\Controllers\V1\RoomController;
use App\Http\Controllers\V1\ServiceRequestController;
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

Route::prefix('v1')->name('api.v1.')->middleware(['api.version:v1', 'api.rate_limit:api'])->group(function () {

    /*
    |--------------------------------------------------------------------------
    | System Health Check Routes (Public)
    |--------------------------------------------------------------------------
    */
    Route::get('ping', [SystemHealthController::class, 'ping'])->name('ping');
    Route::get('/system/ping', [SystemHealthController::class, 'ping'])->name('system.ping');
    Route::get('/system/health', [SystemHealthController::class, 'health'])->name('system.health');
    Route::get('/system/metrics', [SystemHealthController::class, 'metrics'])->name('system.metrics');
    Route::get('/system/ready', [SystemHealthController::class, 'ready'])->name('system.ready');
    Route::get('/system/live', [SystemHealthController::class, 'live'])->name('system.live');

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
            ->middleware('api.rate_limit:login')
            ->name('login');
        Route::post('register', [AuthController::class, 'register'])
            ->middleware('api.rate_limit:register')
            ->name('register');
        Route::post('refresh', [AuthController::class, 'refreshToken'])
            ->middleware('api.rate_limit:sensitive')
            ->name('refresh');

        // Password Reset
        Route::post('forgot-password', [AuthController::class, 'sendResetPasswordLink'])
            ->middleware('api.rate_limit:password-reset')
            ->name('password.email');
        Route::post('reset-password', [AuthController::class, 'resetPassword'])
            ->middleware('api.rate_limit:password-reset')
            ->name('password.store');

        // Email Verification
        Route::post('verification-notification', [AuthController::class, 'verificationNotification'])
            ->middleware('api.rate_limit:verification-notification')
            ->name('verification.send');
        Route::get('verify-email/{ulid}/{hash}', [AuthController::class, 'verifyEmail'])
            ->middleware(['signed', 'api.rate_limit:sensitive'])
            ->name('verification.verify');
    });

    /*
    |--------------------------------------------------------------------------
    | Protected Routes (Require Authentication)
    |--------------------------------------------------------------------------
    */
    Route::middleware(['auth:api'])->group(function () {

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
        Route::middleware(['api.rate_limit:uploads'])->group(function () {
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
        Route::apiResource('patients', PatientController::class)
            ->middleware('permission:view-patients|create-patients|edit-patients|delete-patients');
        Route::prefix('patients')->name('patients.')->group(function () {
            Route::get('code/{code}', [PatientController::class, 'showByCode'])->name('show-by-code');
            Route::get('{patient}/summary', [PatientController::class, 'summary'])->name('summary');
            Route::get('{patient}/visits', [PatientController::class, 'visits'])->name('visits');
            Route::get('{patient}/insurance-status', [PatientController::class, 'insuranceStatus'])->name('insurance-status');
            Route::post('{patient}/insurance', [PatientController::class, 'addInsurance'])->name('add-insurance');
            Route::get('{patient}/beneficiary-status', [PatientController::class, 'beneficiaryStatus'])->name('beneficiary-status');
            Route::get('{patient}/medications', [\App\Http\Controllers\V1\MedicationController::class, 'getPatientMedicationHistory'])->name('medications');
            Route::get('{patient}/active-prescriptions', [\App\Http\Controllers\V1\MedicationController::class, 'getActivePrescriptions'])->name('active-prescriptions');
            Route::get('{patient}/medication-summary', [\App\Http\Controllers\V1\MedicationController::class, 'getMedicationSummary'])->name('medication-summary');
            Route::get('{patient}/administrations', [\App\Http\Controllers\V1\MedicationController::class, 'getPatientAdministrations'])->name('administrations');
        });

        // Visit Management
        Route::apiResource('visits', VisitController::class)
            ->middleware('permission:view-visits,create-visits,edit-visits,delete-visits');
        Route::prefix('visits')->name('visits.')->group(function () {
            Route::get('{visit}/timeline', [VisitController::class, 'timeline'])->name('timeline');
            Route::post('{visit}/discharge', [VisitController::class, 'discharge'])->name('discharge');
            Route::get('{visit}/medications', [\App\Http\Controllers\V1\MedicationController::class, 'getVisitMedications'])->name('medications');
            Route::get('{visit}/pending-dispenses', [\App\Http\Controllers\V1\MedicationController::class, 'getPendingDispenses'])->name('pending-dispenses');
            Route::get('{visit}/administrations', [\App\Http\Controllers\V1\MedicationController::class, 'getVisitAdministrations'])->name('administrations');
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
        Route::apiResource('encounters', EncounterController::class)
            ->middleware('permission:view-encounters|create-encounters|edit-encounters|delete-encounters');
        Route::prefix('encounters')->name('encounters.')->group(function () {
            Route::get('{encounter}/observations', [EncounterController::class, 'observations'])
                ->name('observations');
            Route::post('{encounter}/forms', [EncounterController::class, 'submitForm'])
                ->name('forms.submit');
            Route::post('transfer', [EncounterController::class, 'transfer'])
                ->name('transfer');
            Route::post('transfer-patient', [EncounterController::class, 'transferPatient'])
                ->name('transfer-patient');
        });

        // Visit encounters chronological view
        Route::get('visits/{visit}/encounters/chronological', [EncounterController::class, 'chronological'])
            ->name('visits.encounters.chronological');

        Route::apiResource('observations', ObservationController::class)->only(['show', 'update', 'destroy']);

        // Medication Management
        Route::prefix('prescriptions')->name('prescriptions.')->group(function () {
            Route::post('/', [\App\Http\Controllers\V1\MedicationController::class, 'createPrescription'])->name('create');
        });

        Route::prefix('medications')->name('medications.')->group(function () {
            Route::post('dispense', [\App\Http\Controllers\V1\MedicationController::class, 'dispenseMedication'])->name('dispense');
            Route::post('administer', [\App\Http\Controllers\V1\MedicationController::class, 'recordAdministration'])->name('administer');
            Route::get('safety-check/{patient}/{medication}', [\App\Http\Controllers\V1\MedicationController::class, 'validateMedicationSafety'])->name('safety-check');
        });

        // Service Request Management
        Route::prefix('service-requests')->name('service-requests.')->group(function () {
            Route::get('/', [ServiceRequestController::class, 'index'])->name('index');
            Route::post('/', [ServiceRequestController::class, 'store'])->name('store');
            Route::get('pending', [ServiceRequestController::class, 'pending'])->name('pending');
            Route::get('type/{type}', [ServiceRequestController::class, 'byType'])->name('by-type');
            Route::get('{serviceRequest}', [ServiceRequestController::class, 'show'])->name('show');
            Route::put('{serviceRequest}/results', [ServiceRequestController::class, 'updateResults'])->name('update-results');
            Route::put('{serviceRequest}/complete', [ServiceRequestController::class, 'complete'])->name('complete');
        });

        // Visit service requests
        Route::prefix('visits')->name('visits.')->group(function () {
            Route::post('{visit}/service-requests', [ServiceRequestController::class, 'store'])->name('service-requests.create');
            Route::get('{visit}/pending-requests', [ServiceRequestController::class, 'pending'])->name('pending-requests');
        });

        // Billing and Invoice Management
        Route::prefix('invoices')->name('invoices.')->group(function () {
            Route::get('{invoice}', [\App\Http\Controllers\V1\InvoiceController::class, 'show'])->name('show');
            Route::post('{invoice}/payments', [\App\Http\Controllers\V1\PaymentController::class, 'recordPayment'])->name('payments.record');
            Route::get('{invoice}/payments', [\App\Http\Controllers\V1\PaymentController::class, 'getInvoicePayments'])->name('payments.list');
            Route::get('{invoice}/payment-summary', [\App\Http\Controllers\V1\PaymentController::class, 'getPaymentSummary'])->name('payment-summary');
            Route::get('{invoice}/calculate-discounts', [\App\Http\Controllers\V1\PaymentController::class, 'calculateDiscounts'])->name('calculate-discounts');
            Route::post('{invoice}/insurance-claim', [\App\Http\Controllers\V1\PaymentController::class, 'generateInsuranceClaim'])->name('insurance-claim');
        });

        Route::prefix('payments')->name('payments.')->group(function () {
            Route::post('{payment}/refund', [\App\Http\Controllers\V1\PaymentController::class, 'processRefund'])->name('refund');
        });

        Route::prefix('visits')->name('visits.')->group(function () {
            Route::post('{visit}/invoices', [\App\Http\Controllers\V1\InvoiceController::class, 'generateForVisit'])->name('invoices.generate');
            Route::get('{visit}/invoices', [\App\Http\Controllers\V1\InvoiceController::class, 'getVisitInvoices'])->name('invoices.list');
        });

        Route::prefix('patients')->name('patients.')->group(function () {
            Route::get('{patient}/billing-history', [\App\Http\Controllers\V1\InvoiceController::class, 'getPatientBillingHistory'])->name('billing-history');
            Route::get('{patient}/billing-summary', [\App\Http\Controllers\V1\PaymentController::class, 'getBillingHistory'])->name('billing-summary');
        });

        // Data Export Management
        Route::prefix('exports')->name('exports.')->group(function () {
            Route::get('visits/{visit}', [ExportController::class, 'exportVisit'])->name('visit');
            Route::get('patients/{patient}/visits', [ExportController::class, 'exportPatientVisits'])->name('patient-visits');
            Route::post('bulk', [ExportController::class, 'bulkExport'])->name('bulk');
        });

        // Facility Management
        Route::apiResource('facilities', FacilityController::class)->only(['index', 'show']);
        Route::prefix('facilities')->name('facilities.')->group(function () {
            Route::get('{facility}/departments', [FacilityController::class, 'departments'])->name('departments');
            Route::get('{facility}/utilization', [FacilityController::class, 'utilization'])->name('utilization');
        });

        Route::apiResource('departments', DepartmentController::class)->only(['show']);
        Route::prefix('departments')->name('departments.')->group(function () {
            Route::get('{department}/rooms', [DepartmentController::class, 'rooms'])->name('rooms');
        });

        Route::apiResource('rooms', RoomController::class)->only(['show']);
        Route::prefix('rooms')->name('rooms.')->group(function () {
            Route::get('{room}/availability', [RoomController::class, 'availability'])->name('availability');
        });

        // Patient Transfer Management
        Route::prefix('transfers')->name('transfers.')->group(function () {
            Route::post('/', [TransferController::class, 'transfer'])->name('transfer');
            Route::post('validate', [TransferController::class, 'validateTransfer'])->name('validate');
        });

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
                Route::get('provinces', [GazetteerController::class, 'provinces'])->name('provinces');
                Route::get('districts/{province_id}', [GazetteerController::class, 'districts'])->name('districts');
                Route::get('communes/{district_id}', [GazetteerController::class, 'communes'])->name('communes');
                Route::get('villages/{commune_id}', [GazetteerController::class, 'villages'])->name('villages');
                Route::post('validate', [GazetteerController::class, 'validateAddress'])->name('validate');
                Route::get('search', [GazetteerController::class, 'search'])->name('search');
                Route::get('{id}/path', [GazetteerController::class, 'path'])->name('path');

                // Address search functionality
                Route::get('addresses/search', [GazetteerController::class, 'searchAddresses'])->name('addresses.search');
                Route::get('addresses/in-area', [GazetteerController::class, 'addressesInArea'])->name('addresses.in-area');
                Route::get('addresses/statistics', [GazetteerController::class, 'addressStatistics'])->name('addresses.statistics');
                Route::post('addresses/validate', [GazetteerController::class, 'validatePatientAddress'])->name('addresses.validate');
            });
        });
    });
});
