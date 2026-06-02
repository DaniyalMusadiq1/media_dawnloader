<?php

use App\Http\Controllers\Api\DownloadController;
use App\Http\Controllers\Api\ExtensionController;
use App\Http\Controllers\Api\PreferencesController;
use App\Http\Controllers\Api\ShareController;
use App\Http\Controllers\FileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
*/

// Public routes (no authentication required)
Route::post('/register-guest', [DownloadController::class, 'registerGuest']);
Route::get('/share/{shareToken}', [ShareController::class, 'show']);

// Protected routes (require X-Guest-Token header)
Route::middleware('guest.auth')->group(function () {
    // Downloads
    Route::post('/download', [DownloadController::class, 'create']);
    Route::post('/download/playlist', [DownloadController::class, 'createPlaylist']);
    Route::post('/download/batch', [DownloadController::class, 'batch']);
    Route::get('/downloads', [DownloadController::class, 'index']);
    Route::get('/download/{id}/status', [DownloadController::class, 'status']);
    Route::delete('/download/{id}', [DownloadController::class, 'destroy']);
    Route::get('/downloads/stats', [DownloadController::class, 'stats']);
    
    // File access
    Route::get('/download/{id}/file', [FileController::class, 'serve']);
    
    // Share
    Route::post('/download/{id}/share', [ShareController::class, 'generate']);
    
    // Preferences
    Route::get('/preferences', [PreferencesController::class, 'show']);
    Route::post('/preferences', [PreferencesController::class, 'update']);
    Route::post('/api-token/generate', [PreferencesController::class, 'generateApiToken']);
    Route::post('/api-token/revoke', [PreferencesController::class, 'revokeApiToken']);
    
    // Schedule
    Route::post('/schedule', [DownloadController::class, 'create']);
});

// Extension routes (require X-API-Token header)
Route::middleware('extension.auth')->prefix('extension')->group(function () {
    Route::post('/submit', [ExtensionController::class, 'submit']);
    Route::get('/status', [ExtensionController::class, 'status']);
});
