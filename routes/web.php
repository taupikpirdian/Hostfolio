<?php

use App\Http\Controllers\InvoicePdfController;
use App\Http\Controllers\ContractUnpaidInvoicePdfController;
use App\Http\Controllers\PaymentProofController;
use App\Http\Controllers\ReportExportController;
use Illuminate\Support\Facades\Route;

Route::get('/reports/export', ReportExportController::class)->middleware('auth')->name('reports.export');

Route::get('/payments/{payment}/proof', PaymentProofController::class)->middleware(['auth', 'signed'])->name('payments.proof');
Route::get('/invoices/{invoice}/pdf', InvoicePdfController::class)->middleware('auth')->name('invoices.pdf');
Route::get('/compute-instances/{computeInstance}/unpaid-contract-invoices.pdf', ContractUnpaidInvoicePdfController::class)->middleware('auth')->name('invoices.contract-unpaid-pdf');
