<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
use App\Http\Controllers\UserExportController;
use App\Http\Controllers\UserImportController;

Route::post('/import-users', [UserImportController::class, 'import']);
Route::get('/export-users', [UserExportController::class, 'export']);
