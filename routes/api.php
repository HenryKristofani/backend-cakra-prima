<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DebtGroupController;
use App\Http\Controllers\DebtItemController;
use App\Http\Controllers\DebtPaymentController;
use App\Http\Controllers\ProjectController;
use App\Http\Controllers\AccountController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\InstallmentController;
use App\Http\Controllers\CashAdvanceController;
use App\Http\Controllers\PotentialController;
use App\Http\Controllers\CashFlowPeriodController;
use App\Http\Controllers\OperationalCashFlowItemController;
use App\Http\Controllers\CashFlowTransactionController;
use App\Http\Controllers\BudgetNeedController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('accounts', AccountController::class);
Route::put('projects/bulk', [ProjectController::class, 'bulkUpdate']);
Route::apiResource('projects', ProjectController::class);
Route::get('projects/{project}/rab-summary', [App\Http\Controllers\ProjectRabSummaryController::class, '__invoke']);
Route::post('projects/{project}/transactions', [TransactionController::class, 'storeNested']);
Route::apiResource('transactions', TransactionController::class);
Route::apiResource('debts', DebtController::class);
Route::apiResource('installments', InstallmentController::class);
Route::apiResource('cash-advances', CashAdvanceController::class);
Route::apiResource('potentials', PotentialController::class);

Route::get('transactions-summary', [TransactionController::class, 'summary']);

Route::get('transactions-export', [TransactionController::class, 'exportExcel']);

Route::resource('debt-groups', DebtGroupController::class);
Route::get('debt-groups/{debtGroup}/export', [DebtGroupController::class, 'export'])->name('debt-groups.export');
Route::post('debt-groups/{debtGroup}/import', [DebtGroupController::class, 'import'])->name('debt-groups.import');
 
Route::prefix('debt-groups/{debtGroup}')->group(function () {
    Route::get('items/create', [DebtItemController::class, 'create'])->name('debt-items.create');
    Route::post('items/bulk', [DebtItemController::class, 'bulkStore'])->name('debt-items.bulk-store');
    Route::put('items/bulk', [DebtItemController::class, 'bulkUpdate'])->name('debt-items.bulk-update');
    Route::post('items', [DebtItemController::class, 'store'])->name('debt-items.store');
    Route::get('items/{debtItem}/edit', [DebtItemController::class, 'edit'])->name('debt-items.edit');
    Route::put('items/{debtItem}', [DebtItemController::class, 'update'])->name('debt-items.update');
    Route::delete('items/{debtItem}', [DebtItemController::class, 'destroy'])->name('debt-items.destroy');
 
    Route::get('payments/create', [DebtPaymentController::class, 'create'])->name('debt-payments.create');
    Route::post('payments/bulk', [DebtPaymentController::class, 'bulkStore'])->name('debt-payments.bulk-store');
    Route::put('payments/bulk', [DebtPaymentController::class, 'bulkUpdate'])->name('debt-payments.bulk-update');
    Route::post('payments', [DebtPaymentController::class, 'store'])->name('debt-payments.store');
    Route::get('payments/{debtPayment}/edit', [DebtPaymentController::class, 'edit'])->name('debt-payments.edit');
    Route::put('payments/{debtPayment}', [DebtPaymentController::class, 'update'])->name('debt-payments.update');
    Route::delete('payments/{debtPayment}', [DebtPaymentController::class, 'destroy'])->name('debt-payments.destroy');
});

Route::apiResource('cash-flow-periods', CashFlowPeriodController::class);
Route::prefix('cash-flow-periods/{cashFlowPeriod}')->group(function () {
    Route::post('items', [OperationalCashFlowItemController::class, 'store']);
    Route::put('items/{item}', [OperationalCashFlowItemController::class, 'update']);
    Route::delete('items/{item}', [OperationalCashFlowItemController::class, 'destroy']);
    
    Route::post('transactions', [CashFlowTransactionController::class, 'store']);
    Route::put('transactions/{transaction}', [CashFlowTransactionController::class, 'update']);
    Route::delete('transactions/{transaction}', [CashFlowTransactionController::class, 'destroy']);
    
    Route::post('budget-needs', [BudgetNeedController::class, 'store']);
    Route::put('budget-needs/{budgetNeed}', [BudgetNeedController::class, 'update']);
    Route::delete('budget-needs/{budgetNeed}', [BudgetNeedController::class, 'destroy']);
});

// RAB module routes (Phase 3)
use App\Http\Controllers\RabCategoryController;
use App\Http\Controllers\RabItemController;
use App\Http\Controllers\ProgressReportController;

Route::apiResource('projects.rab-categories', RabCategoryController::class)->shallow();

// Project-scoped cash advances
Route::get('projects/{project}/cash-advances', [\App\Http\Controllers\CashAdvanceController::class, 'indexForProject']);
Route::post('projects/{project}/cash-advances', [\App\Http\Controllers\CashAdvanceController::class, 'storeForProject']);

Route::prefix('rab-categories/{rabCategory}')->group(function () {
    Route::apiResource('items', RabItemController::class);
});

Route::prefix('rab-categories/{rabCategory}/items/{rabItem}')->group(function () {
    Route::get('progress-reports', [ProgressReportController::class, 'index']);
    Route::post('progress-reports', [ProgressReportController::class, 'store']);
});