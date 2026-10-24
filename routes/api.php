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
    Route::match(['get', 'post'], '/webhook/transaction/cancel', [WebhookTransactionController::class, 'cancelTransaction']);
    Route::match(['get', 'post'], '/webhook/transactions/cancel', [WebhookTransactionController::class, 'cancelTransaction']);

    // Recurring & Subscription Endpoints
    Route::match(['get', 'post'], '/webhook/recurring/due', [WebhookTransactionController::class, 'dueRecurring']);
    Route::post('/webhook/recurring/manual-action', [WebhookTransactionController::class, 'manualRecurringAction']);
    Route::post('/webhook/recurring/{recurringTransaction}/approve', [WebhookTransactionController::class, 'approveRecurring']);
    Route::post('/webhook/recurring/{recurringTransaction}/skip', [WebhookTransactionController::class, 'skipRecurring']);
    Route::post('/webhook/recurring/create', [WebhookTransactionController::class, 'createRecurringTransaction']);

    // Employee Payroll Webhook Endpoints
    Route::match(['get', 'post'], '/webhook/payroll/due', [WebhookTransactionController::class, 'duePayroll']);
    Route::match(['get', 'post'], '/webhook/payroll/list', [WebhookTransactionController::class, 'duePayroll']);
    Route::post('/webhook/payroll/{employee}/approve', [WebhookTransactionController::class, 'approvePayroll']);
    Route::post('/webhook/payroll/{employee}/skip', [WebhookTransactionController::class, 'skipPayroll']);
    Route::match(['get', 'post'], '/webhook/payroll/points', [WebhookTransactionController::class, 'listEmployeePoints']);
    Route::post('/webhook/payroll/points/update', [WebhookTransactionController::class, 'updateEmployeePoints']);
    Route::post('/webhook/payroll/points', [WebhookTransactionController::class, 'updateEmployeePoints']);
    Route::match(['get', 'post'], '/webhook/payroll/my-points', [WebhookTransactionController::class, 'myPoints']);
    Route::match(['get', 'post'], '/webhook/payroll/bonus-preference', [WebhookTransactionController::class, 'toggleBonusPreference']);
    Route::match(['get', 'post'], '/webhook/payroll/slip', [WebhookTransactionController::class, 'generatePayrollSlip']);
    Route::match(['get', 'post'], '/webhook/tailor-payroll', [WebhookTransactionController::class, 'manageTailorPayroll']);

    // Auth & Telegram Identity Verification Endpoint
    Route::match(['get', 'post'], '/webhook/auth/identify', [WebhookTransactionController::class, 'identifyTelegramUser']);

    // Information & Monitoring Endpoints (Balance, Summary, & Profit Loss)
    Route::match(['get', 'post'], '/webhook/balance', [WebhookTransactionController::class, 'balance']);
    Route::match(['get', 'post'], '/webhook/summary', [WebhookTransactionController::class, 'summary']);
    Route::match(['get', 'post'], '/webhook/profit-loss', [WebhookTransactionController::class, 'profitLossReport']);
    Route::match(['get', 'post'], '/webhook/income-statement', [WebhookTransactionController::class, 'profitLossReport']);

    // Pending Transactions & Verification via Telegram
    Route::match(['get', 'post'], '/webhook/transactions/pending', [WebhookTransactionController::class, 'pendingTransactions']);
    Route::match(['get', 'post'], '/webhook/transaction/verify', [WebhookTransactionController::class, 'verifyTransaction']);
    Route::match(['get', 'post'], '/webhook/transaction/approve', [WebhookTransactionController::class, 'verifyTransaction']);

    // Master Tailors & Labor Rates Management for Telegram
    Route::match(['get', 'post'], '/webhook/tailors', [WebhookTransactionController::class, 'listTailors']);
    Route::post('/webhook/tailors/register', [WebhookTransactionController::class, 'registerTailor']);
    Route::match(['get', 'post'], '/webhook/tailor-rates', [WebhookTransactionController::class, 'tailorLaborRates']);
    Route::match(['get', 'post'], '/webhook/tailors/rates', [WebhookTransactionController::class, 'tailorLaborRates']);

    // Tax (PPh Final UMKM 0.5%) Endpoints for Telegram / Hermes / n8n
    Route::match(['get', 'post'], '/webhook/tax', [WebhookTransactionController::class, 'taxSummary']);
    Route::match(['get', 'post'], '/webhook/tax/summary', [WebhookTransactionController::class, 'taxSummary']);
    Route::post('/webhook/tax/pay', [WebhookTransactionController::class, 'recordTaxPayment']);

    // Active Chart of Accounts (COA) Directory & Semantic Account Detection for Hermes
    Route::match(['get', 'post'], '/webhook/accounts', [WebhookTransactionController::class, 'listAccounts']);
    Route::match(['get', 'post'], '/webhook/accounts/detect', [WebhookTransactionController::class, 'listAccounts']);

    // E-Commerce Web Penjualan & Sewa Integration Endpoints
    Route::post('/webhook/sales-order', [WebhookTransactionController::class, 'recordSalesOrder']);
    Route::post('/webhook/sales-transaction', [WebhookTransactionController::class, 'recordSalesOrder']);
    Route::match(['get', 'post'], '/webhook/sales/sync', [WebhookTransactionController::class, 'syncSalesFromApi']);
    Route::match(['get', 'post'], '/webhook/sales/points/sync', [WebhookTransactionController::class, 'syncSalesPoints']);
    Route::match(['get', 'post'], '/webhook/sales/poin/sync', [WebhookTransactionController::class, 'syncSalesPoints']);
    Route::post('/webhook/sales/points', [WebhookTransactionController::class, 'receiveSalesPointsWebhook']);
    Route::post('/webhook/sales-points', [WebhookTransactionController::class, 'receiveSalesPointsWebhook']);
});
