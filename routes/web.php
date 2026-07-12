<?php

use App\Http\Controllers\Reports\CustomerPaymentPrintController;
use App\Http\Controllers\Reports\CustomerPaymentReportFilteredPrintController;
use App\Http\Controllers\Reports\CustomerStatementPrintController;
use App\Http\Controllers\Reports\DailyClosingFilteredPrintController;
use App\Http\Controllers\Reports\DailyClosingPrintController;
use App\Http\Controllers\Reports\ExpiryRiskPrintController;
use App\Http\Controllers\Reports\ExpiryRiskReportFilteredPrintController;
use App\Http\Controllers\Reports\OverdueCustomerPrintController;
use App\Http\Controllers\Reports\OverdueCustomerReportFilteredPrintController;
use App\Http\Controllers\Reports\ProfitReportFilteredPrintController;
use App\Http\Controllers\Reports\SalesInvoicePrintController;
use App\Http\Controllers\Reports\SalesReportFilteredPrintController;
use App\Http\Controllers\Reports\SalesReturnPrintController;
use App\Http\Controllers\Reports\SalesReturnReportFilteredPrintController;
use App\Http\Controllers\Reports\VehicleExpensePrintController;
use App\Http\Controllers\Reports\VehicleExpenseReportFilteredPrintController;
use App\Http\Controllers\Reports\VehicleLoadPrintController;
use App\Http\Controllers\Reports\VehicleLoadReportFilteredPrintController;
use App\Http\Controllers\Reports\VehicleStockPrintController;
use App\Http\Controllers\Reports\VehicleStockReportFilteredPrintController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('welcome');
});

Route::get(
    '/admin/reports/daily-closings/print-filtered',
    DailyClosingFilteredPrintController::class,
)->name('reports.daily-closings.print-filtered');

Route::get(
    '/admin/reports/daily-closings/{dailyClosing}/print',
    DailyClosingPrintController::class,
)->name('reports.daily-closings.print');

Route::get(
    '/admin/reports/sales-invoices/print-filtered',
    SalesReportFilteredPrintController::class,
)->name('reports.sales-invoices.print-filtered');

Route::get(
    '/admin/reports/sales-invoices/{salesInvoice}/print',
    SalesInvoicePrintController::class,
)->name('reports.sales-invoices.print');

Route::get(
    '/admin/reports/customer-payments/print-filtered',
    CustomerPaymentReportFilteredPrintController::class,
)->name('reports.customer-payments.print-filtered');

Route::get(
    '/admin/reports/customer-payments/{customerPayment}/print',
    CustomerPaymentPrintController::class,
)->name('reports.customer-payments.print');

Route::get(
    '/admin/reports/customer-statement/print',
    CustomerStatementPrintController::class,
)->name('reports.customer-statement.print');

Route::get(
    '/admin/reports/sales-returns/print-filtered',
    SalesReturnReportFilteredPrintController::class,
)->name('reports.sales-returns.print-filtered');

Route::get(
    '/admin/reports/sales-returns/{salesReturn}/print',
    SalesReturnPrintController::class,
)->name('reports.sales-returns.print');

Route::get(
    '/admin/reports/vehicle-loads/print-filtered',
    VehicleLoadReportFilteredPrintController::class,
)->name('reports.vehicle-loads.print-filtered');

Route::get(
    '/admin/reports/vehicle-loads/{vehicleLoad}/print',
    VehicleLoadPrintController::class,
)->name('reports.vehicle-loads.print');

Route::get(
    '/admin/reports/vehicle-stock/print-filtered',
    VehicleStockReportFilteredPrintController::class,
)->name('reports.vehicle-stock.print-filtered');

Route::get(
    '/admin/reports/vehicle-stock/vehicles/{vehicle}/print',
    VehicleStockPrintController::class,
)->name('reports.vehicle-stock.vehicle.print');

Route::get(
    '/admin/reports/profit/print-filtered',
    ProfitReportFilteredPrintController::class,
)->name('reports.profit.print-filtered');

Route::get(
    '/admin/reports/vehicle-expenses/print-filtered',
    VehicleExpenseReportFilteredPrintController::class,
)->name('reports.vehicle-expenses.print-filtered');

Route::get(
    '/admin/reports/vehicle-expenses/{vehicleExpense}/print',
    VehicleExpensePrintController::class,
)->name('reports.vehicle-expenses.print');

Route::get(
    '/admin/reports/expiry-risk/print-filtered',
    ExpiryRiskReportFilteredPrintController::class,
)->name('reports.expiry-risk.print-filtered');

Route::get(
    '/admin/reports/expiry-risk/{stockBalance}/print',
    ExpiryRiskPrintController::class,
)->name('reports.expiry-risk.print');

Route::get(
    '/admin/reports/overdue-customers/print-filtered',
    OverdueCustomerReportFilteredPrintController::class,
)->name('reports.overdue-customers.print-filtered');

Route::get(
    '/admin/reports/overdue-customers/{customer}/print',
    OverdueCustomerPrintController::class,
)->name('reports.overdue-customers.print');

