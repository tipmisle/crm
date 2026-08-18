<?php

namespace Database\Seeders;

use App\Enums\AppointmentStatus;
use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageSenderType;
use App\Enums\MessageType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Appointment;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\FollowUp;
use App\Models\Message;
use App\Models\Order;
use App\Models\Product;
use App\Models\Service;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use App\Services\WorkspaceStatusDefaults;
use Carbon\Carbon;
use Database\Seeders\Concerns\SpreadsTimestamps;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * "Storitve + naročila" demo scenario: a photo studio that both books
 * appointments (shoots) AND takes orders (albums/prints/gifts) from the
 * same customers — the same seeder shape as StudioNolaSeeder /
 * BloomAndCrumbSeeder, but deliberately mixing both capabilities so a demo
 * visitor can see why a business would want both at once.
 */
class FotoStudioLunaSeeder extends Seeder
{
    use SpreadsTimestamps;

    public function __construct(private ?Workspace $workspace = null, private ?User $user = null) {}

    /** @var array<string, Channel> */
    private array $channels = [];

    /** @var array<string, Service> */
    private array $services = [];

    /** @var array<string, Product> */
    private array $products = [];

    /** @var array<int, array{first: string, last: string}> */
    private array $names = [
        ['first' => 'Maruša', 'last' => 'Jerman'],
        ['first' => 'Anže', 'last' => 'Kolman'],
        ['first' => 'Vid', 'last' => 'Rman'],
        ['first' => 'Eva', 'last' => 'Strle'],
        ['first' => 'Miha', 'last' => 'Golčer'],
        ['first' => 'Lana', 'last' => 'Fajdiga'],
        ['first' => 'Tara', 'last' => 'Osredkar'],
        ['first' => 'Bor', 'last' => 'Kokalj'],
        ['first' => 'Zoja', 'last' => 'Prezelj'],
        ['first' => 'Nace', 'last' => 'Kump'],
        ['first' => 'Živa', 'last' => 'Bavdek'],
        ['first' => 'Rok', 'last' => 'Ahčin'],
    ];

