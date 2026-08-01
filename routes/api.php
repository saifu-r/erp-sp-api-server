<?php

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\RoleController;
use App\Http\Controllers\Api\UserController;

use App\Http\Controllers\Api\Manufacture\MakingHouseController;

use App\Http\Controllers\Api\Manufacture\RawMaterialController;
use App\Http\Controllers\Api\Manufacture\RawMaterialPurchaseController;
use App\Http\Controllers\Api\Manufacture\RawMaterialTransferController;
use App\Http\Controllers\Api\Manufacture\RawMaterialStockController;

use App\Http\Controllers\Api\Accounts\ExpenseTypeController;
use App\Http\Controllers\Api\Accounts\ExpenseController;
use App\Http\Controllers\Api\Accounts\AccountController;
use App\Http\Controllers\Api\Accounts\JournalController;
use App\Http\Controllers\Api\Accounts\ReportController;


// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('/login', [AuthController::class, 'login']);

// Route::middleware(['auth:sanctum', 'active_user'])->group(function () {

Route::middleware('auth:sanctum')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout']);
    Route::get('/me', [AuthController::class, 'me']);

    Route::apiResource('roles', RoleController::class);
    Route::post('/roles/{role}/permissions', [RoleController::class, 'syncPermissions']);
    Route::get('/permissions', [RoleController::class, 'allPermissions']);
    Route::get('/permissions-list', [RoleController::class, 'paginatedList']);

    Route::apiResource('users', UserController::class);

    Route::get('/making-houses', [MakingHouseController::class, 'index'])->middleware('permission:manufacturing.making-house.view');
    Route::post('/making-houses', [MakingHouseController::class, 'store'])->middleware('permission:manufacturing.making-house.create');
    Route::put('/making-houses/{makingHouse}', [MakingHouseController::class, 'update'])->middleware('permission:manufacturing.making-house.edit');
    Route::delete('/making-houses/{makingHouse}', [MakingHouseController::class, 'destroy'])->middleware('permission:manufacturing.making-house.delete');

    Route::get('/raw-materials', [RawMaterialController::class, 'index'])->middleware('permission:manufacturing.raw-material.view');
    Route::post('/raw-materials', [RawMaterialController::class, 'store'])->middleware('permission:manufacturing.raw-material.create');
    Route::put('/raw-materials/{rawMaterial}', [RawMaterialController::class, 'update'])->middleware('permission:manufacturing.raw-material.edit');
    Route::delete('/raw-materials/{rawMaterial}', [RawMaterialController::class, 'destroy'])->middleware('permission:manufacturing.raw-material.delete');

    Route::post('/raw-material-purchases', [RawMaterialPurchaseController::class, 'store'])->middleware('permission:manufacturing.raw-material-purchase.create');
    Route::post('/raw-material-transfers', [RawMaterialTransferController::class, 'store'])->middleware('permission:manufacturing.raw-material-transfer.create');

    Route::get('/raw-material-stock/company', [RawMaterialStockController::class, 'company'])->middleware('permission:manufacturing.raw-material.view');
    Route::get('/raw-material-stock/making-house/{makingHouseId}', [RawMaterialStockController::class, 'makingHouse'])->middleware('permission:manufacturing.raw-material.view');

    Route::get('/accounts', [AccountController::class, 'index'])->middleware('permission:accounts.chart-of-accounts.view');
    Route::get('/accounts/all', [AccountController::class, 'all'])->middleware('permission:accounts.chart-of-accounts.view');
    Route::get('/expense-types', [ExpenseTypeController::class, 'index'])->middleware('permission:accounts.expense-type.view');
    Route::get('/expense-types/all', [ExpenseTypeController::class, 'all'])->middleware('permission:accounts.expense-type.view');
    Route::post('/expense-types', [ExpenseTypeController::class, 'store'])->middleware('permission:accounts.expense-type.create');
    Route::put('/expense-types/{expenseType}', [ExpenseTypeController::class, 'update'])->middleware('permission:accounts.expense-type.edit');
    Route::delete('/expense-types/{expenseType}', [ExpenseTypeController::class, 'destroy'])->middleware('permission:accounts.expense-type.delete');

    Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:accounts.expense.view');
    Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:accounts.expense.create');

    Route::get('/journal', [JournalController::class, 'index'])->middleware('permission:accounts.journal.view');
    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->middleware('permission:accounts.trial-balance.view');
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])->middleware('permission:accounts.income-statement.view');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->middleware('permission:accounts.balance-sheet.view');
    });
// });
