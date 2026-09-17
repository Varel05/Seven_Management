<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\WebhookTransactionController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Webhook endpoint for n8n / AI Agent
Route::get('/webhook/transactions', [WebhookTransactionController::class, 'index']);
Route::post('/webhook/transaction', [WebhookTransactionController::class, 'store']);
