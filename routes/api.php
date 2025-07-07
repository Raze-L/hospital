<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OssController;
use App\Http\Controllers\TdxController;
use App\Http\Controllers\CTScanController;

//请对这张CT图像进行分析，提供影像分析结果文本,诊断意见和治疗建议
// 受保护的路由
Route::middleware('jwt.auth')->group(function () {
    Route::post('oss/upload', [OssController::class, 'upload']);
});

//注册
Route::post('/register', [TdxController::class, 'register']);

//获取验证码
Route::post('/send-verification-code', [TdxController::class, 'sendVerificationCode']);

//登录
Route::post('/login', [TdxController::class, 'login']);

//登出
Route::post('/logout', [TdxController::class, 'logout']);

//忘记密码
Route::post('/forget-password', [TdxController::class, 'forgetPassword']);

//获取医生信息
Route::middleware('jwt.auth')->group(function () {

//获取医生信息
Route::get('/get-doctors', [TdxController::class, 'getDoctors']);

//添加患者信息
Route::post('/add-patients', [TdxController::class, 'addPatients']);

//上传CT影像
Route::post('/upload-ct-images', [TdxController::class, 'upload']);

//获取分析患者对象
Route::get('/get-patients', [TdxController::class, 'getPatients']);

//搜索患者
Route::get('/patients-search', [TdxController::class, 'searchPatients']);

//选择患者
Route::get('/select-patients', [TdxController::class, 'selectPatients']);

// CT 图像分析
    Route::post('/ct-scans/analyse', [CtScanController::class, 'analyseCtScan']);

//ct分析结果图上传
    Route::post('/ct-analysis/upload', [TdxController::class, 'uploadAnalysisImage']);
});
