<?php

use App\Http\Controllers\CustomerController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\Inbox\ConversationController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderNoteController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TodayController;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return redirect()->route(auth()->check() ? 'dashboard' : 'login');
});

Route::middleware('auth')->group(function () {
    Route::get('/today', TodayController::class)->name('dashboard');

    Route::get('/search', SearchController::class)->name('search');

    Route::get('/inbox', [ConversationController::class, 'index'])->name('inbox.index');
    Route::get('/inbox/{conversation}', [ConversationController::class, 'show'])->name('inbox.show');
    Route::post('/inbox/{conversation}/messages', [ConversationController::class, 'sendMessage'])->name('inbox.messages.store');
    Route::patch('/inbox/{conversation}', [ConversationController::class, 'update'])->name('inbox.update');
    Route::post('/inbox/{conversation}/create-customer', [ConversationController::class, 'createCustomer'])->name('inbox.create-customer');
    Route::post('/inbox/{conversation}/notes', [ConversationController::class, 'addNote'])->name('inbox.notes.store');

    Route::resource('customers', CustomerController::class)->except(['edit']);
    Route::resource('orders', OrderController::class)->except(['edit']);
    Route::post('/orders/{order}/notes', [OrderNoteController::class, 'store'])->name('orders.notes.store');

    Route::post('/follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
    Route::patch('/follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');
    Route::delete('/follow-ups/{followUp}', [FollowUpController::class, 'destroy'])->name('follow-ups.destroy');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/business', [SettingsController::class, 'update'])->name('settings.update');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

require __DIR__.'/auth.php';