    public function run(): void
    {
        $this->createWorkspaceAndUser();
        $this->createChannels();
        $this->createCatalog();
        $customers = $this->createCustomers();
        $conversations = $this->createConversationsAndMessages($customers);
        $appointments = $this->createAppointments($customers, $conversations);
        $orders = $this->createOrders($customers, $conversations);
        $this->createFollowUps($customers, $appointments, $orders);
        $this->createActivityLogs($customers, $appointments, $orders);
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
            'name' => 'Foto studio Luna',
            'slug' => 'foto-studio-luna',
            'email' => 'hello@fotostudioluna.com',
            'timezone' => 'Europe/Ljubljana',
            'currency' => 'EUR',
            'orders_enabled' => true,
            'appointments_enabled' => true,
        ]);

        $this->user = User::updateOrCreate(
            ['email' => 'luna@fotostudioluna.com'],
            [
                'name' => 'Luna Kastelic',
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

        WorkspaceStatusDefaults::seed($this->workspace);
    }

    private function createChannels(): void
    {
        $handle = '@'.Str::slug($this->workspace->name, '');

        foreach (ChannelType::cases() as $type) {
            $this->channels[$type->value] = Channel::create([
                'workspace_id' => $this->workspace->id,
                'type' => $type,
                'display_name' => match ($type) {
                    ChannelType::Instagram => $handle,
                    ChannelType::FacebookMessenger => $this->workspace->name,
                    ChannelType::TikTok => $handle,
                    ChannelType::WhatsApp => $this->workspace->name.' WhatsApp',
                    ChannelType::Email => $this->workspace->email,
                    ChannelType::Website => $this->workspace->slug.'.com',
                },
                'status' => 'not_connected',
            ]);
        }
    }

    private function createCatalog(): void
    {
        $services = [
            ['name' => 'Družinsko fotografiranje', 'description' => 'Fotografiranje na lokaciji po izbiri, do 5 oseb.', 'duration' => 60, 'price' => 120, 'deposit' => 30],
            ['name' => 'Poročno fotografiranje', 'description' => 'Cel dan spremljanja poroke, od priprav do zabave.', 'duration' => 480, 'price' => 890, 'deposit' => 200],
            ['name' => 'Portretno fotografiranje', 'description' => 'Studijsko portretno fotografiranje ene osebe.', 'duration' => 45, 'price' => 75, 'deposit' => null],
            ['name' => 'Posvet za poroko', 'description' => 'Uvodni posvet o poteku dneva, lokacijah in željah para.', 'duration' => 30, 'price' => 0, 'deposit' => null],
        ];

        foreach ($services as $def) {
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

        $products = [
            ['name' => 'Foto album', 'description' => 'Tiskan foto album, 20 strani, usnjena platnica.', 'price' => 45, 'deposit' => null],
            ['name' => 'Odtisi fotografij', 'description' => 'Komplet 20 tiskanih fotografij, 15x20 cm.', 'price' => 25, 'deposit' => null],
            ['name' => 'Darilni paket', 'description' => 'Album, odtisi in USB s celotno galerijo v darilni škatli.', 'price' => 140, 'deposit' => 40],
        ];

        foreach ($products as $def) {
            $this->products[$def['name']] = Product::create([
                'workspace_id' => $this->workspace->id,
                'name' => $def['name'],
                'description' => $def['description'],
                'default_price' => $def['price'],
                'default_deposit_amount' => $def['deposit'],
                'active' => true,
            ]);
        }
    }

    /** @return Collection<int, Customer> */
    private function createCustomers()
    {
        $customers = collect();

        // Two deterministic, named customers whose history must clearly
        // show both an appointment AND an order, per the demo brief.
        $customers->push($this->createNamedCustomer('Nina', 'Kovač', ChannelType::Instagram, daysAgo: 3));
        $customers->push($this->createNamedCustomer('Tina', 'Zupan', ChannelType::Instagram, daysAgo: 10));

        foreach ($this->names as $i => $name) {
            $channelType = $this->weightedChannel();
            $firstContact = Carbon::now()->subDays(rand(1, 100));

            $customer = Customer::create([
                'workspace_id' => $this->workspace->id,
                'full_name' => "{$name['first']} {$name['last']}",
                'email' => Str::lower("{$name['first']}.{$name['last']}@example.com"),
                'phone' => '+386 '.rand(30, 70).' '.rand(100, 999).' '.rand(100, 999),
                'notes' => $this->maybeNote(),
                'tags' => $this->maybeTags(),
                'first_contacted_at' => $firstContact,
                'last_interaction_at' => $firstContact->copy()->addDays(rand(0, 90)),
            ]);

            $this->backdate($customer, $firstContact);

            CustomerIdentity::create([
                'customer_id' => $customer->id,
                'workspace_id' => $this->workspace->id,
                'channel_type' => $channelType,
                'external_id' => (string) rand(100000000, 999999999),
                'username' => $channelType === ChannelType::Email ? $customer->email : '@'.Str::slug($customer->full_name, ''),
                'display_name' => $customer->full_name,
            ]);

            $customer->update(['primary_channel_id' => $this->channels[$channelType->value]->id]);

            $customers->push($customer);
        }

        return $customers;
    }

    private function createNamedCustomer(string $first, string $last, ChannelType $channelType, int $daysAgo): Customer
    {
        $fullName = "{$first} {$last}";
        $firstContact = Carbon::now()->subDays($daysAgo);

        $customer = Customer::create([
            'workspace_id' => $this->workspace->id,
            'full_name' => $fullName,
            'email' => Str::lower("{$first}.{$last}@example.com"),
            'phone' => '+386 '.rand(30, 70).' '.rand(100, 999).' '.rand(100, 999),
            'notes' => null,
            'tags' => null,
            'first_contacted_at' => $firstContact,
            'last_interaction_at' => Carbon::now(),
        ]);

        $this->backdate($customer, $firstContact);

        CustomerIdentity::create([
            'customer_id' => $customer->id,
            'workspace_id' => $this->workspace->id,
            'channel_type' => $channelType,
            'external_id' => (string) rand(100000000, 999999999),
            'username' => '@'.Str::slug($fullName, ''),
            'display_name' => $fullName,
        ]);

        $customer->update(['primary_channel_id' => $this->channels[$channelType->value]->id]);

        return $customer;
    }

    private function weightedChannel(): ChannelType
    {
        $roll = rand(1, 100);

        return match (true) {
            $roll <= 60 => ChannelType::Instagram,
            $roll <= 85 => ChannelType::FacebookMessenger,
            default => ChannelType::Email,
        };
    }

    private function maybeNote(): ?string
    {
        $notes = [
            'Rada ima naravno svetlobo, brez studijskih bliskavic.',
            'Priporočila jo je prejšnja stranka.',
            'Zanima jo tudi tiskan album poleg digitalnih fotografij.',
            null,
            null,
        ];

        return $notes[array_rand($notes)];
    }

    private function maybeTags(): ?array
    {
        $pool = ['stalna stranka', 'poroka', 'priporočilo', 'družina'];

        if (rand(0, 2) === 0) {
            return null;
        }

        return [$pool[array_rand($pool)]];
    }

    /** @return Collection<int, Conversation> */
    private function createConversationsAndMessages($customers)
    {
        $conversations = collect();

        // Nina Kovač — the explicit demo flow: enquiry -> appointment -> order.
        $nina = $customers[0];
        $ninaConversation = Conversation::create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->channels[ChannelType::Instagram->value]->id,
            'customer_id' => $nina->id,
            'customer_display_name' => $nina->full_name,
            'customer_username' => '@'.Str::slug($nina->full_name, ''),
            'status' => ConversationStatus::OrderConfirmed,
            'unread_count' => 0,
        ]);
        $ninaStart = Carbon::now()->subHours(2);
        $this->backdate($ninaConversation, $ninaStart);
        $this->seedThread($ninaConversation, [
            [MessageSenderType::Customer, 'Živjo, zanima me družinsko fotografiranje.'],
            [MessageSenderType::Business, 'Živjo Nina! Super, z veseljem 😊 Za koliko oseb in kdaj bi vam ustrezalo?'],
            [MessageSenderType::Customer, 'Za 4 osebe, morda to soboto dopoldan?'],
            [MessageSenderType::Business, 'Odlično, imam prosto soboto ob 10:00. Cena je 120 €, ara za rezervacijo pa 30 €.'],
            [MessageSenderType::Customer, 'Zveni super, rezervirajte nam ta termin prosim!'],
        ], $ninaStart);
        $conversations->push($ninaConversation);

        // Tina Zupan — wedding photography consultation.
        $tina = $customers[1];
        $tinaConversation = Conversation::create([
            'workspace_id' => $this->workspace->id,
            'channel_id' => $this->channels[ChannelType::Instagram->value]->id,
            'customer_id' => $tina->id,
            'customer_display_name' => $tina->full_name,
            'customer_username' => '@'.Str::slug($tina->full_name, ''),
            'status' => ConversationStatus::OrderConfirmed,
            'unread_count' => 1,
        ]);
        $tinaStart = Carbon::now()->subDays(9);
        $this->backdate($tinaConversation, $tinaStart);
        $this->seedThread($tinaConversation, [
            [MessageSenderType::Customer, 'Pozdravljeni, poročamo se septembra — bi bili na voljo za fotografiranje?'],
            [MessageSenderType::Business, 'Čestitke! 🎉 Z veseljem, najprej se dobimo na kratkem posvetu, da se uskladimo glede poteka dneva.'],
            [MessageSenderType::Customer, 'Odlično, kdaj bi imeli čas za posvet?'],
            [MessageSenderType::Business, 'Naslednji teden mi ustreza več terminov, vam pošljem izbor.'],
            [MessageSenderType::Customer, 'Hvala, se že veselim!'],
        ], $tinaStart);
        $conversations->push($tinaConversation);

        $customerPool = $customers->slice(2)->values();
        $total = 16;
        $unlinkedCount = 3;
        $startDates = $this->spreadDates($total);

        for ($i = 0; $i < $total; $i++) {
            $isUnlinked = $i < $unlinkedCount;
            $customer = $isUnlinked ? null : $customerPool[($i - $unlinkedCount) % $customerPool->count()];

            $channelType = $customer
                ? $customer->identities->first()?->channel_type ?? $this->weightedChannel()
                : $this->weightedChannel();

            $leadName = $isUnlinked ? $this->names[array_rand($this->names)] : null;
            $displayName = $customer?->full_name ?? "{$leadName['first']} {$leadName['last']}";
            $status = $this->weightedConversationStatus();
            $startedAt = array_pop($startDates);

            $conversation = Conversation::create([
                'workspace_id' => $this->workspace->id,
                'channel_id' => $this->channels[$channelType->value]->id,
                'customer_id' => $customer?->id,
                'customer_display_name' => $displayName,
                'customer_username' => '@'.Str::slug($displayName, ''),
                'status' => $status,
                'unread_count' => 0,
            ]);

            $this->backdate($conversation, $startedAt);
            $this->seedGenericThread($conversation, $displayName, $status, $startedAt);
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
            $roll <= 90 => ConversationStatus::OrderConfirmed,
            default => ConversationStatus::Closed,
        };
    }

    /** @param array<int, array{0: MessageSenderType, 1: string}> $thread */
    private function seedThread(Conversation $conversation, array $thread, Carbon $startedAt): void
    {
        $timestamp = $startedAt->copy();
        $lastMessage = null;

        foreach ($thread as [$sender, $body]) {
            $timestamp = $timestamp->copy()->addMinutes(rand(3, 20));

            $lastMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => $sender,
                'body' => $body,
                'message_type' => MessageType::Text,
                'sent_at' => $timestamp,
            ]);
        }

        $conversation->update([
            'last_message_preview' => Str::limit($lastMessage->body, 120),
            'last_message_at' => $lastMessage->sent_at,
        ]);
    }

    private function seedGenericThread(Conversation $conversation, string $displayName, ConversationStatus $status, Carbon $startedAt): void
    {
        $firstName = Str::before($displayName, ' ');
        $serviceOrProduct = array_rand(array_merge($this->services, $this->products)) ?: 'fotografiranje';
        $day = ['ponedeljek', 'sredo', 'petek', 'soboto'][array_rand(range(0, 3))];

        $enquiries = [
            "Živjo! Ali ste na voljo za {$serviceOrProduct} v {$day}?",
            'Pozdravljeni, zanima me cena fotografiranja.',
            'Živjo, videla sem vaše delo na Instagramu — sprejemate nova naročila?',
            'Pozdravljeni! Bi bilo možno naročiti tudi tiskan album?',
        ];

        $businessReplies = [
            "Živjo {$firstName}! Hvala za sporočilo 📸 Z veseljem, kdaj bi vam ustrezalo?",
            'Cena je odvisna od trajanja in lokacije — pošljem vam podroben cenik.',
            'Seveda sprejemamo nova naročila! Povejte mi več o priložnosti.',
            'Album lahko dodamo k vsakemu paketu, brez težav.',
        ];

        $confirmations = ['Odlično, hvala!', 'To mi ustreza, rezervirajte prosim.', 'Super, se slišimo!'];

        $thread = [];
        $thread[] = ['sender' => MessageSenderType::Customer, 'body' => $enquiries[array_rand($enquiries)]];

        $messageCount = match ($status) {
            ConversationStatus::NewEnquiry => rand(1, 2),
            ConversationStatus::NeedsQuote => rand(2, 3),
            ConversationStatus::WaitingForCustomer => rand(3, 4),
            ConversationStatus::OrderConfirmed => rand(3, 5),
            ConversationStatus::Closed => rand(2, 3),
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

    /** @return Collection<int, Appointment> */
    private function createAppointments($customers, $conversations)
    {
        $appointments = collect();

        $nina = $customers[0];
        $ninaConversation = $conversations->firstWhere('customer_id', $nina->id);
        $familyService = $this->services['Družinsko fotografiranje'];

        $ninaAppointment = Appointment::create([
            'workspace_id' => $this->workspace->id,
            'customer_id' => $nina->id,
            'conversation_id' => $ninaConversation?->id,
            'channel_id' => $ninaConversation?->channel_id ?? $nina->primary_channel_id,
            'service_name' => $familyService->name,
            'appointment_date' => Carbon::now()->next(Carbon::SATURDAY),
            'start_time' => '10:00:00',
            'duration_minutes' => 60,
            'price' => 120,
            'deposit_amount' => 30,
            'amount_paid' => 30,
            'payment_status' => PaymentStatus::DepositPaid->value,
            'status' => AppointmentStatus::Confirmed,
        ]);
        $ninaAppointment->items()->create([
            'catalog_item_id' => $familyService->id,
            'title' => $familyService->name,
            'quantity' => 1,
            'unit_price' => 120,
        ]);
        $this->backdate($ninaAppointment, Carbon::now()->subHours(1));
        $appointments->push($ninaAppointment);

        $tina = $customers[1];
        $tinaConversation = $conversations->firstWhere('customer_id', $tina->id);
        $weddingService = $this->services['Poročno fotografiranje'];

        $tinaAppointment = Appointment::create([
            'workspace_id' => $this->workspace->id,
            'customer_id' => $tina->id,
            'conversation_id' => $tinaConversation?->id,
            'channel_id' => $tinaConversation?->channel_id ?? $tina->primary_channel_id,
            'service_name' => $weddingService->name,
            'appointment_date' => Carbon::now()->addMonths(2),
            'start_time' => '11:00:00',
            'duration_minutes' => 480,
            'price' => 890,
            'deposit_amount' => 200,
            'amount_paid' => 200,
            'payment_status' => PaymentStatus::DepositPaid->value,
            'status' => AppointmentStatus::Confirmed,
        ]);
        $tinaAppointment->items()->create([
            'catalog_item_id' => $weddingService->id,
            'title' => $weddingService->name,
            'quantity' => 1,
            'unit_price' => 890,
        ]);
        $this->backdate($tinaAppointment, Carbon::now()->subDays(8));
        $appointments->push($tinaAppointment);

        $serviceNames = array_keys($this->services);
        $slots = ['09:00', '10:00', '11:00', '13:00', '15:00', '17:00'];
        $customerPool = $customers->slice(2)->values();

        $plan = [
            [-30, AppointmentStatus::Completed, 3],
            [-14, AppointmentStatus::Completed, 3],
            [-4, AppointmentStatus::Completed, 2],
            [-2, AppointmentStatus::Cancelled, 1],
            [0, AppointmentStatus::Confirmed, 1],
            [2, AppointmentStatus::Confirmed, 1],
            [5, AppointmentStatus::Requested, 1],
        ];

        $totalPlanned = array_sum(array_column($plan, 2));
        $createdDates = $this->spreadDates($totalPlanned);

        foreach ($plan as [$dayOffset, $status, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $customer = $customerPool->random();
                $serviceName = $serviceNames[array_rand($serviceNames)];
                $service = $this->services[$serviceName];
                $date = Carbon::today()->addDays($dayOffset);

                $paymentStatus = match ($status) {
                    AppointmentStatus::Completed => PaymentStatus::Paid,
                    AppointmentStatus::Cancelled => PaymentStatus::Unpaid,
                    AppointmentStatus::Confirmed => PaymentStatus::DepositPaid,
                    AppointmentStatus::Requested => PaymentStatus::Unpaid,
                    default => PaymentStatus::Unpaid,
                };

                $price = (float) $service->default_price;
                $deposit = (float) ($service->default_deposit_amount ?? 0);
                $amountPaid = match ($paymentStatus) {
                    PaymentStatus::Paid => $price,
                    PaymentStatus::DepositPaid => $deposit,
                    default => 0,
                };

                $createdAt = array_pop($createdDates);
                if ($date->isPast() && $createdAt->gt($date)) {
                    $createdAt = $date->copy()->subHours(rand(1, 48));
                }

                $appointment = Appointment::create([
                    'workspace_id' => $this->workspace->id,
                    'customer_id' => $customer->id,
                    'service_name' => $serviceName,
                    'appointment_date' => $date,
                    'start_time' => $slots[array_rand($slots)].':00',
                    'duration_minutes' => $service->default_duration_minutes,
                    'price' => $price,
                    'deposit_amount' => $deposit,
                    'amount_paid' => $amountPaid,
                    'payment_status' => $paymentStatus->value,
                    'status' => $status,
                ]);

                $appointment->items()->create([
                    'catalog_item_id' => $service->id,
                    'title' => $serviceName,
                    'quantity' => 1,
                    'unit_price' => $price,
                ]);

                $this->backdate($appointment, $createdAt);
                $appointments->push($appointment);
            }
        }

        return $appointments;
    }

    /** @return Collection<int, Order> */
    private function createOrders($customers, $conversations)
    {
        $orders = collect();

        $nina = $customers[0];
        $album = $this->products['Foto album'];
        $ninaOrder = Order::create([
            'workspace_id' => $this->workspace->id,
            'customer_id' => $nina->id,
            'title' => $album->name,
            'description' => 'Foto album iz družinskega fotografiranja.',
            'due_date' => Carbon::now()->next(Carbon::SATURDAY)->addWeek(),
            'price' => 45,
            'deposit_amount' => 0,
            'amount_paid' => 0,
            'payment_status' => PaymentStatus::Unpaid->value,
            'status' => OrderStatus::New->value,
        ]);
        $ninaOrder->items()->create([
            'catalog_item_id' => $album->id,
            'title' => $album->name,
            'quantity' => 1,
            'unit_price' => 45,
        ]);
        $this->backdate($ninaOrder, Carbon::now()->subMinutes(20));
        $orders->push($ninaOrder);

        $tina = $customers[1];
        $giftPackage = $this->products['Darilni paket'];
        $tinaOrder = Order::create([
            'workspace_id' => $this->workspace->id,
            'customer_id' => $tina->id,
            'title' => $giftPackage->name,
            'description' => 'Album, odtisi in USB s poročnimi fotografijami.',
            'due_date' => Carbon::now()->addMonths(2)->addWeek(),
            'price' => 140,
            'deposit_amount' => 40,
            'amount_paid' => 40,
            'payment_status' => PaymentStatus::DepositPaid->value,
            'status' => OrderStatus::Confirmed->value,
        ]);
        $tinaOrder->items()->create([
            'catalog_item_id' => $giftPackage->id,
            'title' => $giftPackage->name,
            'quantity' => 1,
            'unit_price' => 140,
        ]);
        $this->backdate($tinaOrder, Carbon::now()->subDays(7));
        $orders->push($tinaOrder);

        $productNames = array_keys($this->products);
        $customerPool = $customers->slice(2)->values();

        $plan = [
            [OrderStatus::New, -20, 2],
            [OrderStatus::QuoteSent, -15, 1],
            [OrderStatus::Confirmed, -10, 1],
            [OrderStatus::InProgress, -3, 1],
            [OrderStatus::Ready, 0, 1],
            [OrderStatus::Completed, -25, 2],
        ];

        $totalPlanned = array_sum(array_column($plan, 2));
        $createdDates = $this->spreadDates($totalPlanned);

        foreach ($plan as [$status, $dayOffset, $count]) {
            for ($i = 0; $i < $count; $i++) {
                $customer = $customerPool->random();
                $productName = $productNames[array_rand($productNames)];
                $product = $this->products[$productName];
                $dueDate = Carbon::today()->addDays($dayOffset + rand(0, 5));

                $paymentStatus = match ($status) {
                    OrderStatus::Completed => PaymentStatus::Paid,
                    OrderStatus::Ready, OrderStatus::InProgress, OrderStatus::Confirmed => PaymentStatus::DepositPaid,
                    default => PaymentStatus::Unpaid,
                };

                $price = (float) $product->default_price;
                $deposit = (float) ($product->default_deposit_amount ?? 0);
                $amountPaid = match ($paymentStatus) {
                    PaymentStatus::Paid => $price,
                    PaymentStatus::DepositPaid => $deposit,
                    default => 0,
                };

                $createdAt = array_pop($createdDates);

                $order = Order::create([
                    'workspace_id' => $this->workspace->id,
                    'customer_id' => $customer->id,
                    'title' => $productName,
                    'description' => $product->description,
                    'due_date' => $dueDate,
                    'price' => $price,
                    'deposit_amount' => $deposit,
                    'amount_paid' => $amountPaid,
                    'payment_status' => $paymentStatus->value,
                    'status' => $status->value,
                ]);

                $order->items()->create([
                    'catalog_item_id' => $product->id,
                    'title' => $productName,
                    'quantity' => 1,
                    'unit_price' => $price,
                ]);

                $this->backdate($order, $createdAt);
                $orders->push($order);
            }
        }

        return $orders;
    }

    private function createFollowUps($customers, $appointments, $orders): void
    {
        $count = 8;

        for ($i = 0; $i < $count; $i++) {
            $dueOffset = match (true) {
                $i < 2 => rand(-2, -1),
                $i < 5 => 0,
                default => rand(1, 4),
            };

            $dueAt = Carbon::now()->addDays($dueOffset)->setTime(rand(9, 17), [0, 15, 30, 45][array_rand([0, 15, 30, 45])]);

            $roll = rand(1, 3);

            if ($roll === 1 && $appointments->isNotEmpty()) {
                $appointment = $appointments->random();
                $followable = $appointment;
                $note = "Potrdi termin za {$appointment->customer->full_name}";
            } elseif ($roll === 2 && $orders->isNotEmpty()) {
                $order = $orders->random();
                $followable = $order;
                $note = "Preveri plačilo are — {$order->customer->full_name} — {$order->title}";
            } else {
                $customer = $customers->random();
                $followable = $customer;
                $note = "Pošlji opomnik stranki {$customer->full_name}";
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

    private function createActivityLogs($customers, $appointments, $orders): void
    {
        foreach ($appointments as $appointment) {
            $this->logAt([
                'workspace_id' => $this->workspace->id,
                'subject_type' => Appointment::class,
                'subject_id' => $appointment->id,
                'type' => 'appointment_created',
                'description' => "Termin {$appointment->appointment_number} ({$appointment->service_name}) ustvarjen za {$appointment->customer->full_name}",
            ], $appointment->created_at);
        }

        foreach ($orders as $order) {
            $this->logAt([
                'workspace_id' => $this->workspace->id,
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'type' => 'order_created',
                'description' => "Naročilo {$order->order_number} ustvarjeno za stranko {$order->customer->full_name}",
            ], $order->created_at);
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
