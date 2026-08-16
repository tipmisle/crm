<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Customer extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'full_name',
        'email',
        'phone',
        'address_line',
        'postal_code',
        'city',
        'country',
        'tax_number',
        'notes',
        'tags',
        'primary_channel_id',
        'first_contacted_at',
        'last_interaction_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            // Application-encrypted — see docs/data-security.md. Free-text
            // notes may contain health or other sensitive disclosures.
            'notes' => 'encrypted',
            'first_contacted_at' => 'datetime',
            'last_interaction_at' => 'datetime',
        ];
    }

    public function identities(): HasMany
    {
        return $this->hasMany(CustomerIdentity::class);
    }

    public function primaryChannel(): BelongsTo
    {
        return $this->belongsTo(Channel::class, 'primary_channel_id');
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

    public function appointments(): HasMany
    {
        return $this->hasMany(Appointment::class);
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'followable');
    }

    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    public function lifetimeSpend(): float
    {
        return (float) $this->orders()->sum('amount_paid');
    }

    public function openOrdersCount(): int
    {
        return $this->orders()->whereNotIn('status', OrderStatus::openExclusionKeys())->count();
    }

    public function completedOrdersCount(): int
    {
        return $this->orders()->whereIn('status', OrderStatus::completedKeys())->count();
    }

    public function appointmentsLifetimeSpend(): float
    {
        return (float) $this->appointments()->sum('amount_paid');
    }

    public function previousAppointmentsCount(): int
    {
        return $this->appointments()->where('status', AppointmentStatus::Completed->value)->count();
    }

    public function noShowAppointmentsCount(): int
    {
        return $this->appointments()->where('status', AppointmentStatus::NoShow->value)->count();
    }

    public function upcomingAppointment(): ?Appointment
    {
        return $this->appointments()
            ->whereIn('status', [AppointmentStatus::Requested->value, AppointmentStatus::Confirmed->value])
            ->where('appointment_date', '>=', now()->toDateString())
            ->orderBy('appointment_date')
            ->orderBy('start_time')
            ->first();
    }

    public function lastAppointment(): ?Appointment
    {
        return $this->appointments()
            ->where('status', AppointmentStatus::Completed->value)
            ->orderByDesc('appointment_date')
            ->first();
    }

    public function initials(): string
    {
        $parts = preg_split('/\s+/', trim($this->full_name));
        $letters = array_map(fn ($p) => mb_substr($p, 0, 1), array_slice($parts, 0, 2));

        return mb_strtoupper(implode('', $letters));
    }
}
