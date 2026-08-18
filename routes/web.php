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
use App\Http\Controllers\Settings\AppointmentStatusController;
use App\Http\Controllers\Settings\BillingController;
use App\Http\Controllers\Settings\BugReportController;
use App\Http\Controllers\Settings\FeatureRequestController;
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
Route::get('/politika-zasebnosti', [LegalController::class, 'privacy'])->name('legal.privacy');
Route::get('/politika-piskotkov', [LegalController::class, 'cookies'])->name('legal.cookies');
Route::get('/dogovor-o-obdelavi-osebnih-podatkov', [LegalController::class, 'dpa'])->name('legal.dpa');
Route::get('/podatki-o-ponudniku', [LegalController::class, 'provider'])->name('legal.provider');
Route::get('/podobdelovalci', [LegalController::class, 'subprocessors'])->name('legal.subprocessors');

// Legacy public paths — permanent redirects so bookmarks/old links/search
// engines converge on the canonical Slovenian URLs above. GET-only; the
// legal pages never accept mutations so no method-changing risk.
Route::redirect('/zasebnost', '/politika-zasebnosti', 301);
Route::redirect('/piskotki', '/politika-piskotkov', 301);
Route::redirect('/obdelava-osebnih-podatkov', '/dogovor-o-obdelavi-osebnih-podatkov', 301);

