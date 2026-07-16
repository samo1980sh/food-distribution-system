<?php

use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\HealthController;
use App\Http\Controllers\Api\V1\Operational\CustomerController;
use App\Http\Controllers\Api\V1\Operational\CustomerPaymentController;
use App\Http\Controllers\Api\V1\Operational\DailyClosingController;
use App\Http\Controllers\Api\V1\Operational\OperationalBootstrapController;
use App\Http\Controllers\Api\V1\Operational\ProductController;
use App\Http\Controllers\Api\V1\Operational\RouteController as OperationalRouteController;
use App\Http\Controllers\Api\V1\Operational\SalesInvoiceController;
use App\Http\Controllers\Api\V1\Operational\SalesReturnController;
use App\Http\Controllers\Api\V1\Operational\StockBalanceController;
use App\Http\Controllers\Api\V1\Operational\VehicleController;
use App\Http\Controllers\Api\V1\Operational\VehicleExpenseController;
use App\Http\Controllers\Api\V1\Operational\VehicleLoadController;
use App\Http\Controllers\Api\V1\Operational\WarehouseController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')
    ->middleware('api.version')
    ->group(function (): void {
        Route::get('/health', HealthController::class)
            ->middleware('throttle:mobile-api')
            ->name('api.v1.health');

        Route::post('/auth/login', [AuthController::class, 'login'])
            ->middleware('throttle:mobile-login')
            ->name('api.v1.auth.login');

        Route::middleware([
            'auth:sanctum',
            'api.access',
            'api.token.touch',
            'throttle:mobile-api',
        ])->group(function (): void {
            Route::get('/auth/me', [AuthController::class, 'me'])
                ->name('api.v1.auth.me');

            Route::get('/auth/sessions', [AuthController::class, 'sessions'])
                ->name('api.v1.auth.sessions');

            Route::delete('/auth/sessions/{token}', [AuthController::class, 'destroySession'])
                ->whereNumber('token')
                ->name('api.v1.auth.sessions.destroy');

            Route::post('/auth/logout', [AuthController::class, 'logout'])
                ->name('api.v1.auth.logout');

            Route::post('/auth/logout-all', [AuthController::class, 'logoutAll'])
                ->name('api.v1.auth.logout-all');

            Route::prefix('operational')
                ->name('api.v1.operational.')
                ->group(function (): void {
                    Route::get('/bootstrap', OperationalBootstrapController::class)
                        ->name('bootstrap');

                    Route::apiResource('routes', OperationalRouteController::class)
                        ->only(['index', 'show'])
                        ->parameters(['routes' => 'distributionRoute'])
                        ->middleware('can:distribution_routes.view');
                    Route::apiResource('vehicles', VehicleController::class)
                        ->only(['index', 'show'])
                        ->middleware('can:vehicles.view');
                    Route::apiResource('warehouses', WarehouseController::class)
                        ->only(['index', 'show'])
                        ->middleware('can:warehouses.view');
                    Route::apiResource('products', ProductController::class)
                        ->only(['index', 'show'])
                        ->middleware('can:products.view');
                    Route::apiResource('customers', CustomerController::class)
                        ->only(['index', 'show'])
                        ->middleware('can:customers.view');
                    Route::apiResource('stock-balances', StockBalanceController::class)
                        ->only(['index', 'show'])
                        ->parameters(['stock-balances' => 'stockBalance'])
                        ->middleware('can:stock_balances.view');
                    Route::apiResource('vehicle-loads', VehicleLoadController::class)
                        ->only(['index', 'show'])
                        ->parameters(['vehicle-loads' => 'vehicleLoad'])
                        ->middleware('can:vehicle_loads.view');
                    Route::apiResource('sales-invoices', SalesInvoiceController::class)
                        ->only(['index', 'show'])
                        ->parameters(['sales-invoices' => 'salesInvoice'])
                        ->middleware('can:sales_invoices.view');
                    Route::apiResource('customer-payments', CustomerPaymentController::class)
                        ->only(['index', 'show'])
                        ->parameters(['customer-payments' => 'customerPayment'])
                        ->middleware('can:customer_payments.view');
                    Route::apiResource('sales-returns', SalesReturnController::class)
                        ->only(['index', 'show'])
                        ->parameters(['sales-returns' => 'salesReturn'])
                        ->middleware('can:sales_returns.view');
                    Route::apiResource('vehicle-expenses', VehicleExpenseController::class)
                        ->only(['index', 'show'])
                        ->parameters(['vehicle-expenses' => 'vehicleExpense'])
                        ->middleware('can:vehicle_expenses.view');
                    Route::apiResource('daily-closings', DailyClosingController::class)
                        ->only(['index', 'show'])
                        ->parameters(['daily-closings' => 'dailyClosing'])
                        ->middleware('can:daily_closings.view');
                });

        });
    });
