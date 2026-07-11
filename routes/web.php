<?php

use App\Http\Controllers\Reports\DailyClosingFilteredPrintController;
use App\Http\Controllers\Reports\DailyClosingPrintController;
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