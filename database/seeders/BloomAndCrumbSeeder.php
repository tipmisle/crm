<?php

namespace Database\Seeders;

use App\Enums\ChannelType;
use App\Enums\ConversationStatus;
use App\Enums\MessageSenderType;
use App\Enums\MessageType;
use App\Enums\OrderStatus;
use App\Enums\PaymentStatus;
use App\Models\ActivityLog;
use App\Models\Channel;
use App\Models\Conversation;
use App\Models\Customer;
use App\Models\CustomerIdentity;
use App\Models\FollowUp;
use App\Models\Message;
use App\Models\Order;
use App\Models\User;
use App\Models\Workspace;
use App\Models\WorkspaceMember;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class BloomAndCrumbSeeder extends Seeder
{
    private Workspace $workspace;

    /** @var array<string, Channel> */
    private array $channels = [];

    /** @var array<int, array{first: string, last: string}> */
    private array $names = [
        ['first' => 'Anna', 'last' => 'Novak'],
        ['first' => 'Marko', 'last' => 'Kovač'],
        ['first' => 'Sarah', 'last' => 'Bennett'],
        ['first' => 'Emma', 'last' => 'Clarke'],
        ['first' => 'Liam', 'last' => "O'Connor"],
        ['first' => 'Nina', 'last' => 'Horvat'],
        ['first' => 'James', 'last' => 'Wilson'],
        ['first' => 'Petra', 'last' => 'Zupan'],
        ['first' => 'Tom', 'last' => 'Fischer'],
        ['first' => 'Laura', 'last' => 'Meyer'],
        ['first' => 'Sophie', 'last' => 'Turner'],
        ['first' => 'David', 'last' => 'Brown'],
        ['first' => 'Mia', 'last' => 'Schmidt'],
        ['first' => 'Jan', 'last' => 'Novak'],
        ['first' => 'Katarina', 'last' => 'Kranjc'],
        ['first' => 'Oliver', 'last' => 'Smith'],
        ['first' => 'Ivana', 'last' => 'Perić'],
        ['first' => 'Ben', 'last' => 'Harris'],
        ['first' => 'Ella', 'last' => 'Roberts'],
        ['first' => 'Luka', 'last' => 'Zorko'],
        ['first' => 'Chloe', 'last' => 'Walker'],
        ['first' => 'Matej', 'last' => 'Krajnc'],
        ['first' => 'Grace', 'last' => 'Evans'],
        ['first' => 'Filip', 'last' => 'Vidmar'],
        ['first' => 'Ana', 'last' => 'Marković'],
        ['first' => 'Noah', 'last' => 'Baker'],
        ['first' => 'Julia', 'last' => 'Weber'],
        ['first' => 'Rok', 'last' => 'Golob'],
        ['first' => 'Isabelle', 'last' => 'Moore'],
        ['first' => 'Simon', 'last' => 'Pavlič'],
    ];

    /** @var array<int, string> */
    private array $products = [
        'Birthday Cake – Unicorn Theme',
        '3-Tier Wedding Cake',
        'Gender Reveal Cake',
        'Engagement Cake – Floral Design',
        'Dessert Box – Mini Tarts (12 pc)',
        'Cupcake Tower – 40 Cupcakes',
        "Custom Number Cake",
        'Baby Shower Cake – Pastel Clouds',
        'Corporate Order – 20 Mini Cakes',
        'Anniversary Cake – Red Velvet',
        'Graduation Cake',
        'Retirement Cake',
        'Bridal Shower Dessert Table',
        "Valentine's Dessert Box",
        'Christmas Cookie Box',
        'Halloween Themed Cupcakes',
        'Birthday Cake – Football Theme',
        'Chocolate Drip Cake',
        'Macaron Tower',
        'Mini Cake Bundle (6 pc)',
    ];

    private array $areas = ['downtown', 'the north side', 'Riverside', 'the city center', 'Oak Park'];

    public function run(): void
    {
        $this->createWorkspaceAndUser();
        $this->createChannels();
        $customers = $this->createCustomers();
        $conversations = $this->createConversationsAndMessages($customers);
        $orders = $this->createOrders($customers, $conversations);
        $this->createFollowUps($customers, $orders, $conversations);
        $this->createActivityLogs($customers, $orders);
    }

    private function createWorkspaceAndUser(): void
    {
        $this->workspace = Workspace::create([
            'name' => 'Bloom & Crumb',
            'slug' => 'bloom-and-crumb',
            'email' => 'hello@bloomandcrumb.com',
            'timezone' => 'Europe/Ljubljana',
            'currency' => 'EUR',
        ]);

        $user = User::updateOrCreate(
            ['email' => 'tjasa@flowout.com'],
            [
                'name' => 'Tjaša Jereb',
                'password' => bcrypt('password'),
                'email_verified_at' => now(),
                'current_workspace_id' => $this->workspace->id,
            ]
        );

        WorkspaceMember::create([
            'workspace_id' => $this->workspace->id,
            'user_id' => $user->id,
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
                    ChannelType::Instagram => '@bloomandcrumb',
                    ChannelType::FacebookMessenger => 'Bloom & Crumb',
                    ChannelType::TikTok => '@bloomandcrumb',
                    ChannelType::WhatsApp => 'Bloom & Crumb WhatsApp',
                    ChannelType::Email => 'hello@bloomandcrumb.com',
                    ChannelType::Website => 'bloomandcrumb.com',
                },
                'status' => 'not_connected',
            ]);
        }
    }

    /** @return \Illuminate\Support\Collection<int, Customer> */
    private function createCustomers()
    {
        $customers = collect();

        foreach ($this->names as $i => $name) {
            $fullName = "{$name['first']} {$name['last']}";
            $username = Str::slug($fullName, '').['_cakes', '', '.eats', ''][($i % 4)];
            $channelType = $this->weightedChannel();
            $firstContact = Carbon::now()->subDays(rand(10, 240));

            $customer = Customer::create([
                'workspace_id' => $this->workspace->id,
                'full_name' => $fullName,
                'email' => Str::lower("{$name['first']}.{$name['last']}@example.com"),
                'phone' => '+386 '.rand(30, 70).' '.rand(100, 999).' '.rand(100, 999),
                'notes' => $this->maybeNote($fullName),
                'tags' => $this->maybeTags(),
                'first_contacted_at' => $firstContact,
                'last_interaction_at' => $firstContact->copy()->addDays(rand(0, 200)),
            ]);

            CustomerIdentity::create([
                'customer_id' => $customer->id,
                'workspace_id' => $this->workspace->id,
                'channel_type' => $channelType,
                'external_id' => (string) rand(100000000, 999999999),
                'username' => $channelType === ChannelType::Email ? $customer->email : '@'.$username,
                'display_name' => $fullName,
            ]);

            $customer->update(['primary_channel_id' => $this->channels[$channelType->value]->id]);

            if (rand(0, 4) === 0) {
                CustomerIdentity::create([
                    'customer_id' => $customer->id,
                    'workspace_id' => $this->workspace->id,
                    'channel_type' => ChannelType::Email,
                    'username' => $customer->email,
                    'display_name' => $fullName,
                ]);
            }

            $customers->push($customer);
        }

        return $customers;
    }

    private function weightedChannel(): ChannelType
    {
        $roll = rand(1, 100);

        return match (true) {
            $roll <= 45 => ChannelType::Instagram,
            $roll <= 65 => ChannelType::FacebookMessenger,
            $roll <= 80 => ChannelType::WhatsApp,
            $roll <= 92 => ChannelType::Email,
            $roll <= 98 => ChannelType::TikTok,
            default => ChannelType::Website,
        };
    }

    private function maybeNote(string $fullName): ?string
    {
        $notes = [
            'Prefers vanilla sponge over chocolate.',
            'Allergic to nuts — always double check recipe.',
            'Loves pastel color palettes.',
            'Referred by a previous customer.',
            'Always books early, very organized.',
            'Prefers pickup over delivery.',
            null,
            null,
        ];

        return $notes[array_rand($notes)];
    }

    private function maybeTags(): ?array
    {
        $pool = ['VIP', 'wedding', 'repeat customer', 'corporate', 'referral', 'local delivery'];

        if (rand(0, 2) === 0) {
            return null;
        }

        return array_values(array_unique([$pool[array_rand($pool)], $pool[array_rand($pool)]]));
    }

    /** @return \Illuminate\Support\Collection<int, Conversation> */
    private function createConversationsAndMessages($customers)
    {
        $conversations = collect();
        $total = 42;
        $unlinkedCount = 8;

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
            $startedAt = Carbon::now()->subDays(rand(0, 14))->subHours(rand(0, 20));

            $conversation = Conversation::create([
                'workspace_id' => $this->workspace->id,
                'channel_id' => $this->channels[$channelType->value]->id,
                'customer_id' => $customer?->id,
                'customer_display_name' => $displayName,
                'customer_username' => $username,
                'status' => $status,
                'unread_count' => 0,
            ]);

            $this->seedMessages($conversation, $displayName, $status, $startedAt);
            $conversations->push($conversation);
        }

        return $conversations;
    }

    private function weightedConversationStatus(): ConversationStatus
    {
        $roll = rand(1, 100);

        return match (true) {
            $roll <= 25 => ConversationStatus::NewEnquiry,
            $roll <= 45 => ConversationStatus::NeedsQuote,
            $roll <= 65 => ConversationStatus::WaitingForCustomer,
            $roll <= 90 => ConversationStatus::OrderConfirmed,
            default => ConversationStatus::Closed,
        };
    }

    private function seedMessages(Conversation $conversation, string $displayName, ConversationStatus $status, Carbon $startedAt): void
    {
        $firstName = Str::before($displayName, ' ');
        $product = $this->products[array_rand($this->products)];
        $guests = [10, 20, 25, 30, 40, 50, 60][array_rand([10, 20, 25, 30, 40, 50, 60])] ?? 30;
        $guests = [10, 20, 25, 30, 40, 50, 60][array_rand(range(0, 6))];
        $date = Carbon::now()->addDays(rand(5, 45))->format('F j');
        $price = rand(45, 320);
        $deposit = (int) round($price * 0.3);
        $area = $this->areas[array_rand($this->areas)];

        $enquiries = [
            "Hi! Are you available for a {$product} on {$date}?",
            "Hello! Do you make {$product}? We need something for about {$guests} guests.",
            "Hi, I saw your {$product} on Instagram — do you deliver to {$area}?",
            'Could you make something similar to this but in pink? [photo attached]',
            "How much would a {$product} be for {$guests} people?",
            "Hey! Loved your cakes, do you have availability around {$date}?",
            'Hi! We need a cake for our daughter\'s 1st birthday, something with a bunny theme?',
            "Hello, what's your price for a {$product} serving {$guests}?",
        ];

        $businessReplies = [
            "Hi {$firstName}! Thanks for reaching out 😊 Yes I have availability that week. What flavor were you thinking?",
            "Hi! Congratulations 🎉 For {$guests} guests I'd recommend a {$product}, pricing would be around €{$price}.",
            "Sure thing, I can definitely do a pink version! Let me put together a quote for you.",
            "For {$guests} people that would be around €{$price}, that includes standard delivery.",
            "Yes we deliver to {$area} for a small fee. When do you need it by?",
            'That sounds lovely! I\'ll send over a couple of design options shortly.',
            "Hi {$firstName}! We'd love to make that for you — the {$product} works out to about €{$price}.",
        ];

        $confirmations = [
            "Perfect, I'll take it!",
            "That sounds great, let's go ahead!",
            'Yes please, that works for us!',
            "Amazing, thank you! Where can I send the deposit?",
            'Sounds good, when do you need the deposit by?',
        ];

        $depositInfo = [
            "Great! You can send the €{$deposit} deposit via bank transfer, I'll send the details now.",
            "You can pay via the link I'll send shortly — just the €{$deposit} deposit to confirm the date.",
            "Perfect — €{$deposit} deposit secures the date, I'll send payment details over now.",
        ];

        $thread = [];
        $thread[] = ['sender' => MessageSenderType::Customer, 'body' => $enquiries[array_rand($enquiries)]];

        $messageCount = match ($status) {
            ConversationStatus::NewEnquiry => rand(1, 2),
            ConversationStatus::NeedsQuote => rand(2, 3),
            ConversationStatus::WaitingForCustomer => rand(3, 5),
            ConversationStatus::OrderConfirmed => rand(4, 7),
            ConversationStatus::Closed => rand(3, 6),
        };

        while (count($thread) < $messageCount) {
            $lastSender = end($thread)['sender'];

            if ($lastSender === MessageSenderType::Customer) {
                $thread[] = ['sender' => MessageSenderType::Business, 'body' => $businessReplies[array_rand($businessReplies)]];
            } else {
                if (in_array($status, [ConversationStatus::OrderConfirmed, ConversationStatus::Closed], true) && count($thread) >= $messageCount - 2) {
                    $thread[] = ['sender' => MessageSenderType::Customer, 'body' => $confirmations[array_rand($confirmations)]];
                } else {
                    $thread[] = ['sender' => MessageSenderType::Customer, 'body' => $enquiries[array_rand($enquiries)]];
                }
            }
        }

        if ($status === ConversationStatus::OrderConfirmed && end($thread)['sender'] === MessageSenderType::Customer) {
            $thread[] = ['sender' => MessageSenderType::Business, 'body' => $depositInfo[array_rand($depositInfo)]];
        }

        $timestamp = $startedAt->copy();
        $lastMessage = null;

        foreach ($thread as $entry) {
            $timestamp = $timestamp->copy()->addMinutes(rand(4, 180));

            $lastMessage = Message::create([
                'conversation_id' => $conversation->id,
                'sender_type' => $entry['sender'],
                'body' => $entry['body'],
                'message_type' => MessageType::Text,
                'sent_at' => $timestamp,
            ]);
        }

        $unread = $lastMessage && $lastMessage->sender_type === MessageSenderType::Customer ? rand(1, 3) : 0;

        $conversation->update([
            'last_message_preview' => Str::limit($lastMessage->body, 120),
            'last_message_at' => $lastMessage->sent_at,
            'unread_count' => $unread,
        ]);
    }

    /** @return \Illuminate\Support\Collection<int, Order> */
    private function createOrders($customers, $conversations)
    {
        $orders = collect();

        $statusPlan = [
            OrderStatus::New->value => 5,
            OrderStatus::QuoteNeeded->value => 6,
            OrderStatus::QuoteSent->value => 6,
            OrderStatus::WaitingForCustomer->value => 5,
            OrderStatus::Confirmed->value => 8,
            OrderStatus::InProgress->value => 7,
            OrderStatus::Ready->value => 4,
            OrderStatus::Completed->value => 13,
            OrderStatus::Cancelled->value => 4,
        ];

        $confirmedConversations = $conversations->filter(
            fn (Conversation $c) => $c->status === ConversationStatus::OrderConfirmed && $c->customer_id
        )->values();

        $convIndex = 0;
        $dueTodayAssigned = 0;

        foreach ($statusPlan as $statusValue => $count) {
            $status = OrderStatus::from($statusValue);

            for ($i = 0; $i < $count; $i++) {
                $useConversation = $convIndex < $confirmedConversations->count() && rand(0, 3) > 0;
                $conversation = $useConversation ? $confirmedConversations[$convIndex++] : null;
                $customer = $conversation?->customer ?? $customers->random();

                $product = $this->products[array_rand($this->products)];
                $price = rand(45, 420);
                $deposit = (int) round($price * 0.3);

                [$dueDate, $paidTowards] = $this->dueDateForStatus($status, $dueTodayAssigned);

                if ($status === OrderStatus::Ready && $dueDate?->isToday()) {
                    $dueTodayAssigned++;
                }

                $paymentStatus = $this->paymentStatusForOrder($status, $paidTowards);
                $amountPaid = match ($paymentStatus) {
                    PaymentStatus::Unpaid => 0,
                    PaymentStatus::DepositDue => 0,
                    PaymentStatus::DepositPaid => $deposit,
                    PaymentStatus::PartiallyPaid => min($price, $deposit + rand(10, 60)),
                    PaymentStatus::Paid => $price,
                };

                $order = Order::create([
                    'workspace_id' => $this->workspace->id,
                    'customer_id' => $customer->id,
                    'conversation_id' => $conversation?->id,
                    'channel_id' => $conversation?->channel_id ?? $customer->primary_channel_id,
                    'title' => $product,
                    'description' => $this->orderDescription($product),
                    'due_date' => $dueDate,
                    'due_time' => $dueDate ? sprintf('%02d:00:00', [10, 11, 12, 14, 15, 16][array_rand([10, 11, 12, 14, 15, 16])]) : null,
                    'price' => $price,
                    'deposit_amount' => $deposit,
                    'amount_paid' => $amountPaid,
                    'payment_status' => $paymentStatus,
                    'status' => $status,
                    'internal_notes' => rand(0, 3) === 0 ? 'Double-check allergy notes before baking.' : null,
                    'customer_notes' => rand(0, 3) === 0 ? 'Please keep it dairy-free if possible.' : null,
                    'tags' => rand(0, 2) === 0 ? ['priority'] : null,
                ]);

                if (rand(0, 2) === 0) {
                    $order->notes()->create([
                        'body' => $this->orderNoteBody($status),
                    ]);
                }

                $orders->push($order);
            }
        }

        return $orders;
    }

    private function dueDateForStatus(OrderStatus $status, int $dueTodayAssigned): array
    {
        return match ($status) {
            OrderStatus::New => [rand(0, 1) ? Carbon::now()->addDays(rand(7, 30)) : null, 0],
            OrderStatus::QuoteNeeded => [rand(0, 1) ? Carbon::now()->addDays(rand(7, 30)) : null, 0],
            OrderStatus::QuoteSent => [Carbon::now()->addDays(rand(5, 25)), 0],
            OrderStatus::WaitingForCustomer => [Carbon::now()->addDays(rand(5, 20)), 0],
            OrderStatus::Confirmed => [Carbon::now()->addDays(rand(2, 18)), 1],
            OrderStatus::InProgress => [Carbon::now()->addDays(rand(0, 5)), 1],
            OrderStatus::Ready => [$dueTodayAssigned < 3 ? Carbon::now() : Carbon::now()->addDays(1), 1],
            OrderStatus::Completed => [Carbon::now()->subDays(rand(1, 60)), 2],
            OrderStatus::Cancelled => [Carbon::now()->addDays(rand(-10, 20)), 0],
        };
    }

    private function paymentStatusForOrder(OrderStatus $status, int $paidLevel): PaymentStatus
    {
        if ($status === OrderStatus::Completed) {
            return PaymentStatus::Paid;
        }

        if ($status === OrderStatus::Cancelled) {
            return rand(0, 1) ? PaymentStatus::Unpaid : PaymentStatus::DepositPaid;
        }

        if (in_array($status, [OrderStatus::New, OrderStatus::QuoteNeeded, OrderStatus::QuoteSent], true)) {
            return PaymentStatus::Unpaid;
        }

        $roll = rand(1, 100);

        return match (true) {
            $roll <= 15 => PaymentStatus::Unpaid,
            $roll <= 45 => PaymentStatus::DepositDue,
            $roll <= 75 => PaymentStatus::DepositPaid,
            $roll <= 92 => PaymentStatus::PartiallyPaid,
            default => PaymentStatus::Paid,
        };
    }

    private function orderDescription(string $product): string
    {
        $descriptions = [
            "Custom order — {$product}, flavor and design to be finalized with customer.",
            "{$product} — vanilla sponge, buttercream finish unless noted otherwise.",
            "{$product} — chocolate base, customer requested minimal decoration.",
            "{$product} — includes delivery and setup at venue.",
            "{$product} — pickup order, packaging included.",
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function orderNoteBody(OrderStatus $status): string
    {
        $notes = [
            OrderStatus::New->value => 'Waiting to confirm flavor preferences before quoting.',
            OrderStatus::QuoteNeeded->value => 'Need to measure ingredient costs before sending a price.',
            OrderStatus::QuoteSent->value => 'Quote sent, following up in a couple of days if no reply.',
            OrderStatus::WaitingForCustomer->value => 'Customer said they need to check with their partner.',
            OrderStatus::Confirmed->value => 'Deposit received, date locked in the calendar.',
            OrderStatus::InProgress->value => 'Baking scheduled, decoration to follow the day before pickup.',
            OrderStatus::Ready->value => 'Ready for pickup/delivery, customer notified.',
            OrderStatus::Completed->value => 'Delivered without issues, customer was thrilled.',
            OrderStatus::Cancelled->value => 'Customer cancelled due to change of plans.',
        ];

        return $notes[$status->value] ?? 'No additional notes.';
    }

    private function createFollowUps($customers, $orders, $conversations): void
    {
        $count = 18;

        for ($i = 0; $i < $count; $i++) {
            $type = rand(1, 3);

            $dueOffset = match (true) {
                $i < 4 => rand(-4, -1),
                $i < 10 => 0,
                default => rand(1, 6),
            };

            $dueAt = Carbon::now()->addDays($dueOffset)->setTime(rand(9, 17), [0, 15, 30, 45][array_rand([0, 15, 30, 45])]);

            if ($type === 1) {
                $customer = $customers->random();
                $note = "Follow up with {$customer->full_name} about their quote";
                $followable = $customer;
            } elseif ($type === 2 && $orders->isNotEmpty()) {
                $order = $orders->random();
                $note = "Deposit reminder for {$order->customer->full_name} — {$order->title}";
                $followable = $order;
            } else {
                $conversation = $conversations->random();
                $name = $conversation->displayName();
                $days = rand(2, 5);
                $note = "{$name} hasn't replied for {$days} days";
                $followable = $conversation;
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

    private function createActivityLogs($customers, $orders): void
    {
        foreach ($orders as $order) {
            $this->logAt([
                'workspace_id' => $this->workspace->id,
                'subject_type' => Order::class,
                'subject_id' => $order->id,
                'type' => 'order_created',
                'description' => "Order {$order->order_number} created for {$order->customer->full_name}",
            ], $order->created_at);

            if (in_array($order->status, [OrderStatus::Completed, OrderStatus::Confirmed, OrderStatus::Cancelled], true)) {
                $this->logAt([
                    'workspace_id' => $this->workspace->id,
                    'subject_type' => Order::class,
                    'subject_id' => $order->id,
                    'type' => 'status_changed',
                    'description' => "Order {$order->order_number} marked as {$order->status->label()}",
                ], $order->created_at->copy()->addHours(rand(1, 48)));
            }
        }

        foreach ($customers as $customer) {
            $this->logAt([
                'workspace_id' => $this->workspace->id,
                'subject_type' => Customer::class,
                'subject_id' => $customer->id,
                'type' => 'customer_created',
                'description' => "{$customer->full_name} added as a customer",
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
