<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\Inbox\ConversationController;
use App\Http\Controllers\Integrations\MetaIntegrationController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderNoteController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\Webhooks\MetaWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingController::class, 'home'])->name('home');

Route::get('/demo', [DemoController::class, 'show'])->name('demo');
Route::post('/demo/{variant}', [DemoController::class, 'create'])->name('demo.create');

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

    Route::get('/analitika', [AnalyticsController::class, 'index'])->name('analytics.index');

    Route::get('/ponudba', [CatalogController::class, 'index'])->name('catalog.index');
    Route::resource('products', ProductController::class)->only(['store', 'update', 'destroy']);

    Route::middleware('appointments.enabled')->group(function () {
        Route::resource('appointments', AppointmentController::class)->except(['edit']);
        Route::resource('services', ServiceController::class)->only(['store', 'update', 'destroy']);
    });

    Route::post('/follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
    Route::patch('/follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');
    Route::delete('/follow-ups/{followUp}', [FollowUpController::class, 'destroy'])->name('follow-ups.destroy');

    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/business', [SettingsController::class, 'update'])->name('settings.update');
    Route::patch('/settings/capabilities', [SettingsController::class, 'updateCapabilities'])->name('settings.capabilities.update');

    Route::get('/settings/integrations/meta/connect', [MetaIntegrationController::class, 'connect'])->name('integrations.meta.connect');
    Route::get('/settings/integrations/meta/callback', [MetaIntegrationController::class, 'callback'])->name('integrations.meta.callback');
    Route::post('/settings/integrations/meta/channels', [MetaIntegrationController::class, 'store'])->name('integrations.meta.store');
    Route::delete('/settings/integrations/meta/pending', [MetaIntegrationController::class, 'cancel'])->name('integrations.meta.cancel');
    Route::delete('/settings/integrations/meta/channels/{channel}', [MetaIntegrationController::class, 'destroy'])->name('integrations.meta.disconnect');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// Meta calls these directly — no session, no auth, no CSRF (verified via
// hub.verify_token on GET and X-Hub-Signature-256 on POST instead).
Route::get('/webhooks/meta', [MetaWebhookController::class, 'verify'])->name('webhooks.meta.verify');
Route::post('/webhooks/meta', [MetaWebhookController::class, 'handle'])->name('webhooks.meta.handle');

require __DIR__.'/auth.php';
