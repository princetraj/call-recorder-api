<?php

use App\Http\Controllers\Api\AdminAuthController;
use App\Http\Controllers\Api\AdminController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BranchController;
use App\Http\Controllers\Api\CallLogController;
use App\Http\Controllers\Api\CallRecordingController;
use App\Http\Controllers\Api\DeviceController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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

// ===========================
// USER ROUTES (Android App)
// ===========================

// Public user routes
Route::post('/login', [AuthController::class, 'login']);

// Protected user routes (for Android app users)
Route::middleware('auth:sanctum')->group(function () {
    // User auth
    Route::post('/logout', [AuthController::class, 'logout']);

    // Call logs routes
    Route::get('/call-logs', [CallLogController::class, 'index']);
    Route::post('/call-logs', [CallLogController::class, 'store']);
    Route::get('/call-logs/{id}', [CallLogController::class, 'show']);

    // Call recordings routes
    Route::get('/call-logs/{callLogId}/recordings', [CallRecordingController::class, 'index']);
    Route::post('/call-recordings', [CallRecordingController::class, 'store']);
    Route::delete('/call-recordings/{id}', [CallRecordingController::class, 'destroy']);

    // Device routes
    Route::post('/devices/register', [DeviceController::class, 'register']);
    Route::post('/devices/status', [DeviceController::class, 'updateStatus']);
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::get('/devices/{id}', [DeviceController::class, 'show']);
    Route::delete('/devices/{id}', [DeviceController::class, 'destroy']);
});

// ===========================
// ADMIN ROUTES (Admin Panel)
// ===========================

// Public admin routes
Route::prefix('admin')->group(function () {
    Route::post('/login', [AdminAuthController::class, 'login']);
});

// Protected admin routes (for admin panel)
Route::prefix('admin')->middleware(['auth:admin', 'admin.role'])->group(function () {
    // Admin auth
    Route::post('/logout', [AdminAuthController::class, 'logout']);
    Route::get('/me', [AdminAuthController::class, 'me']);

    // Branch routes - All admin roles can view
    Route::get('/branches', [BranchController::class, 'index']);
    Route::get('/branches/{id}', [BranchController::class, 'show']);

    // Branch management - Only super_admin and manager
    Route::middleware('admin.role:super_admin,manager')->group(function () {
        Route::post('/branches', [BranchController::class, 'store']);
        Route::put('/branches/{id}', [BranchController::class, 'update']);
        Route::delete('/branches/{id}', [BranchController::class, 'destroy']);
    });

    // User management routes - All admin roles can view
    Route::get('/users', [UserController::class, 'index']);
    Route::get('/users/{id}', [UserController::class, 'show']);

    // User CRUD - Only super_admin and manager
    Route::middleware('admin.role:super_admin,manager')->group(function () {
        Route::post('/users', [UserController::class, 'store']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
    });

    // Admin management routes - View all admins (all roles)
    Route::get('/admins', [AdminController::class, 'index']);
    Route::get('/admins/{id}', [AdminController::class, 'show']);

    // Admin CRUD - Only super_admin
    Route::middleware('admin.role:super_admin')->group(function () {
        Route::post('/admins', [AdminController::class, 'store']);
        Route::put('/admins/{id}', [AdminController::class, 'update']);
        Route::delete('/admins/{id}', [AdminController::class, 'destroy']);
    });

    // Call logs - All admins can view
    Route::get('/call-logs', [CallLogController::class, 'index']);
    Route::get('/call-logs/{id}', [CallLogController::class, 'show']);

    // Call recordings - All admins can view
    Route::get('/call-logs/{callLogId}/recordings', [CallRecordingController::class, 'index']);

    // Devices - All admins can view
    Route::get('/devices', [DeviceController::class, 'index']);
    Route::get('/devices/{id}', [DeviceController::class, 'show']);
});
