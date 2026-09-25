<?php

use App\Http\Controllers\Api\WebhookTransactionController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

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
    Route::get('/webhook/payroll/points', [WebhookTransactionController::class, 'listEmployeePoints']);
    Route::post('/webhook/payroll/points', [WebhookTransactionController::class, 'updateEmployeePoints']);

    // Information & Monitoring Endpoints (Balance & Summary)
    Route::get('/webhook/balance', [WebhookTransactionController::class, 'balance']);
    Route::get('/webhook/summary', [WebhookTransactionController::class, 'summary']);

    // Retail Clothing & Stock Endpoints for n8n / Telegram
    Route::get('/webhook/retail/stock', [WebhookTransactionController::class, 'checkRetailStock']);
    Route::post('/webhook/retail/sale', [WebhookTransactionController::class, 'recordRetailSale']);

    // Custom Suit Tailoring & AI Estimation Endpoints for n8n / Telegram
    Route::post('/webhook/custom-suit/estimate', [WebhookTransactionController::class, 'estimateSuit']);
    Route::post('/webhook/custom-suit/order', [WebhookTransactionController::class, 'orderCustomSuit']);
    Route::get('/webhook/custom-suit/status', [WebhookTransactionController::class, 'trackCustomSuit']);
    Route::post('/webhook/custom-suit/update-status', [WebhookTransactionController::class, 'updateCustomSuitStatus']);

    // Raw Materials & Warehouse Monitoring Endpoints for n8n / Telegram
    Route::get('/webhook/materials/stock', [WebhookTransactionController::class, 'checkMaterialStock']);
    Route::post('/webhook/materials/restock', [WebhookTransactionController::class, 'recordMaterialRestock']);
});
