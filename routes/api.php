<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\jyhController;
use App\Http\Controllers\LrzController;
use App\Http\Controllers\LrzOssController;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// 无需认证的路由
Route::post('/register', [jyhController::class, 'register']);
Route::post('/login', [jyhController::class, 'login']);
Route::post('/send-verification-code', [jyhController::class, 'sendVerificationCode']);
Route::post('/reset-password', [jyhController::class, 'resetPassword']);//重置密码

// 需要JWT认证的路由
Route::middleware('auth:api')->group(function () {
    Route::get('/user-info', [jyhController::class, 'getUserInfo']);
    Route::post('/add-patients', [jyhController::class, 'addPatient']);
    Route::get('/patients', [jyhController::class, 'getPatients']);
    Route::delete('/delete-patients', [jyhController::class, 'deletePatient']);
    Route::get('/patients/{patientId}', [jyhController::class, 'getPatientDetail']);

    Route::post('/patients/{patientId}/ct-scans', [jyhController::class, 'uploadCtScan']);
    Route::get('/patients/{patientId}/ct-scans', [jyhController::class, 'getPatientCtScans']);

    Route::get('/patients-for-analysis', [jyhController::class, 'getPatientsForAnalysis']);
    Route::post('/analyze-ct', [jyhController::class, 'analyzeCtScan']);
});

Route::middleware('auth:api')->group(function () {
    Route::post('/AddPatient', [LrzController::class, 'LrzAddPatient']);
    Route::post('/UploadImg', [LrzOssController::class, 'LrzUploadImg']);
    Route::get('/patient/info/{patientId}', [LrzController::class, 'LrzGetPatientInfo']);
    Route::get('search', [LrzController::class, 'LrzSearch']);
    Route::get('GetAnalysesData', [LrzController::class, 'LrzGetAnalysesData']);
    Route::delete('patients/{patientId}', [LrzController::class, 'LrzDeletePatients']);
});
