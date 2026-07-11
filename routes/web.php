<?php

use App\Http\Controllers\Reports\CustomerPaymentPrintController;
use App\Http\Controllers\Reports\CustomerPaymentReportFilteredPrintController;
use App\Http\Controllers\Reports\DailyClosingFilteredPrintController;
use App\Http\Controllers\Reports\DailyClosingPrintController;
use App\Http\Controllers\Reports\SalesInvoicePrintController;
use App\Http\Controllers\Reports\SalesReportFilteredPrintController;
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