Route::middleware('auth')->group(function () {
    // Billing management/activation and account/GDPR routes stay reachable
    // regardless of subscription state — never trap an unpaid/canceled
    // owner in an app they can't exit, pay, or delete. See docs/billing.md.
    Route::get('/billing/activate', [ActivationController::class, 'edit'])->name('billing.activate');
    Route::post('/billing/activate/checkout', [ActivationController::class, 'checkout'])->name('billing.activate.checkout');
    Route::get('/billing/activate/success', [ActivationController::class, 'success'])->name('billing.activate.success');

    Route::get('/nastavitve', [SettingsController::class, 'edit'])->name('settings.edit');
    Route::patch('/nastavitve/business', [SettingsController::class, 'update'])->name('settings.update');
    Route::patch('/nastavitve/capabilities', [SettingsController::class, 'updateCapabilities'])->name('settings.capabilities.update');

    Route::get('/nastavitve/podpora', [SupportAccessController::class, 'edit'])->name('settings.support.edit');
    // Granting support access to a live workspace's content is sensitive
    // enough to gate behind a recently-confirmed password — but leaving/
    // revoking it must never be gated, so it's always immediately reachable.
    Route::middleware('password.confirm')->post('/nastavitve/podpora', [SupportAccessController::class, 'store'])->name('settings.support.store');
    Route::delete('/nastavitve/podpora', [SupportAccessController::class, 'destroy'])->name('settings.support.destroy');
    Route::post('/nastavitve/podpora/bug-reports', [BugReportController::class, 'store'])->name('settings.support.bug-reports.store');
    Route::post('/nastavitve/podpora/feature-requests', [FeatureRequestController::class, 'store'])->name('settings.support.feature-requests.store');

    Route::get('/nastavitve/narocnina', [BillingController::class, 'edit'])->name('settings.billing.edit');
    // The Stripe Customer Portal lets the owner change payment method,
    // cancel, or view invoices — billing/cancellation-affecting, so it's
    // gated the same as other high-sensitivity actions above.
    Route::middleware('password.confirm')->get('/nastavitve/narocnina/portal', [BillingController::class, 'portal'])->name('settings.billing.portal');

    Route::get('/nastavitve/statusi', [SettingsStatusesController::class, 'edit'])->name('settings.statuses.edit');
    Route::post('/nastavitve/statusi/order', [OrderStatusController::class, 'store'])->name('settings.statuses.order.store');
    Route::patch('/nastavitve/statusi/order/{orderStatus}', [OrderStatusController::class, 'update'])->name('settings.statuses.order.update');
    Route::delete('/nastavitve/statusi/order/{orderStatus}', [OrderStatusController::class, 'destroy'])->name('settings.statuses.order.destroy');
    Route::post('/nastavitve/statusi/order/reorder', [OrderStatusController::class, 'reorder'])->name('settings.statuses.order.reorder');

    Route::post('/nastavitve/statusi/payment', [PaymentStatusController::class, 'store'])->name('settings.statuses.payment.store');
    Route::patch('/nastavitve/statusi/payment/{paymentStatus}', [PaymentStatusController::class, 'update'])->name('settings.statuses.payment.update');
    Route::delete('/nastavitve/statusi/payment/{paymentStatus}', [PaymentStatusController::class, 'destroy'])->name('settings.statuses.payment.destroy');
    Route::post('/nastavitve/statusi/payment/reorder', [PaymentStatusController::class, 'reorder'])->name('settings.statuses.payment.reorder');

    Route::post('/nastavitve/statusi/appointment', [AppointmentStatusController::class, 'store'])->name('settings.statuses.appointment.store');
    Route::patch('/nastavitve/statusi/appointment/{appointmentStatus}', [AppointmentStatusController::class, 'update'])->name('settings.statuses.appointment.update');
    Route::delete('/nastavitve/statusi/appointment/{appointmentStatus}', [AppointmentStatusController::class, 'destroy'])->name('settings.statuses.appointment.destroy');
    Route::post('/nastavitve/statusi/appointment/reorder', [AppointmentStatusController::class, 'reorder'])->name('settings.statuses.appointment.reorder');

    Route::get('/nastavitve/izdajanje-racunov', [InvoiceSettingsController::class, 'edit'])->name('settings.invoicing.edit');
    Route::patch('/nastavitve/izdajanje-racunov', [InvoiceSettingsController::class, 'update'])->name('settings.invoicing.update');
    Route::post('/nastavitve/izdajanje-racunov/logo', [InvoiceSettingsController::class, 'updateLogo'])->name('settings.invoicing.logo.update');
    Route::delete('/nastavitve/izdajanje-racunov/logo', [InvoiceSettingsController::class, 'destroyLogo'])->name('settings.invoicing.logo.destroy');
    Route::get('/nastavitve/izdajanje-racunov/preview', [InvoiceSettingsController::class, 'preview'])->name('settings.invoicing.preview');

    Route::get('/nastavitve/zasebnost', [WorkspacePrivacyController::class, 'edit'])->name('settings.privacy.edit');
    Route::get('/nastavitve/zasebnost/export/{export}/download', [WorkspaceExportController::class, 'download'])->name('settings.privacy.export.download');

    // Destructive/data-generating mutations require a recently-confirmed
    // password on top of normal auth — same pattern as routes/admin.php.
    Route::middleware('password.confirm')->group(function () {
        Route::post('/nastavitve/zasebnost/delete', [WorkspacePrivacyController::class, 'requestDeletion'])->name('settings.privacy.delete');
        Route::delete('/nastavitve/zasebnost/delete', [WorkspacePrivacyController::class, 'cancelDeletion'])->name('settings.privacy.cancel');
        Route::post('/nastavitve/zasebnost/export', [WorkspaceExportController::class, 'store'])->name('settings.privacy.export.store');
    });

    Route::get('/profil', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profil', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::post('/push-subscriptions', [PushSubscriptionController::class, 'store'])->name('push-subscriptions.store');
    Route::delete('/push-subscriptions', [PushSubscriptionController::class, 'destroy'])->name('push-subscriptions.destroy');

    // Normal product surface — requires an active subscription for real
    // (non-demo) workspaces. Demo bypasses entirely. See docs/billing.md.
    // onboarding.gate additionally bounces an incomplete (non-demo)
    // workspace to /onboarding first; the onboarding routes themselves and
    // the Meta OAuth round-trip are exempt from that bounce (see
    // EnsureOnboardingComplete).
    Route::middleware(['subscription.active', 'onboarding.gate'])->group(function () {
        Route::get('/zacetna-nastavitev', [OnboardingController::class, 'show'])->name('onboarding.show');
        Route::post('/zacetna-nastavitev/complete', [OnboardingController::class, 'complete'])->name('onboarding.complete');

        Route::get('/danes', TodayController::class)->name('dashboard');

        Route::get('/iskanje', SearchController::class)->name('search');

        Route::get('/sporocila', [ConversationController::class, 'index'])->name('inbox.index');
        Route::get('/sporocila/{conversation}', [ConversationController::class, 'show'])->name('inbox.show');
        Route::post('/sporocila/{conversation}/messages', [ConversationController::class, 'sendMessage'])->name('inbox.messages.store');
        Route::patch('/sporocila/{conversation}', [ConversationController::class, 'update'])->name('inbox.update');
        Route::post('/sporocila/{conversation}/create-customer', [ConversationController::class, 'createCustomer'])->name('inbox.create-customer');
        Route::post('/sporocila/{conversation}/notes', [ConversationController::class, 'addNote'])->name('inbox.notes.store');
        Route::get('/sporocila/attachments/{message}/{index}', [AttachmentController::class, 'show'])->name('inbox.attachments.show');

        // No customers.destroy: deleting a Customer would cascade-delete
        // Orders/Appointments (cascadeOnDelete FKs). GDPR removal is
        // customers.privacy.erase (anonymization), which preserves
        // operational/financial history. See CustomerController.
        Route::resource('stranke', CustomerController::class)
            ->except(['edit', 'destroy'])
            ->parameters(['stranke' => 'customer'])
            ->names('customers');
        Route::middleware('orders.enabled')->group(function () {
            Route::resource('narocila', OrderController::class)
                ->except(['edit'])
                ->parameters(['narocila' => 'order'])
                ->names('orders');
            Route::get('/narocila-export', [OrderController::class, 'exportCsv'])->name('orders.export');
            Route::post('/narocila/{order}/notes', [OrderNoteController::class, 'store'])->name('orders.notes.store');
            Route::post('/narocila/{order}/notify', [OrderNotificationController::class, 'store'])->name('orders.notify.store');

            Route::get('/narocila/{order}/documents/create', [SalesDocumentController::class, 'create'])->name('orders.documents.create');
            Route::post('/narocila/{order}/documents', [SalesDocumentController::class, 'store'])->name('orders.documents.store');
            Route::post('/narocila/{order}/documents/external', [ExternalDocumentController::class, 'store'])->name('orders.documents.external.store');
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
            Route::resource('termini', AppointmentController::class)
                ->except(['edit'])
                ->parameters(['termini' => 'appointment'])
                ->names('appointments');
            Route::get('/termini-export', [AppointmentController::class, 'exportCsv'])->name('appointments.export');
            Route::post('/termini/{appointment}/notify', [AppointmentNotificationController::class, 'store'])->name('appointments.notify.store');
            Route::get('/termini/{appointment}/documents/create', [SalesDocumentController::class, 'createForAppointment'])->name('appointments.documents.create');
            Route::post('/termini/{appointment}/documents', [SalesDocumentController::class, 'storeForAppointment'])->name('appointments.documents.store');
            Route::post('/termini/{appointment}/documents/external', [ExternalDocumentController::class, 'storeForAppointment'])->name('appointments.documents.external.store');
            Route::resource('services', ServiceController::class)->only(['store', 'update', 'destroy']);
        });

        Route::post('/follow-ups', [FollowUpController::class, 'store'])->name('follow-ups.store');
        Route::patch('/follow-ups/{followUp}/complete', [FollowUpController::class, 'complete'])->name('follow-ups.complete');
        Route::delete('/follow-ups/{followUp}', [FollowUpController::class, 'destroy'])->name('follow-ups.destroy');

        // Exporting or erasing a customer's data exposes/destroys personal
        // data — same password.confirm gate as workspace export/deletion
        // (routes/web.php above) and admin support access (routes/admin.php).
        Route::middleware('password.confirm')->group(function () {
            Route::post('/stranke/{customer}/privacy/export', [CustomerPrivacyController::class, 'exportData'])->name('customers.privacy.export');
            Route::post('/stranke/{customer}/privacy/erase', [CustomerPrivacyController::class, 'eraseData'])->name('customers.privacy.erase');
        });

        Route::get('/settings/integrations/meta/connect', [MetaIntegrationController::class, 'connect'])->name('integrations.meta.connect');
        Route::get('/settings/integrations/meta/callback', [MetaIntegrationController::class, 'callback'])->name('integrations.meta.callback');
        Route::post('/settings/integrations/meta/channels', [MetaIntegrationController::class, 'store'])->name('integrations.meta.store');
        Route::delete('/settings/integrations/meta/pending', [MetaIntegrationController::class, 'cancel'])->name('integrations.meta.cancel');
        Route::delete('/settings/integrations/meta/channels/{channel}', [MetaIntegrationController::class, 'destroy'])->name('integrations.meta.disconnect');
    });
});

// Legacy GET-only redirects for bookmarked/old human-facing app URLs, so
// old links/bookmarks converge on the new Slovenian paths instead of
// 404ing. GET-only: a redirected POST/PATCH/DELETE could have its method
// downgraded by the client, so those simply 404 on the old path — every
// internal caller already moved to route()-generated new paths.
Route::get('/today', fn () => redirect('/danes', 301));
Route::get('/search', fn () => redirect('/iskanje', 301));
Route::get('/inbox', fn () => redirect('/sporocila', 301));
Route::get('/inbox/{conversation}', fn (string $conversation) => redirect('/sporocila/'.$conversation, 301));
Route::get('/customers', fn () => redirect('/stranke', 301));
Route::get('/customers/{customer}', fn (string $customer) => redirect('/stranke/'.$customer, 301));
Route::get('/orders', fn () => redirect('/narocila', 301));
Route::get('/orders/{order}', fn (string $order) => redirect('/narocila/'.$order, 301));
Route::get('/orders-export', fn () => redirect('/narocila-export', 301));
Route::get('/appointments', fn () => redirect('/termini', 301));
Route::get('/appointments/{appointment}', fn (string $appointment) => redirect('/termini/'.$appointment, 301));
Route::get('/appointments-export', fn () => redirect('/termini-export', 301));
Route::get('/settings', fn () => redirect('/nastavitve', 301));
Route::get('/settings/support', fn () => redirect('/nastavitve/podpora', 301));
Route::get('/settings/billing', fn () => redirect('/nastavitve/narocnina', 301));
Route::get('/settings/statuses', fn () => redirect('/nastavitve/statusi', 301));
Route::get('/settings/invoicing', fn () => redirect('/nastavitve/izdajanje-racunov', 301));
Route::get('/settings/privacy', fn () => redirect('/nastavitve/zasebnost', 301));
Route::get('/profile', fn () => redirect('/profil', 301));
Route::get('/onboarding', fn () => redirect('/zacetna-nastavitev', 301));

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
