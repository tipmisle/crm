<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageSenderType;
use App\Enums\MessageType;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\FollowUp;
use App\Models\Message;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Carbon\Carbon;
use Database\Seeders\Concerns\SpreadsTimestamps;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class StudioNolaSeeder extends Seeder
{
    use SpreadsTimestamps;

    /** @var array<string, Channel> */
    private array $channels = [];

    /** @var array<string, Service> */
    private array $services = [];

    public function __construct(private ?Workspace $workspace = null, private ?User $user = null) {}

    /** @var array<int, array{first: string, last: string}> */
    private array $names = [
        ['first' => 'Manca', 'last' => 'Kolar'],
        ['first' => 'Zala', 'last' => 'Ferlan'],
        ['first' => 'Ajda', 'last' => 'Rakovec'],
        ['first' => 'Iza', 'last' => 'Trontelj'],
        ['first' => 'Pia', 'last' => 'Šinkovec'],
        ['first' => 'Neja', 'last' => 'Blatnik'],
        ['first' => 'Urška', 'last' => 'Podgornik'],
        ['first' => 'Vesna', 'last' => 'Rupar'],
        ['first' => 'Tina', 'last' => 'Lokar'],
        ['first' => 'Mojca', 'last' => 'Debeljak'],
        ['first' => 'Lucija', 'last' => 'Peternel'],
        ['first' => 'Barbara', 'last' => 'Kastelic'],
        ['first' => 'Ines', 'last' => 'Brglez'],
        ['first' => 'Doroteja', 'last' => 'Zajc'],
        ['first' => 'Karin', 'last' => 'Meglič'],
        ['first' => 'Saša', 'last' => 'Habjan'],
    ];

    public function run(): void
    {
        $this->createWorkspaceAndUser();
        $this->createChannels();
        $this->createServices();
        $customers = $this->createCustomers();
        $conversations = $this->createConversationsAndMessages($customers);
        $appointments = $this->createAppointments($customers, $conversations);
        $this->createFollowUps($customers, $appointments);
        $this->createActivityLogs($customers, $appointments);
    }

    private function createWorkspaceAndUser(): void
    {
        // When a Workspace (and User) is injected via the constructor — the
        // ephemeral-demo path — data generation reuses that identity instead
        // of creating the fixed dev/demo-seed one below.
        if ($this->workspace) {
            return;
        }

        $this->workspace = Workspace::create([
            'name' => 'Studio Nola',
            'slug' => 'studio-nola',
            'email' => 'hello@studionola.com',
            'timezone' => 'Europe/Ljubljana',
            'currency' => 'EUR',
            'orders_enabled' => false,
            'appointments_enabled' => true,
        ]);

        $this->user = User::updateOrCreate(
            ['email' => 'nola@studionola.com'],
            [
                'name' => 'Nola Vidmar',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'current_workspace_id' => $this->workspace->id,
            ]
        );

        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $this->user->id,
            'role' => 'owner',
        ]);
    }

    private function createChannels(): void
    {
        foreach (ChannelType::cases() as $type) {
            $this->channels[$type->value] = Channel::create([
                'workspace_id' => $this->workspace->id,
                'type' => $type,
                'display_name' => match ($type) {
                    ChannelType::Instagram => '@studio.nola',
                    ChannelType::FacebookMessenger => 'Studio Nola',
                    ChannelType::TikTok => '@studio.nola',
                    ChannelType::WhatsApp => 'Studio Nola WhatsApp',
                    ChannelType::Email => 'hello@studionola.com',
                    ChannelType::Website => 'studionola.com',
                },
                'status' => 'not_connected',
            ]);
        }
    }

    private function createServices(): void
    {
        $definitions = [
            ['name' => 'Gel manikura', 'description' => 'Klasična gel manikura z barvo po izbiri.', 'duration' => 60, 'price' => 35, 'deposit' => null],
            ['name' => 'BIAB nadgradnja', 'description' => 'Podaljšanje in ojačitev naravnega nohta z BIAB gelom.', 'duration' => 75, 'price' => 42, 'deposit' => 10],
            ['name' => 'Gel podaljški', 'description' => 'Podaljški z gel tehniko na šablonah.', 'duration' => 120, 'price' => 55, 'deposit' => 15],
            ['name' => 'Nail art dodatek', 'description' => 'Dodatna dekoracija — kamenčki, francoska linija, ombre.', 'duration' => 15, 'price' => 8, 'deposit' => null],
        ];

        foreach ($definitions as $def) {
            $this->services[$def['name']] = Service::create([
                'workspace_id' => $this->workspace->id,
                'name' => $def['name'],
                'description' => $def['description'],
                'default_duration_minutes' => $def['duration'],
                'default_price' => $def['price'],
                'default_deposit_amount' => $def['deposit'],
                'active' => true,
            ]);
        }
    }

    /** @return \Illuminate\Support\Collection<int, Customer> */
    private function createCustomers()
    {
        $customers = collect();

        foreach ($this->names as $i => $name) {
            $fullName = "{$name['first']} {$name['last']}";
            $username = Str::slug($fullName, '').['_nails', '', '.nails', ''][($i % 4)];
            $channelType = $this->weightedChannel();
            $firstContact = Carbon::now()->subDays(rand(1, 120));

            $customer = Customer::create([
                'workspace_id' => $this->workspace->id,
                'full_name' => $fullName,
                'email' => Str::lower("{$name['first']}.{$name['last']}@example.com"),
                'phone' => '+386 '.rand(30, 70).' '.rand(100, 999).' '.rand(100, 999),
                'notes' => $this->maybeNote(),
                'tags' => $this->maybeTags(),
                'first_contacted_at' => $firstContact,
                'last_interaction_at' => $firstContact->copy()->addDays(rand(0, 250)),
            ]);

            $this->backdate($customer, $firstContact);

            CustomerIdentity::create([
                'customer_id' => $customer->id,
                'workspace_id' => $this->workspace->id,
                'channel_type' => $channelType,
                'external_id' => (string) rand(100000000, 999999999),
                'username' => $channelType === ChannelType::Email ? $customer->email : '@'.$username,
                'display_name' => $fullName,
            ]);

            $customer->update(['primary_channel_id' => $this->channels[$channelType->value]->id]);

            $customers->push($customer);
        }

        return $customers;
    }

    private function weightedChannel(): ChannelType
    {
        $roll = rand(1, 100);

        return match (true) {
            $roll <= 70 => ChannelType::Instagram,
            $roll <= 85 => ChannelType::FacebookMessenger,
            $roll <= 93 => ChannelType::WhatsApp,
            default => ChannelType::Email,
        };
    }

    private function maybeNote(): ?string
    {
        $notes = [
            'Vedno želi isto obliko — mandljasto.',
            'Alergična na nekatere lake — preveri pred nanosom.',
            'Rada ima nežne pastelne odtenke.',
            'Priporočila jo je prejšnja stranka.',
            'Vedno rezervira termine ob petkih popoldan.',
            null,
            null,
        ];

        return $notes[array_rand($notes)];
    }

    private function maybeTags(): ?array
    {
        $pool = ['stalna stranka', 'VIP', 'poroka', 'priporočilo', 'nova stranka'];

        if (rand(0, 2) === 0) {
            return null;
        }

        return array_values(array_unique([$pool[array_rand($pool)]]));
    }

    /** @return \Illuminate\Support\Collection<int, Conversation> */
    private function createConversationsAndMessages($customers)
    {
        $conversations = collect();
        $total = 24;
        $unlinkedCount = 5;
        $startDates = $this->spreadDates($total);

        for ($i = 0; $i < $total; $i++) {
            $isUnlinked = $i < $unlinkedCount;
            $customer = $isUnlinked ? null : $customers[($i - $unlinkedCount) % $customers->count()];

            $channelType = $customer
                ? $customer->identities->first()?->channel_type ?? $this->weightedChannel()
                : $this->weightedChannel();

            $leadName = $isUnlinked ? $this->names[array_rand($this->names)] : null;
            $displayName = $customer?->full_name ?? "{$leadName['first']} {$leadName['last']}";
            $username = '@'.Str::slug($displayName, '');

            $status = $this->weightedConversationStatus();
            $startedAt = array_pop($startDates);

            $conversation = Conversation::create([
                'workspace_id' => $this->workspace->id,
                'channel_id' => $this->channels[$channelType->value]->id,
                'customer_id' => $customer?->id,
                'customer_display_name' => $displayName,
                'customer_username' => $username,
                'status' => $status,
                'unread_count' => 0,
            ]);

            $this->backdate($conversation, $startedAt);

            $this->seedMessages($conversation, $displayName, $status, $startedAt);
            $conversations->push($conversation);
        }

        return $conversations;
    }

    private function weightedConversationStatus(): ConversationStatus
    {
        $roll = rand(1, 100);

        return match (true) {
            $roll <= 30 => ConversationStatus::NewEnquiry,
            $roll <= 50 => ConversationStatus::NeedsQuote,
            $roll <= 65 => ConversationStatus::WaitingForCustomer,
            $roll <= 92 => ConversationStatus::OrderConfirmed,
            default => ConversationStatus::Closed,
        };
    }

    private function seedMessages(Conversation $conversation, string $displayName, ConversationStatus $status, Carbon $startedAt): void
    {
        $firstName = Str::before($displayName, ' ');
        $service = array_rand($this->services);
        $day = ['ponedeljek', 'torek', 'sredo', 'četrtek', 'petek', 'soboto'][array_rand(range(0, 5))];
        $time = ['10:00', '11:30', '14:00', '15:30', '17:00'][array_rand(range(0, 4))];

        $enquiries = [
            "Živjo! Imate prost termin za {$service} v {$day}?",
            "Pozdravljeni, bi rada rezervirala {$service}, kdaj ste prosti ta teden?",
            "Živjo, videla sem vaše delo na Instagramu — koliko stane {$service}?",
            "Pozdravljeni! Ali imate prost termin ob {$time} v {$day}?",
            'Živjo, rada bi zamenjala barvo — je to mogoče ta teden?',
            "Pozdravljeni, ali sprejemate nove stranke za {$service}?",
        ];

        $businessReplies = [
            "Živjo {$firstName}! Hvala za sporočilo 💅 Da, imam prost termin v {$day} ob {$time}, vam ustreza?",
            "Pozdravljeni! Za {$service} lahko rezerviramo termin — imam prosto v {$day}.",
            "Seveda! {$service} traja približno uro, cena je odvisna od dizajna.",
            "Živjo {$firstName}! Vsekakor sprejemam nove stranke 😊 Kdaj bi vam ustrezalo?",
            'To lahko naredimo! Pošljem vam nekaj predlogov odtenkov.',
        ];

        $confirmations = [
            'Odlično, to mi ustreza!',
            'Super, se vidimo takrat!',
            'Da, rezervirajte mi ta termin prosim.',
            'Zveni dobro, hvala!',
        ];

        $thread = [];
        $thread[] = ['sender' => MessageSenderType::Customer, 'body' => $enquiries[array_rand($enquiries)]];

        $messageCount = match ($status) {
            ConversationStatus::NewEnquiry => rand(1, 2),
            ConversationStatus::NeedsQuote => rand(2, 3),
            ConversationStatus::WaitingForCustomer => rand(3, 4),
            ConversationStatus::OrderConfirmed => rand(3, 5),
            ConversationStatus::Closed => rand(2, 4),
        };

        while (count($thread) < $messageCount) {
            $lastSender = end($thread)['sender'];

            if ($lastSender === MessageSenderType::Customer) {
                $thread[] = ['sender' => MessageSenderType::Business, 'body' => $businessReplies[array_rand($businessReplies)]];
            } elseif (in_array($status, [ConversationStatus::OrderConfirmed, ConversationStatus::Closed], true) && count($thread) >= $messageCount - 1) {
                $thread[] = ['sender' => MessageSenderType::Customer, 'body' => $confirmations[array_rand($confirmations)]];
            } else {
                $thread[] = ['sender' => MessageSenderType::Customer, 'body' => $enquiries[array_rand($enquiries)]];
            }
        }

        $timestamp = $startedAt->copy();
        $lastMessage = null;

        foreach ($thread as $entry) {
            $timestamp = $timestamp->copy()->addMinutes(rand(3, 90));

            $lastMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => $entry['sender'],
                'body' => $entry['body'],
                'message_type' => MessageType::Text,
                'sent_at' => $timestamp,
            ]);
        }

        $unread = $lastMessage && $lastMessage->sender_type === MessageSenderType::Customer ? rand(1, 2) : 0;

        $conversation->update([
            'last_message_preview' => Str::limit($lastMessage->body, 120),
            'last_message_at' => $lastMessage->sent_at,
            'unread_count' => $unread,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Appointment> */
    private function createAppointments($customers, $conversations)
    {
        $appointments = collect();

        $confirmedConversations = $conversations->filter(
            fn (Conversation $c) => $c->status === ConversationStatus::OrderConfirmed && $c->customer_id
        )->values();

        // [dayOffset from today, status, count]
        $plan = [
            [-40, AppointmentStatus::Completed, 6],
            [-20, AppointmentStatus::Completed, 6],
            [-7, AppointmentStatus::Completed, 5],
            [-3, AppointmentStatus::NoShow, 2],
            [-2, AppointmentStatus::Cancelled, 2],
            [0, AppointmentStatus::Confirmed, 3],
            [1, AppointmentStatus::Confirmed, 3],
            [2, AppointmentStatus::Confirmed, 2],
            [3, AppointmentStatus::Requested, 2],
            [5, AppointmentStatus::Confirmed, 2],
            [7, AppointmentStatus::Requested, 2],
            [14, AppointmentStatus::Requested, 1],
        ];

        $serviceNames = array_keys($this->services);
        $slots = ['09:00', '10:00', '11:00', '12:30', '14:00', '15:00', '16:00', '17:00'];
        $convIndex = 0;
        $totalAppointments = array_sum(array_column($plan, 2));
        $createdDates = $this->spreadDates($totalAppointments);

        foreach ($plan as [$dayOffset, $status, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $useConversation = $convIndex < $confirmedConversations->count() && rand(0, 2) > 0;
                $conversation = $useConversation ? $confirmedConversations[$convIndex++] : null;
                $customer = $conversation?->customer ?? $customers->random();

                $serviceName = $serviceNames[array_rand($serviceNames)];
                $service = $this->services[$serviceName];
                $date = Carbon::today()->addDays($dayOffset);
                $startTime = $slots[array_rand($slots)];

                // The booking (created_at) can't land after the appointment
                // itself for anything already in the past.
                $createdAt = array_pop($createdDates);
                if ($date->isPast() && $createdAt->gt($date)) {
                    $createdAt = $date->copy()->subHours(rand(1, 72));
                }

                $paymentStatus = match ($status) {
                    AppointmentStatus::Completed => PaymentStatus::Paid,
                    AppointmentStatus::Cancelled => rand(0, 1) ? PaymentStatus::Unpaid : PaymentStatus::DepositPaid,
                    AppointmentStatus::NoShow => PaymentStatus::DepositPaid,
                    AppointmentStatus::Confirmed => rand(0, 3) === 0 ? PaymentStatus::DepositDue : PaymentStatus::DepositPaid,
                    AppointmentStatus::Requested => PaymentStatus::Unpaid,
                };

                $price = (float) $service->default_price;
                $deposit = (float) ($service->default_deposit_amount ?? 0);
                $amountPaid = match ($paymentStatus) {
                    PaymentStatus::Paid => $price,
                    PaymentStatus::DepositPaid => $deposit,
                    default => 0,
                };

                $appointment = Appointment::create([
                    'workspace_id' => $this->workspace->id,
                    'customer_id' => $customer->id,
                    'conversation_id' => $conversation?->id,
                    'channel_id' => $conversation?->channel_id ?? $customer->primary_channel_id,
                    'service_id' => $service->id,
                    'service_name' => $serviceName,
                    'description' => null,
                    'appointment_date' => $date,
                    'start_time' => $startTime.':00',
                    'duration_minutes' => $service->default_duration_minutes,
                    'price' => $price,
                    'deposit_amount' => $deposit,
                    'amount_paid' => $amountPaid,
                    'payment_status' => $paymentStatus,
                    'status' => $status,
                    'internal_notes' => rand(0, 4) === 0 ? 'Stranka je prosila za natančno isti odtenek kot zadnjič.' : null,
                    'customer_notes' => rand(0, 4) === 0 ? 'Občutljiva koža okoli nohtov, previdno pri odstranjevanju.' : null,
                ]);

                $this->backdate($appointment, $createdAt);

                $appointments->push($appointment);
            }
        }

        return $appointments;
    }

    private function createFollowUps($customers, $appointments): void
    {
        $count = 12;

        for ($i = 0; $i < $count; $i++) {
            $dueOffset = match (true) {
                $i < 3 => rand(-3, -1),
                $i < 7 => 0,
                default => rand(1, 5),
            };

            $dueAt = Carbon::now()->addDays($dueOffset)->setTime(rand(9, 17), [0, 15, 30, 45][array_rand([0, 15, 30, 45])]);

            if (rand(0, 1) === 0 && $appointments->isNotEmpty()) {
                $appointment = $appointments->random();
                $note = "Prosi {$appointment->customer->full_name}, naj potrdi termin za {$appointment->service_name}";
                $followable = $appointment;
            } else {
                $customer = $customers->random();
                $note = "Pošlji opomnik za aro stranki {$customer->full_name}";
                $followable = $customer;
            }

            FollowUp::create([
                'workspace_id' => $this->workspace->id,
                'followable_type' => $followable::class,
                'followable_id' => $followable->id,
                'note' => $note,
                'due_at' => $dueAt,
                'completed_at' => $dueOffset < -1 && rand(0, 1) ? Carbon::now()->subDay() : null,
            ]);
        }
    }

    private function createActivityLogs($customers, $appointments): void
    {
        foreach ($appointments as $appointment) {
            $this->logAt([
                'workspace_id' => $this->workspace->id,
                'subject_type' => Appointment::class,
                'subject_id' => $appointment->id,
                'type' => 'appointment_created',
                'description' => "Termin {$appointment->appointment_number} ({$appointment->service_name}) ustvarjen za {$appointment->customer->full_name}",
            ], $appointment->created_at);

            if (in_array($appointment->status, [AppointmentStatus::Completed, AppointmentStatus::Confirmed, AppointmentStatus::Cancelled, AppointmentStatus::NoShow], true)) {
                $this->logAt([
                    'workspace_id' => $this->workspace->id,
                    'subject_type' => Appointment::class,
                    'subject_id' => $appointment->id,
                    'type' => 'status_changed',
                    'description' => "Termin {$appointment->appointment_number} označen kot {$appointment->status->label()}",
                ], $appointment->created_at->copy()->addHours(rand(1, 24)));
            }
        }

        foreach ($customers as $customer) {
            $this->logAt([
                'workspace_id' => $this->workspace->id,
                'subject_type' => Customer::class,
                'subject_id' => $customer->id,
                'type' => 'customer_created',
                'description' => "{$customer->full_name} dodana kot stranka",
            ], $customer->first_contacted_at);
        }
    }

    private function logAt(array $attributes, Carbon $timestamp): void
    {
        $log = new ActivityLog($attributes);
        $log->timestamps = false;
        $log->created_at = $timestamp;
        $log->updated_at = $timestamp;
        $log->save();
    }
}
