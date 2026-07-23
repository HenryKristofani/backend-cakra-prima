<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use App\Http\Controllers\TransactionController;


Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

Route::apiResource('transactions', TransactionController::class);

Route::get('transactions-summary', [TransactionController::class, 'summary']);
Route::get('transactions-export', [TransactionController::class, 'exportExcel']);