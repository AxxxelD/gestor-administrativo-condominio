<?php

use App\Http\Controllers\AccountingController;
use App\Http\Controllers\CommunicationController;
use App\Http\Controllers\CommunityController;
use App\Http\Controllers\DocumentController;
use App\Http\Controllers\WorkspaceController;
use Illuminate\Support\Facades\Route;

Route::view('/', 'app')->name('home');
Route::view('/login', 'app')->name('login');
require __DIR__.'/auth.php';

Route::middleware(['auth', 'active'])->group(function (): void {
    Route::get('/api/workspace', WorkspaceController::class);
    Route::post('/api/properties', [CommunityController::class, 'property']);
    Route::put('/api/properties/{property}', [CommunityController::class, 'property']);
    Route::post('/api/properties/{property}/opening', [CommunityController::class, 'opening']);
    Route::get('/api/properties/{property}/statement', [CommunityController::class, 'statement']);
    Route::post('/api/people', [CommunityController::class, 'person']);
    Route::put('/api/people/{person}', [CommunityController::class, 'person']);
    Route::post('/api/occupancies', [CommunityController::class, 'occupancy']);
    Route::put('/api/occupancies/{occupancy}', [CommunityController::class, 'occupancy']);
    Route::post('/api/periods', [AccountingController::class, 'period']);
    Route::post('/api/periods/{period}/load-recurring', [AccountingController::class, 'recurring']);
    Route::post('/api/periods/{period}/items', [AccountingController::class, 'item']);
    Route::post('/api/periods/{period}/issue', [AccountingController::class, 'issue']);
    Route::put('/api/items/{item}', [AccountingController::class, 'updateItem']);
    Route::delete('/api/items/{item}', [AccountingController::class, 'deleteItem']);
    Route::post('/api/items/{item}/attachment', [AccountingController::class, 'attachment']);
    Route::get('/api/items/{item}/attachment', [AccountingController::class, 'downloadAttachment']);
    Route::post('/api/templates', [AccountingController::class, 'template']);
    Route::put('/api/templates/{template}', [AccountingController::class, 'template']);
    Route::post('/api/banks', [AccountingController::class, 'bank']);
    Route::post('/api/payments', [AccountingController::class, 'payment']);
    Route::post('/api/payments/{payment}/void', [AccountingController::class, 'voidPayment']);
    Route::post('/api/expenses', [AccountingController::class, 'expense']);
    Route::post('/api/expenses/{expense}/void', [AccountingController::class, 'voidExpense']);
    Route::post('/api/funds/opening', [AccountingController::class, 'fundOpening']);
    Route::get('/api/rates/{date}', [AccountingController::class, 'rate'])->middleware('throttle:20,1');
    Route::post('/api/rates', [AccountingController::class, 'saveRate']);
    Route::put('/api/settings', [AccountingController::class, 'settings']);
    Route::get('/documents/charges/{charge}', [DocumentController::class, 'charge'])->name('documents.charge');
    Route::get('/documents/payments/{payment}', [DocumentController::class, 'payment'])->name('documents.payment');
    Route::get('/documents/properties/{property}', [DocumentController::class, 'statement'])->name('documents.statement');
    Route::post('/api/communications', [CommunicationController::class, 'store']);
    Route::get('/api/deliveries/{delivery}/preview', [CommunicationController::class, 'preview']);
    Route::post('/api/deliveries/{delivery}/retry', [CommunicationController::class, 'retry']);
});
