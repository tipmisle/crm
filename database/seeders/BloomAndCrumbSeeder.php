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
        ['first' => 'Ana', 'last' => 'Novak'],
        ['first' => 'Marko', 'last' => 'Kovač'],
        ['first' => 'Sara', 'last' => 'Kos'],
        ['first' => 'Eva', 'last' => 'Kralj'],
        ['first' => 'Luka', 'last' => 'Potočnik'],
        ['first' => 'Nina', 'last' => 'Horvat'],
        ['first' => 'Jaka', 'last' => 'Vogrin'],
        ['first' => 'Petra', 'last' => 'Zupan'],
        ['first' => 'Tomaž', 'last' => 'Fistrič'],
        ['first' => 'Lara', 'last' => 'Mlakar'],
        ['first' => 'Sofija', 'last' => 'Turk'],
        ['first' => 'David', 'last' => 'Bregar'],
        ['first' => 'Maja', 'last' => 'Šuštar'],
        ['first' => 'Jan', 'last' => 'Novak'],
        ['first' => 'Katarina', 'last' => 'Kranjc'],
        ['first' => 'Oskar', 'last' => 'Kotnik'],
        ['first' => 'Ivana', 'last' => 'Perić'],
        ['first' => 'Benjamin', 'last' => 'Hren'],
        ['first' => 'Ela', 'last' => 'Rozman'],
        ['first' => 'Luka', 'last' => 'Zorko'],
        ['first' => 'Klara', 'last' => 'Vovk'],
        ['first' => 'Matej', 'last' => 'Krajnc'],
        ['first' => 'Gaja', 'last' => 'Erjavec'],
        ['first' => 'Filip', 'last' => 'Vidmar'],
        ['first' => 'Ana', 'last' => 'Marković'],
        ['first' => 'Nejc', 'last' => 'Bevk'],
        ['first' => 'Julija', 'last' => 'Weber'],
        ['first' => 'Rok', 'last' => 'Golob'],
        ['first' => 'Neža', 'last' => 'Miklavčič'],
        ['first' => 'Simon', 'last' => 'Pavlič'],
    ];

    /** @var array<int, string> */
    private array $products = [
        'Rojstnodnevna torta – Tema Samorog',
        '3-nadstropna poročna torta',
        'Torta za razkritje spola',
        'Torta za zaroko – Cvetlični dizajn',
        'Škatla sladic – Mini tartice (12 kos)',
        'Stolp iz muffinov – 40 kosov',
        'Torta s številko po meri',
        'Torta za baby shower – Pastelni oblaki',
        'Naročilo za podjetje – 20 mini tort',
        'Obletniška torta – Red Velvet',
        'Torta za maturantski ples',
        'Torta za upokojitev',
        'Miza sladic za dekliščino',
        'Škatla sladic za valentinovo',
        'Škatla božičnih piškotov',
        'Muffini za noč čarovnic',
        'Rojstnodnevna torta – Nogometna tema',
        'Čokoladna torta s prelivom',
        'Stolp iz makronov',
        'Paket mini tort (6 kos)',
    ];

    private array $areas = ['center mesta', 'severni del mesta', 'Ob Savi', 'mestno središče', 'Hrastov Park'];

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
            'Raje ima vaniljev biskvit kot čokoladnega.',
            'Alergična na oreščke — vedno preveri recept.',
            'Obožuje pastelne barvne palete.',
            'Priporočila jo je prejšnja stranka.',
            'Vedno rezervira zgodaj, zelo organizirana.',
            'Raje pride po naročilo osebno kot dostava.',
            null,
            null,
        ];

        return $notes[array_rand($notes)];
    }

    private function maybeTags(): ?array
    {
        $pool = ['VIP', 'poroka', 'stalna stranka', 'podjetje', 'priporočilo', 'lokalna dostava'];

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
            "Živjo! Ali ste na voljo za {$product} dne {$date}?",
            "Pozdravljeni! Ali izdelujete {$product}? Potrebujemo nekaj za približno {$guests} gostov.",
            "Živjo, videla sem vašo {$product} na Instagramu — dostavljate tudi v {$area}?",
            'Bi lahko naredili nekaj podobnega temu, ampak v roza barvi? [priložena fotografija]',
            "Koliko bi stala {$product} za {$guests} ljudi?",
            "Pozdravljeni! Všeč so mi vaše torte, imate prosti termin okoli {$date}?",
            'Živjo! Potrebujemo torto za prvi rojstni dan naše hčerke, nekaj s temo zajčka?',
            "Pozdravljeni, kakšna je cena za {$product} za {$guests} oseb?",
        ];

        $businessReplies = [
            "Živjo {$firstName}! Hvala za povpraševanje 😊 Da, ta teden imam prosti termin. Kateri okus ste imeli v mislih?",
            "Pozdravljeni! Čestitke 🎉 Za {$guests} gostov bi priporočila {$product}, cena bi bila okoli {$price} €.",
            'Seveda, roza različico lahko brez težav naredim! Pripravila vam bom ponudbo.',
            "Za {$guests} oseb bi to bilo okoli {$price} €, vključno s standardno dostavo.",
            "Da, dostavljamo tudi v {$area} za majhen doplačilo. Do kdaj jo potrebujete?",
            'To zveni čudovito! Kmalu vam pošljem nekaj predlogov dizajna.',
            "Živjo {$firstName}! To bomo z veseljem pripravili — {$product} znese okoli {$price} €.",
        ];

        $confirmations = [
            'Odlično, vzamem!',
            'To zveni super, gremo naprej!',
            'Da prosim, to nam ustreza!',
            'Super, hvala! Kam lahko nakažem aro?',
            'Zveni dobro, do kdaj potrebujete aro?',
        ];

        $depositInfo = [
            "Odlično! Aro v vrednosti {$deposit} € lahko nakažete na TRR, podatke pošljem takoj.",
            "Plačate lahko preko povezave, ki jo pošljem v kratkem — samo {$deposit} € are za potrditev termina.",
            "Odlično — {$deposit} € are zagotovi termin, podatke za plačilo pošljem takoj.",
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
                    'internal_notes' => rand(0, 3) === 0 ? 'Pred peko še enkrat preveri opombe o alergijah.' : null,
                    'customer_notes' => rand(0, 3) === 0 ? 'Prosimo, naj bo brez mlečnih izdelkov, če je mogoče.' : null,
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
            "Naročilo po meri — {$product}, okus in dizajn se dokončno uskladita s stranko.",
            "{$product} — vaniljev biskvit, prevleka iz maslene kreme, razen če ni drugače navedeno.",
            "{$product} — čokoladna osnova, stranka je želela minimalne dekoracije.",
            "{$product} — vključuje dostavo in postavitev na lokaciji.",
            "{$product} — prevzem osebno, embalaža vključena.",
        ];

        return $descriptions[array_rand($descriptions)];
    }

    private function orderNoteBody(OrderStatus $status): string
    {
        $notes = [
            OrderStatus::New->value => 'Čakamo na potrditev želenega okusa pred pripravo ponudbe.',
            OrderStatus::QuoteNeeded->value => 'Treba je preračunati stroške sestavin pred pošiljanjem cene.',
            OrderStatus::QuoteSent->value => 'Ponudba poslana, sledi opomnik čez nekaj dni, če ni odgovora.',
            OrderStatus::WaitingForCustomer->value => 'Stranka je povedala, da se mora dogovoriti s partnerjem.',
            OrderStatus::Confirmed->value => 'Ara prejeta, datum zaklenjen v koledarju.',
            OrderStatus::InProgress->value => 'Peka načrtovana, dekoracija sledi dan pred prevzemom.',
            OrderStatus::Ready->value => 'Pripravljeno za prevzem/dostavo, stranka obveščena.',
            OrderStatus::Completed->value => 'Dostavljeno brez težav, stranka je bila navdušena.',
            OrderStatus::Cancelled->value => 'Stranka je odpovedala zaradi spremembe načrtov.',
        ];

        return $notes[$status->value] ?? 'Ni dodatnih opomb.';
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
                $note = "Preveri ponudbo pri stranki {$customer->full_name}";
                $followable = $customer;
            } elseif ($type === 2 && $orders->isNotEmpty()) {
                $order = $orders->random();
                $note = "Opomnik za aro — {$order->customer->full_name} — {$order->title}";
                $followable = $order;
            } else {
                $conversation = $conversations->random();
                $name = $conversation->displayName();
                $days = rand(2, 5);
                $note = "{$name} se ni oglasil(a) že {$days} dni";
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
                'description' => "Naročilo {$order->order_number} ustvarjeno za stranko {$order->customer->full_name}",
            ], $order->created_at);

            if (in_array($order->status, [OrderStatus::Completed, OrderStatus::Confirmed, OrderStatus::Cancelled], true)) {
                $this->logAt([
                    'workspace_id' => $this->workspace->id,
                    'subject_type' => Order::class,
                    'subject_id' => $order->id,
                    'type' => 'status_changed',
                    'description' => "Naročilo {$order->order_number} označeno kot {$order->status->label()}",
                ], $order->created_at->copy()->addHours(rand(1, 48)));
            }
        }

        foreach ($customers as $customer) {
            $this->logAt([
                'workspace_id' => $this->workspace->id,
                'subject_type' => Customer::class,
                'subject_id' => $customer->id,
                'type' => 'customer_created',
                'description' => "{$customer->full_name} dodan(a) kot stranka",
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
