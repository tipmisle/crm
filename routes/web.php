<?php

use App\Http\Controllers\AnalyticsController;
use App\Http\Controllers\AppointmentController;
use App\Http\Controllers\AppointmentNotificationController;
use App\Http\Controllers\Billing\ActivationController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CustomerController;
use App\Http\Controllers\Customers\PrivacyController as CustomerPrivacyController;
use App\Http\Controllers\DemoController;
use App\Http\Controllers\FollowUpController;
use App\Http\Controllers\Inbox\AttachmentController;
use App\Http\Controllers\Inbox\ConversationController;
use App\Http\Controllers\Integrations\MetaIntegrationController;
use App\Http\Controllers\Invoicing\DocumentsController;
use App\Http\Controllers\Invoicing\ExternalDocumentController;
use App\Http\Controllers\Invoicing\SalesDocumentController;
use App\Http\Controllers\Invoicing\SalesDocumentCorrectionController;
use App\Http\Controllers\Invoicing\SalesDocumentDownloadController;
use App\Http\Controllers\Invoicing\SalesDocumentReminderController;
use App\Http\Controllers\Invoicing\SalesDocumentSendController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MarketingController;
use App\Http\Controllers\OnboardingController;
use App\Http\Controllers\OrderController;
use App\Http\Controllers\OrderNoteController;
use App\Http\Controllers\OrderNotificationController;
use App\Http\Controllers\ProductController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\PushSubscriptionController;
use App\Http\Controllers\SearchController;
use App\Http\Controllers\ServiceController;
use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\InvoiceSettingsController;
use App\Http\Controllers\Settings\OrderStatusController;
use App\Http\Controllers\Settings\PaymentStatusController;
use App\Http\Controllers\Settings\StatusesController as SettingsStatusesController;
use App\Http\Controllers\Settings\SupportAccessController;
use App\Http\Controllers\Settings\WorkspaceExportController;
use App\Http\Controllers\Settings\WorkspacePrivacyController;
use App\Http\Controllers\SettingsController;
use App\Http\Controllers\TodayController;
use App\Http\Controllers\Webhooks\MetaWebhookController;
use App\Http\Controllers\Webhooks\StripeWebhookController;
use Illuminate\Support\Facades\Route;

Route::get('/', [MarketingController::class, 'home'])->name('home');

Route::get('/demo', [DemoController::class, 'show'])->name('demo');
Route::post('/demo/{variant}', [DemoController::class, 'create'])->name('demo.create');

