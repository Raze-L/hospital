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
//用户注册
Route::post('/register', [jyhController::class, 'register']);
//用户登录
Route::post('/login', [jyhController::class, 'login']);
//发送验证码
Route::post('/send-verification-code', [jyhController::class, 'sendVerificationCode']);
//重置密码
Route::post('/reset-password', [jyhController::class, 'resetPassword']);

// 需要JWT认证的路由
Route::middleware('auth:api')->group(function () {
    //获取用户信息
    Route::get('/user-info', [jyhController::class, 'getUserInfo']);
    //添加患者
    Route::post('/add-patients', [jyhController::class, 'addPatient']);
    //获取患者列表
    Route::get('/patients', [jyhController::class, 'getPatients']);
    //删除患者
    Route::delete('/delete-patients', [jyhController::class, 'deletePatient']);
    //获取患者详情
    Route::get('/patients-detail', [jyhController::class, 'getPatientDetail']);

    //上传CT扫描
    Route::post('/upload/ctscan', [jyhController::class, 'uploadCtScan']);
    //获取患者CT扫描列表
    Route::get('/patients/ct-scans', [jyhController::class, 'getPatientCtScans']);

    //获取CT扫描分析
    Route::get('/patients-for-analysis', [jyhController::class, 'getPatientsForAnalysis']);
    //分析CT扫描
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
