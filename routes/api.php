<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\WebhookTransactionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Webhook endpoints for n8n / AI Agent (Secured with X-Webhook-Secret / Bearer token)
Route::middleware('webhook.secret')->group(function () {
    Route::get('/webhook/transactions', [WebhookTransactionController::class, 'index']);
    Route::post('/webhook/transaction', [WebhookTransactionController::class, 'store']);
});
