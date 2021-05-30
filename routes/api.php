<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\Payment\WebinarPaymentController;
/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/

//the routes of webinar
Route::middleware('auth:api')->prefix('webinar')->group(base_path('routes/api/webinar.php'));

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);

//api for change the order status and triggered by midtrans
Route::post('/payment/status', [WebinarPaymentController::class, 'updateStatus']);
