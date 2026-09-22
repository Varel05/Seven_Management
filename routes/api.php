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

    // Recurring & Subscription Endpoints
    Route::get('/webhook/recurring/due', [WebhookTransactionController::class, 'dueRecurring']);
    Route::post('/webhook/recurring/manual-action', [WebhookTransactionController::class, 'manualRecurringAction']);
    Route::post('/webhook/recurring/{recurringTransaction}/approve', [WebhookTransactionController::class, 'approveRecurring']);
    Route::post('/webhook/recurring/{recurringTransaction}/skip', [WebhookTransactionController::class, 'skipRecurring']);

    // Employee Payroll Webhook Endpoints
    Route::get('/webhook/payroll/due', [WebhookTransactionController::class, 'duePayroll']);
    Route::get('/webhook/payroll/list', [WebhookTransactionController::class, 'duePayroll']);
    Route::post('/webhook/payroll/{employee}/approve', [WebhookTransactionController::class, 'approvePayroll']);
    Route::post('/webhook/payroll/{employee}/skip', [WebhookTransactionController::class, 'skipPayroll']);

    // Information & Monitoring Endpoints (Balance & Summary)
    Route::get('/webhook/balance', [WebhookTransactionController::class, 'balance']);
    Route::get('/webhook/summary', [WebhookTransactionController::class, 'summary']);
});

