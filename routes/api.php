<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\DebtGroupController;
use App\Http\Controllers\DebtItemController;
use App\Http\Controllers\DebtPaymentController;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('transactions', TransactionController::class);

Route::get('transactions-summary', [TransactionController::class, 'summary']);

Route::get('transactions-export', [TransactionController::class, 'exportExcel']);

Route::resource('debt-groups', DebtGroupController::class);
 
Route::prefix('debt-groups/{debtGroup}')->group(function () {
    Route::get('items/create', [DebtItemController::class, 'create'])->name('debt-items.create');
    Route::post('items', [DebtItemController::class, 'store'])->name('debt-items.store');
    Route::get('items/{debtItem}/edit', [DebtItemController::class, 'edit'])->name('debt-items.edit');
    Route::put('items/{debtItem}', [DebtItemController::class, 'update'])->name('debt-items.update');
    Route::delete('items/{debtItem}', [DebtItemController::class, 'destroy'])->name('debt-items.destroy');
 
    Route::get('payments/create', [DebtPaymentController::class, 'create'])->name('debt-payments.create');
    Route::post('payments', [DebtPaymentController::class, 'store'])->name('debt-payments.store');
    Route::get('payments/{debtPayment}/edit', [DebtPaymentController::class, 'edit'])->name('debt-payments.edit');
    Route::put('payments/{debtPayment}', [DebtPaymentController::class, 'update'])->name('debt-payments.update');
    Route::delete('payments/{debtPayment}', [DebtPaymentController::class, 'destroy'])->name('debt-payments.destroy');
});