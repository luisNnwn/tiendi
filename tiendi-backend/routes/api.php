<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\AnalyticsController;
use App\Http\Controllers\Api\ChatbotController;
use App\Http\Controllers\Api\N8nWebhookController;
use App\Http\Controllers\Api\OrderController;
use App\Http\Controllers\Api\ProductController;
use App\Http\Controllers\Api\StoreController;
use App\Http\Controllers\Api\SupplierSettingsController;
use App\Http\Middleware\VerifyN8nWebhookSecret;
use App\Http\Middleware\EnsureUserIsSupplier;
use Illuminate\Support\Facades\Route;

Route::prefix('auth')->group(function () {
    Route::post('signup', [AuthController::class, 'signup']);
    Route::post('login', [AuthController::class, 'login']);

    Route::middleware(['auth:sanctum'])->group(function () {
        Route::get('me', [AuthController::class, 'me']);
        Route::post('logout', [AuthController::class, 'logout']);
    });
});

Route::post('chatbot/test-order', [ChatbotController::class, 'store']);
Route::post('webhooks/n8n/whatsapp-inbound', [N8nWebhookController::class, 'whatsappInbound'])
    ->middleware(VerifyN8nWebhookSecret::class);
Route::post('stores', [StoreController::class, 'store']);

Route::middleware(['auth:sanctum', EnsureUserIsSupplier::class])->group(function () {
    Route::apiResource('products', ProductController::class);
    Route::apiResource('stores', StoreController::class)->except(['store']);
    Route::get('orders', [OrderController::class, 'index']);
    Route::post('orders', [OrderController::class, 'store']);
    Route::get('orders/{order}', [OrderController::class, 'show']);
    Route::patch('orders/{order}/status', [OrderController::class, 'updateStatus']);
    Route::get('supplier/settings', [SupplierSettingsController::class, 'show']);
    Route::put('supplier/settings', [SupplierSettingsController::class, 'update']);
    Route::get('analytics/overview', [AnalyticsController::class, 'overview']);
    Route::post('analytics/insights', [AnalyticsController::class, 'insights']);
    Route::post('analytics/refresh', [AnalyticsController::class, 'refresh']);
});
