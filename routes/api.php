<?php

use App\Http\Controllers\V1\VisitController;
use App\Http\Controllers\V1\AccountController;
use App\Http\Controllers\V1\AuthController;
use App\Http\Controllers\V1\ClinicalFormController;
use App\Http\Controllers\V1\DashboardController;
use App\Http\Controllers\V1\EncounterController;
use App\Http\Controllers\V1\ObservationController;
use App\Http\Controllers\V1\PatientController;
use App\Http\Controllers\V1\UploadController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return ['ok' => true, 'message' => 'Welcome to the API'];
});

Route::prefix('v1')->group(function () {
    Route::get('login/{provider}/redirect', [AuthController::class, 'redirect'])->name('login.provider.redirect');
    Route::get('login/{provider}/callback', [AuthController::class, 'callback'])->name('login.provider.callback');
    Route::post('login', [AuthController::class, 'login'])->middleware('throttle:login')->name('login');
    Route::post('register', [AuthController::class, 'register'])->name('register');
    Route::post('forgot-password', [AuthController::class, 'sendResetPasswordLink'])->middleware('throttle:5,1')->name('password.email');
    Route::post('reset-password', [AuthController::class, 'resetPassword'])->name('password.store');
    Route::post('verification-notification', [AuthController::class, 'verificationNotification'])->middleware('throttle:verification-notification')->name('verification.send');
    Route::get('verify-email/{ulid}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:6,1'])->name('verification.verify');

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::post('logout', [AuthController::class, 'logout'])->name('logout');
        Route::post('devices/disconnect', [AuthController::class, 'deviceDisconnect'])->name('devices.disconnect');
        Route::get('devices', [AuthController::class, 'devices'])->name('devices');
        Route::get('user', [AuthController::class, 'user'])->name('user');

        Route::post('account/update', [AccountController::class, 'update'])->name('account.update');
        Route::post('account/password', [AccountController::class, 'password'])->name('account.password');

        Route::middleware(['throttle:uploads'])->group(function () {
            Route::post('upload', [UploadController::class, 'image'])->name('upload.image');
        });
    });
});

// Public routes
//Route::post('/auth/login', [AuthController::class, 'login']);
//Route::post('/auth/register', [AuthController::class, 'register']);

// Protected routes
Route::middleware('auth:sanctum')->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/user', [AuthController::class, 'user']);

    // Dashboard
    Route::get('/dashboard/stats', [DashboardController::class, 'stats']);
    Route::get('/dashboard/active-patients', [DashboardController::class, 'activePatients']);
    Route::get('/dashboard/recent-activity', [DashboardController::class, 'recentActivity']);

    // Patients
    Route::apiResource('patients', PatientController::class);
    Route::get('/patients/search/{query}', [PatientController::class, 'search']);
    Route::get('/patients/{patient}/summary', [PatientController::class, 'summary']);

    // Visits
    Route::apiResource('visits', VisitController::class);
    Route::get('/visits/{visit}/timeline', [VisitController::class, 'timeline']);
    Route::post('/visits/{visit}/discharge', [VisitController::class, 'discharge']);

    // Clinical Forms
    Route::apiResource('clinical-forms', ClinicalFormController::class);
    Route::get('/clinical-forms/{form}/preview', [ClinicalFormController::class, 'preview']);

    // Encounters & Observations
    Route::apiResource('encounters', EncounterController::class);
    Route::get('/encounters/{encounter}/observations', [EncounterController::class, 'observations']);
    Route::post('/encounters/{encounter}/observations', [ObservationController::class, 'store']);
    Route::put('/observations/{observation}', [ObservationController::class, 'update']);

    // Taxonomy & Reference Data
    Route::get('/taxonomy/{type}', function($type) {
        return \App\Models\TaxonomyValue::whereHas('term', fn($q) => $q->where('code', $type))
            ->orderBy('name')
            ->get(['id', 'name', 'code']);
    });

    Route::get('/facilities', function() {
        return \App\Models\Facility::orderBy('name')->get(['id', 'name', 'code']);
    });

    Route::get('/gazetteers/{type}', function($type) {
        return \App\Models\Gazetteer::where('type', $type)
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'parent_id']);
    });
});