// Public legal pages — no auth/workspace required, never redirect an
// authenticated user away. See docs/legal-compliance.md.
Route::get('/pogoji-poslovanja', [LegalController::class, 'terms'])->name('legal.terms');
Route::get('/zasebnost', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/piskotki', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::get('/obdelava-osebnih-podatkov', [LegalController::class, 'dpa'])->name('legal.dpa');
Route::get('/podatki-o-ponudniku', [LegalController::class, 'provider'])->name('legal.provider');
Route::get('/podobdelovalci', [LegalController::class, 'subprocessors'])->name('legal.subprocessors');

Route::middleware('auth')->group(function () {
    // Billing management/activation and account/GDPR routes stay reachable
    // regardless of subscription state — never trap an unpaid/canceled
    // owner in an app they can't exit, pay, or delete. See docs/billing.md.
    Route::get('/billing/activate', [ActivationController::class, 'edit'])->name('billing.activate');
    Route::post('/billing/activate/checkout', [ActivationController::class, 'checkout'])->name('billing.activate.checkout');
    Route::get('/billing/activate/success', [ActivationController::class, 'success'])->name('billing.activate.success');

    Route::get('/settings', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/settings/business', [SettingsController::class, 'update'])->name('settings.update');
    Route::patch('/settings/capabilities', [SettingsController::class, 'updateCapabilities'])->name('settings.capabilities.update');

    Route::get('/settings/support', [SupportAccessController::class, 'edit'])->name('settings.support.edit');
    Route::post('/settings/support', [SupportAccessController::class, 'store'])->name('settings.support.store');
    Route::delete('/settings/support', [SupportAccessController::class, 'destroy'])->name('settings.support.destroy');

    Route::get('/settings/billing', [BillingController::class, 'edit'])->name('settings.billing.edit');
    Route::get('/settings/billing/portal', [BillingController::class, 'portal'])->name('settings.billing.portal');

    Route::get('/settings/statuses', [SettingsStatusesController::class, 'edit'])->name('settings.statuses.edit');
    Route::post('/settings/statuses/order', [OrderStatusController::class, 'store'])->name('settings.statuses.order.store');
    Route::patch('/settings/statuses/order/{orderStatus}', [OrderStatusController::class, 'update'])->name('settings.statuses.order.update');
    Route::delete('/settings/statuses/order/{orderStatus}', [OrderStatusController::class, 'destroy'])->name('settings.statuses.order.destroy');
    Route::post('/settings/statuses/order/reorder', [OrderStatusController::class, 'reorder'])->name('settings.statuses.order.reorder');

    Route::post('/settings/statuses/payment', [PaymentStatusController::class, 'store'])->name('settings.statuses.payment.store');
    Route::patch('/settings/statuses/payment/{paymentStatus}', [PaymentStatusController::class, 'update'])->name('settings.statuses.payment.update');
    Route::delete('/settings/statuses/payment/{paymentStatus}', [PaymentStatusController::class, 'destroy'])->name('settings.statuses.payment.destroy');
    Route::post('/settings/statuses/payment/reorder', [PaymentStatusController::class, 'reorder'])->name('settings.statuses.payment.reorder');

    Route::get('/settings/invoicing', [InvoiceSettingsController::class, 'edit'])->name('settings.invoicing.edit');
    Route::patch('/settings/invoicing', [InvoiceSettingsController::class, 'update'])->name('settings.invoicing.update');
    Route::post('/settings/invoicing/logo', [InvoiceSettingsController::class, 'updateLogo'])->name('settings.invoicing.logo.update');
    Route::delete('/settings/invoicing/logo', [InvoiceSettingsController::class, 'destroyLogo'])->name('settings.invoicing.logo.destroy');
    Route::get('/settings/invoicing/preview', [InvoiceSettingsController::class, 'preview'])->name('settings.invoicing.preview');

    Route::get('/settings/privacy', [WorkspacePrivacyController::class, 'edit'])->name('settings.privacy.edit');
    Route::get('/settings/privacy/export/{export}/download', [WorkspaceExportController::class, 'download'])->name('settings.privacy.export.download');

    // Destructive/data-generating mutations require a recently-confirmed
    // password on top of normal auth — same pattern as routes/admin.php.
    Route::middleware('password.confirm')->group(function () {
        Route::post('/settings/privacy/delete', [WorkspacePrivacyController::class, 'requestDeletion'])->name('settings.privacy.delete');
        Route::delete('/settings/privacy/delete', [WorkspacePrivacyController::class, 'cancelDeletion'])->name('settings.privacy.cancel');
        Route::post('/settings/privacy/export', [WorkspaceExportController::class, 'store'])->name('settings.privacy.export.store');
    });

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    // Normal product surface — requires an active subscription for real
    // (non-demo) workspaces. Demo bypasses entirely. See docs/billing.md.
    // onboarding.gate additionally bounces an incomplete (non-demo)
    // workspace to /onboarding first; the onboarding routes themselves and
    // the Meta OAuth round-trip are exempt from that bounce (see
    // EnsureOnboardingComplete).
    Route::middleware(['subscription.active', 'onboarding.gate'])->group(function () {
        Route::get('/onboarding', [OnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('/onboarding/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

        Route::get('/today', TodayController::class)->name('dashboard');

        Route::get('/search', SearchController::class)->name('search');

        Route::get('/inbox', [ConversationController::class, 'index'])->name('inbox.index');
        Route::get('/inbox/{conversation}', [ConversationController::class, 'show'])->name('inbox.show');
        Route::post('/inbox/{conversation}/messages', [ConversationController::class, 'sendMessage'])->name('inbox.messages.store');
        Route::patch('/inbox/{conversation}', [ConversationController::class, 'update'])->name('inbox.update');
        Route::post('/inbox/{conversation}/create-customer', [ConversationController::class, 'createCustomer'])->name('inbox.create-customer');
        Route::post('/inbox/{conversation}/notes', [ConversationController::class, 'addNote'])->name('inbox.notes.store');
        Route::get('/inbox/attachments/{message}/{index}', [AttachmentController::class, 'show'])->name('inbox.attachments.show');

        // No customers.destroy: deleting a Customer would cascade-delete
        // Orders/Appointments (cascadeOnDelete FKs). GDPR removal is
        // customers.privacy.erase (anonymization), which preserves
        // operational/financial history. See CustomerController.
        Route::resource('customers', CustomerController::class)->except(['edit', 'destroy']);
        Route::middleware('orders.enabled')->group(function () {
            Route::resource('orders', OrderController::class)->except(['edit']);
            Route::get('/orders-export', [OrderController::class, 'exportCsv'])->name('orders.export');
            Route::post('/orders/{order}/notes', [OrderNoteController::class, 'store'])->name('orders.notes.store');
            Route::post('/orders/{order}/notify', [OrderNotificationController::class, 'store'])->name('orders.notify.store');

            Route::get('/orders/{order}/documents/create', [SalesDocumentController::class, 'create'])->name('orders.documents.create');
            Route::post('/orders/{order}/documents', [SalesDocumentController::class, 'store'])->name('orders.documents.store');
            Route::post('/orders/{order}/documents/external', [ExternalDocumentController::class, 'store'])->name('orders.documents.external.store');
            Route::resource('products', ProductController::class)->only(['store', 'update', 'destroy']);
        });

        Route::get('/documents/{document}/download', [SalesDocumentDownloadController::class, 'show'])->name('documents.download');
        Route::post('/documents/{document}/send', [SalesDocumentSendController::class, 'store'])->name('documents.send');
        Route::post('/documents/{document}/remind', [SalesDocumentReminderController::class, 'store'])->name('documents.remind');
        Route::post('/documents/{document}/cancel', [SalesDocumentCorrectionController::class, 'cancelProforma'])->name('documents.cancel');
        Route::post('/documents/{document}/storno', [SalesDocumentCorrectionController::class, 'storno'])->name('documents.storno');

        Route::get('/dokumenti', [DocumentsController::class, 'index'])->name('documents.index');

        Route::get('/analitika', [AnalyticsController::class, 'index'])->name('analytics.index');

        Route::get('/ponudba', [CatalogController::class, 'index'])->name('catalog.index');

        Route::middleware('appointments.enabled')->group(function () {
            Route::resource('appointments', AppointmentController::class)->except(['edit']);
            Route::get('/appointments-export', [AppointmentController::class, 'exportCsv'])->name('appointments.export');
            Route::post('/appointments/{appointment}/notify', [AppointmentNotificationController::class, 'store'])->name('appointments.notify.store');
            Route::get('/appointments/{appointment}/documents/create', [SalesDocumentController::class, 'createForAppointment'])->name('appointments.documents.create');
            Route::post('/appointments/{appointment}/documents', [SalesDocumentController::class, 'storeForAppointment'])->name('appointments.documents.store');
            Route::post('/appointments/{appointment}/documents/external', [ExternalDocumentController::class, 'storeForAppointment'])->name('appointments.documents.external.store');
            Route::resource('services', ServiceController::class)->only(['store', 'update', 'destroy']);
        });

        Route::post('/follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
        Route::patch('/follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');
        Route::delete('/follow-ups/{followUp}', [FollowUpController::class, 'destroy'])->name('follow-ups.destroy');

        Route::post('/customers/{customer}/privacy/export', [CustomerPrivacyController::class, 'exportData'])->name('customers.privacy.export');
        Route::post('/customers/{customer}/privacy/erase', [CustomerPrivacyController::class, 'eraseData'])->name('customers.privacy.erase');

        Route::get('/settings/integrations/meta/connect', [MetaIntegrationController::class, 'connect'])->name('integrations.meta.connect');
        Route::get('/settings/integrations/meta/callback', [MetaIntegrationController::class, 'callback'])->name('integrations.meta.callback');
        Route::post('/settings/integrations/meta/channels', [MetaIntegrationController::class, 'store'])->name('integrations.meta.store');
        Route::delete('/settings/integrations/meta/pending', [MetaIntegrationController::class, 'cancel'])->name('integrations.meta.cancel');
        Route::delete('/settings/integrations/meta/channels/{channel}', [MetaIntegrationController::class, 'destroy'])->name('integrations.meta.disconnect');
    });
});

// Meta calls these directly — no session, no auth, no CSRF (verified via
// hub.verify_token on GET and X-Hub-Signature-256 on POST instead).
Route::get('/webhooks/meta', [MetaWebhookController::class, 'verify'])->name('webhooks.meta.verify');
Route::post('/webhooks/meta', [MetaWebhookController::class, 'handle'])->name('webhooks.meta.handle');

// Stripe calls this directly — no session/auth/CSRF; Cashier's own
// VerifyWebhookSignature middleware (applied automatically by
// StripeWebhookController's parent constructor when cashier.webhook.secret
// is configured) authenticates the request instead.
Route::post('/stripe/webhook', [StripeWebhookController::class, 'handleWebhook'])->name('cashier.webhook');

require __DIR__.'/auth.php';
