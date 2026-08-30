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

use App\Http\Controllers\Api\Purchase\SupplierController;
use App\Http\Controllers\Api\Purchase\PurchaseController;

use App\Http\Controllers\Api\Manufacture\ItemController;
use App\Http\Controllers\Api\Manufacture\ProductionController;

use App\Http\Controllers\Api\CompanySettingController;
use App\Http\Controllers\Api\DashboardController;
use App\Http\Controllers\Api\Sales\CustomerController;
use App\Http\Controllers\Api\Sales\InvoiceController;
use App\Http\Controllers\Api\Sales\ProductController;
use App\Http\Controllers\Api\Sales\OrderController;
use App\Http\Controllers\Api\Sales\QuotationController;

use App\Http\Controllers\Api\StockAdjustmentController;
use App\Http\Controllers\Api\LedgerAdjustmentController;

use App\Http\Controllers\Api\Accounts\CashFlowController;

// Route::middleware('auth:sanctum')->get('/user', function (Request $request) {
//     return $request->user();
// });

Route::post('/login', [AuthController::class, 'login']);

// Route::middleware(['auth:sanctum', 'active_user'])->group(function () {

Route::get('/company-settings', [CompanySettingController::class, 'show']); // no permission gate — every logged-in user needs this to render dates correctly


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
    Route::get('/reports/cash-flow', [CashFlowController::class, 'index'])->middleware('permission:accounts.cash-flow.view');

    Route::get('/expenses', [ExpenseController::class, 'index'])->middleware('permission:accounts.expense.view');
    Route::post('/expenses', [ExpenseController::class, 'store'])->middleware('permission:accounts.expense.create');

    Route::get('/journal', [JournalController::class, 'index'])->middleware('permission:accounts.journal.view');
    Route::get('/reports/trial-balance', [ReportController::class, 'trialBalance'])->middleware('permission:accounts.trial-balance.view');
    Route::get('/reports/income-statement', [ReportController::class, 'incomeStatement'])->middleware('permission:accounts.income-statement.view');
    Route::get('/reports/balance-sheet', [ReportController::class, 'balanceSheet'])->middleware('permission:accounts.balance-sheet.view');

    Route::get('/suppliers', [SupplierController::class, 'index'])->middleware('permission:purchase.supplier.view');
    Route::get('/suppliers/all', [SupplierController::class, 'all'])->middleware('permission:purchase.supplier.view');
    Route::post('/suppliers', [SupplierController::class, 'store'])->middleware('permission:purchase.supplier.create');
    Route::put('/suppliers/{supplier}', [SupplierController::class, 'update'])->middleware('permission:purchase.supplier.edit');
    Route::delete('/suppliers/{supplier}', [SupplierController::class, 'destroy'])->middleware('permission:purchase.supplier.delete');

    Route::get('/purchases', [PurchaseController::class, 'index'])->middleware('permission:purchase.purchase.view');
    Route::get('/purchases/{purchase}', [PurchaseController::class, 'show'])->middleware('permission:purchase.purchase.view');
    Route::post('/purchases', [PurchaseController::class, 'store'])->middleware('permission:purchase.purchase.create');
    Route::post('/purchases/{purchase}/payments', [PurchaseController::class, 'recordPayment'])->middleware('permission:purchase.purchase-payment.create');
    Route::get('/payments', [PurchaseController::class, 'payments'])->middleware('permission:purchase.purchase-payment.view');
    Route::get('/expenses/{expense}', [ExpenseController::class, 'show'])->middleware('permission:accounts.expense.view');


    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:sales.customer.view');
    Route::get('/customers/all', [CustomerController::class, 'all'])->middleware('permission:sales.customer.view');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:sales.customer.create');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:sales.customer.edit');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:sales.customer.delete');

    Route::get('/items', [ItemController::class, 'index'])->middleware('permission:manufacturing.items.view');
    Route::get('/items/all', [ItemController::class, 'all'])->middleware('permission:manufacturing.items.view');
    Route::post('/items', [ItemController::class, 'store'])->middleware('permission:manufacturing.items.create');
    Route::put('/items/{item}', [ItemController::class, 'update'])->middleware('permission:manufacturing.items.edit');
    Route::delete('/items/{item}', [ItemController::class, 'destroy'])->middleware('permission:manufacturing.items.delete');
    Route::put('/items/{item}/recipe', [ItemController::class, 'updateRecipe'])->middleware('permission:manufacturing.items.edit');

    Route::get('/productions', [ProductionController::class, 'index'])->middleware('permission:manufacturing.productions.view');
    Route::get('/productions/{production}', [ProductionController::class, 'show'])->middleware('permission:manufacturing.productions.view');
    Route::post('/productions', [ProductionController::class, 'store'])->middleware('permission:manufacturing.productions.create');

    Route::put('/company-settings', [CompanySettingController::class, 'update'])->middleware('permission:settings.company-profile.edit');

    Route::get('/customers', [CustomerController::class, 'index'])->middleware('permission:sales.customer.view');
    Route::get('/customers/all', [CustomerController::class, 'all'])->middleware('permission:sales.customer.view');
    Route::post('/customers', [CustomerController::class, 'store'])->middleware('permission:sales.customer.create');
    Route::put('/customers/{customer}', [CustomerController::class, 'update'])->middleware('permission:sales.customer.edit');
    Route::delete('/customers/{customer}', [CustomerController::class, 'destroy'])->middleware('permission:sales.customer.delete');

    Route::get('/products', [ProductController::class, 'index'])->middleware('permission:sales.product.view');
    Route::get('/products/all', [ProductController::class, 'all'])->middleware('permission:sales.product.view');
    Route::post('/products', [ProductController::class, 'store'])->middleware('permission:sales.product.create');
    Route::put('/products/{product}', [ProductController::class, 'update'])->middleware('permission:sales.product.edit');
    Route::delete('/products/{product}', [ProductController::class, 'destroy'])->middleware('permission:sales.product.delete');

    Route::get('/orders', [OrderController::class, 'index'])->middleware('permission:sales.order.view');
    Route::get('/orders/{order}', [OrderController::class, 'show'])->middleware('permission:sales.order.view');
    Route::post('/orders', [OrderController::class, 'store'])->middleware('permission:sales.order.create');

    // Invoice — the real transaction
    Route::get('/invoices', [InvoiceController::class, 'index'])->middleware('permission:sales.invoice.view');
    Route::get('/invoices/{invoice}', [InvoiceController::class, 'show'])->middleware('permission:sales.invoice.view');
    Route::post('/invoices', [InvoiceController::class, 'store'])->middleware('permission:sales.invoice.create');
    Route::post('/invoices/{invoice}/payments', [InvoiceController::class, 'recordPayment'])->middleware('permission:sales.payment.create');

    Route::get('/invoice-payments', [InvoiceController::class, 'payments'])->middleware('permission:sales.payment.view');
    Route::post('/invoices/{invoice}/write-off', [InvoiceController::class, 'writeOff'])->middleware('permission:sales.write-off.create');
    Route::get('/quotations', [QuotationController::class, 'index'])->middleware('permission:sales.quotation.view');
    Route::get('/quotations/{quotation}', [QuotationController::class, 'show'])->middleware('permission:sales.quotation.view');
    Route::post('/quotations', [QuotationController::class, 'store'])->middleware('permission:sales.quotation.create');

    Route::post('/orders/{order}/invoice', [OrderController::class, 'markInvoiced'])->middleware('permission:sales.invoice.create');

    Route::get('/dashboard', [DashboardController::class, 'index']);

    Route::get('/stock-adjustments', [StockAdjustmentController::class, 'index'])->middleware('permission:adjustment.stock-adjustment.view');
    Route::post('/stock-adjustments', [StockAdjustmentController::class, 'store'])->middleware('permission:adjustment.stock-adjustment.create');

    Route::get('/ledger-adjustments', [LedgerAdjustmentController::class, 'index'])->middleware('permission:adjustment.ledger-adjustment.view');
    Route::post('/ledger-adjustments', [LedgerAdjustmentController::class, 'store'])->middleware('permission:adjustment.ledger-adjustment.create');
});
// });
