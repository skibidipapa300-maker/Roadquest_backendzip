<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\CarController;
use App\Http\Controllers\RentalController;
use App\Http\Controllers\UserController;

// Handle CORS preflight requests
Route::options('/{any}', function () {
    return response('', 200)
        ->header('Access-Control-Allow-Origin', 'https://skibidipapa300-maker.github.io')
        ->header('Access-Control-Allow-Methods', 'GET, POST, PUT, DELETE, OPTIONS, PATCH')
        ->header('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-XSRF-TOKEN, Accept')
        ->header('Access-Control-Allow-Credentials', 'true')
        ->header('Access-Control-Max-Age', '3600');
})->where('any', '.*');

Route::post('/register', [AuthController::class, 'register']);
Route::post('/login', [AuthController::class, 'login']);
Route::post('/verify-otp', [AuthController::class, 'verifyOtp']);
Route::post('/resend-otp', [AuthController::class, 'resendOtp']);
Route::post('/forgot-password', [AuthController::class, 'forgotPassword']);
Route::post('/verify-reset-otp', [AuthController::class, 'verifyResetOtp']);
Route::post('/reset-password', [AuthController::class, 'resetPassword']);

Route::get('/cars', [CarController::class, 'index']);
Route::get('/cars/popular', [CarController::class, 'popular']);
Route::get('/cars/{id}', [CarController::class, 'show']);

// Protected Routes (Token Auth)
Route::middleware('auth.token')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::post('/rentals', [RentalController::class, 'store']);
    Route::get('/rentals', [RentalController::class, 'index']);
    Route::put('/rentals/{id}', [RentalController::class, 'update']); // Added generic update route for customers
    Route::post('/rentals/{id}/cancel', [RentalController::class, 'cancel']);
    
    Route::get('/user', function (Request $request) {
        return $request->user();
    });
    
    // Profile Update
    Route::post('/profile', [UserController::class, 'updateProfile']);

    // Staff Routes (Staff & Admin)
    Route::middleware('staff')->prefix('staff')->group(function () {
        // Car Management (Shared with Admin logic essentially)
        Route::post('/cars', [CarController::class, 'store']);
        Route::put('/cars/{id}', [CarController::class, 'update']);
        Route::delete('/cars/{id}', [CarController::class, 'destroy']);
        
        // Rental Management (Staff specific status updates could also use the generic one if logic permits, keeping specific route for clarity if needed or just rely on generic)
        // Note: We already added generic PUT /rentals/{id} above which uses RentalController@update (that now has state machine logic).
        // We can keep this one for explicit staff prefix usage or remove if redundant.
        // For now, keeping it is fine as it points to same controller logic, but let's ensure frontend uses correct URL.
        // Staff frontend uses /staff/rentals/{id}, Customer uses /rentals/{id}
        Route::put('/rentals/{id}', [RentalController::class, 'update']);
    });

    // Admin Routes (Admin Only)
    Route::middleware('admin')->prefix('admin')->group(function () {
        // Car Management
        Route::post('/cars', [CarController::class, 'store']);
        Route::put('/cars/{id}', [CarController::class, 'update']);
        Route::delete('/cars/{id}', [CarController::class, 'destroy']);
        
        // User Management
        Route::get('/users', [UserController::class, 'index']);
        Route::post('/users', [UserController::class, 'store']);
        Route::get('/users/{id}', [UserController::class, 'show']);
        Route::put('/users/{id}', [UserController::class, 'update']);
        Route::delete('/users/{id}', [UserController::class, 'destroy']);
        
        // Rental Management
        Route::delete('/rentals/{id}', [RentalController::class, 'destroy']);
    });
});
