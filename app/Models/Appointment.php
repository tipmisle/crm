<?php

namespace App\Models;

use App\Enums\AppointmentStatus;
use App\Enums\PaymentStatus;
use App\Models\Concerns\BelongsToWorkspace;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphMany;

class Appointment extends Model
{
    use BelongsToWorkspace, HasFactory;

    protected $fillable = [
        'workspace_id',
        'customer_id',
        'conversation_id',
        'channel_id',
        'assigned_user_id',
        'service_id',
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
            'status' => AppointmentStatus::class,
            'payment_status' => PaymentStatus::class,
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

    public function service(): BelongsTo
    {
        return $this->belongsTo(Service::class);
    }

    public function followUps(): MorphMany
    {
        return $this->morphMany(FollowUp::class, 'followable');
    }

    public function remainingBalance(): float
    {
        return max(0, (float) $this->price - (float) $this->amount_paid);
    }

    public function isUpcoming(): bool
    {
        return ! in_array($this->status, [AppointmentStatus::Completed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)
            && $this->appointment_date->isFuture();
    }
}
