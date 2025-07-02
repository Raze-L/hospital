<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\OssController;


// 受保护的路由
Route::middleware('jwt.auth')->group(function () {
    Route::post('oss/upload', [OssController::class, 'upload']);
});
