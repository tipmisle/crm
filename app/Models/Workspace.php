<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Laravel\Cashier\Billable;

/**
 * Billing belongs to the Workspace (the paying business), never to an
 * individual User — see docs/billing.md. Cashier's Billable model is
 * configured to Workspace via Cashier::useCustomerModel() in
 * AppServiceProvider::boot(). Demo workspaces (is_demo=true) must never
 * get a stripe_id / subscription row — enforced at the call sites that
 * create Stripe customers/subscriptions (ActivationController), not here.
 */
class Workspace extends Model
{
    use Billable, HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'email',
        'logo_path',
        'timezone',
        'currency',
        'orders_enabled',
        'appointments_enabled',
        'accepts_deposit',
        'is_demo',
        'demo_expires_at',
        'demo_variant',
        'deletion_requested_at',
        'scheduled_deletion_at',
        'onboarding_completed_at',
    ];

    protected function casts(): array
    {
        return [
            'orders_enabled' => 'boolean',
            'appointments_enabled' => 'boolean',
            'accepts_deposit' => 'boolean',
            'is_demo' => 'boolean',
            'demo_expires_at' => 'datetime',
            'deletion_requested_at' => 'datetime',
            'scheduled_deletion_at' => 'datetime',
            'trial_ends_at' => 'datetime',
            'onboarding_completed_at' => 'datetime',
        ];
    }

    /**
     * Distinct from demo expiry (is_demo/demo_expires_at) — this tracks a
     * real workspace's owner-initiated deletion request. See
     * docs/data-lifecycle.md.
     */
    public function isPendingDeletion(): bool
    {
        return $this->deletion_requested_at !== null;
    }

    /**
     * Demo workspaces skip the first-run /onboarding flow entirely — they
     * arrive pre-seeded via a demo seeder, not through Stripe activation.
     */
    public function needsOnboarding(): bool
    {
        return ! $this->is_demo && $this->onboarding_completed_at === null;
    }

    public function members(): HasMany
    {
        return $this->hasMany(WorkspaceMember::class);
    }

    public function users(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'workspace_members')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function channels(): HasMany
    {
        return $this->hasMany(Channel::class);
    }

    public function customers(): HasMany
    {
        return $this->hasMany(Customer::class);
    }

    public function conversations(): HasMany
    {
        return $this->hasMany(Conversation::class);
    }

    public function orders(): HasMany
    {
        return $this->hasMany(Order::class);
    }

    public function salesDocuments(): HasMany
    {
        return $this->hasMany(SalesDocument::class);
    }

    public function invoiceSettings(): HasOne
    {
        return $this->hasOne(InvoiceSettings::class);
    }

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function services(): HasMany
    {
        return $this->hasMany(Service::class);
    }

    public function products(): HasMany
    {
        return $this->hasMany(Product::class);
    }

    public function followUps(): HasMany
    {
        return $this->hasMany(FollowUp::class);
    }

    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    public function integrations(): HasMany
    {
        return $this->hasMany(Integration::class);
    }

    public function supportAccessGrants(): HasMany
    {
        return $this->hasMany(SupportAccessGrant::class);
    }

    public function currentSupportAccessGrant(): ?SupportAccessGrant
    {
        return $this->supportAccessGrants()->active()->orderByDesc('expires_at')->first();
    }

    public function workspaceExports(): HasMany
    {
        return $this->hasMany(WorkspaceExport::class);
    }

    /**
     * Safe, identifiers-only Stripe Customer metadata — never CRM content
     * (messages, customer notes, Meta tokens). See docs/billing.md.
     */
    public function stripeMetadata(): array
    {
        return [
            'workspace_id' => (string) $this->id,
            'app' => 'belezka',
            'environment' => config('app.env'),
        ];
    }
}
