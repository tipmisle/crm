<?php

namespace App\Models;

use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;

class Appointment extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'customer_id',
        'conversation_id',
        'channel_id',
        'assigned_user_id',
        'service_name',
        'description',
        'appointment_date',
        'start_time',
        'duration_minutes',
        'price',
        'deposit_amount',
        'amount_paid',
        'payment_status',
        'status',
        'internal_notes',
        'customer_notes',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            // status/payment_status are plain strings referencing
            // workspace-editable AppointmentStatus/PaymentStatus rows'
            // `key` — see appointmentStatusRecord()/paymentStatusRecord()
            // below. No enum cast: the set of valid values is per-workspace,
            // not fixed.
            'appointment_date' => 'date',
            'price' => 'decimal:2',
            'deposit_amount' => 'decimal:2',
            'amount_paid' => 'decimal:2',
            'tags' => 'array',
            // Application-encrypted — see docs/data-security.md.
            'description' => 'encrypted',
            'internal_notes' => 'encrypted',
            'customer_notes' => 'encrypted',
        ];
    }

    protected static function booted(): void
    {
        static::created(function (Appointment $appointment) {
            if (! $appointment->appointment_number) {
                $appointment->forceFill([
                    'appointment_number' => 'APT-'.str_pad((string) $appointment->id, 4, '0', STR_PAD_LEFT),
                ])->saveQuietly();
            }
        });
    }

    // Same date-only JSON quirk as Order::due_date — see that model for why.
    protected function serializeDate(\DateTimeInterface $date): string
    {
        return $date->format('Y-m-d');
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(Customer::class);
    }

    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    public function channel(): BelongsTo
    {
        return $this->belongsTo(Channel::class);
    }

    public function assignedUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_user_id');
    }

    public function items(): HasMany
    {
        return $this->hasMany(AppointmentItem::class)->orderBy('id');
    }

    /** See Order::orderStatus() for why this is scoped by workspace_id as well as key, and why it must stay lazy-only (never eager-loaded via with()/load()). */
    public function paymentStatusRecord(): BelongsTo
    {
        return $this->belongsTo(PaymentStatus::class, 'payment_status', 'key')
            ->withoutGlobalScopes()
            ->where('payment_statuses.workspace_id', $this->workspace_id);
    }

    /** See Order::orderStatus() for why this is scoped by workspace_id as well as key, and why it must stay lazy-only. */
    public function appointmentStatusRecord(): BelongsTo
    {
        return $this->belongsTo(AppointmentStatus::class, 'status', 'key')
            ->withoutGlobalScopes()
            ->where('appointment_statuses.workspace_id', $this->workspace_id);
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'followable');
    }

    public function salesDocuments(): HasMany
    {
        return $this->hasMany(SalesDocument::class);
    }

    public function remainingBalance(): float
    {
        return max(0, (float) $this->price - (float) $this->amount_paid);
    }

    public function isUpcoming(): bool
    {
        // appointment_date is a calendar date, not an instant — "future"
        // means strictly after today's date in the workspace's own
        // timezone (matches the original isFuture() semantics: a same-day
        // appointment is not "upcoming" once today has begun).
        $timezone = $this->workspace?->timezone ?? config('app.timezone');

        return ! in_array($this->status, AppointmentStatus::openExclusionKeys(), true)
            && $this->appointment_date->gt(Carbon::today($timezone));
    }
}
