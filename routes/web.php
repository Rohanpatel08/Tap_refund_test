<?php

use App\Http\Controllers\TapRefundController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});


Route::prefix('admin')->group(function () {
    Route::get('payments', [TapRefundController::class, 'index'])->name('admin.payments.index');
    Route::get('payments/{charge}/refund', [TapRefundController::class, 'showRefundForm'])->name('admin.payments.refund_form');
    Route::post('payments/{charge}/refund', [TapRefundController::class, 'processRefund'])->name('admin.payments.process_refund');
    Route::get('refunds', [TapRefundController::class, 'refundsIndex'])->name('admin.refunds.index');
});
