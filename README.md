# Bloom & Crumb — Turn conversations into customers and orders

A business operating system for small business owners who sell through Instagram, Facebook Messenger, TikTok, WhatsApp, email, and their website. Built as a Laravel + Inertia + Vue 3 + TypeScript + Tailwind app, seeded with a realistic demo business ("Bloom & Crumb", a custom cake and dessert box business).

## Stack

- Laravel 12, MySQL
- Inertia.js + Vue 3 + TypeScript
- Tailwind CSS v4
- Real Instagram DM + Facebook Messenger integration via the Meta Graph API (see "Meta integration setup" below). Other providers (TikTok, Gmail, Outlook, WhatsApp) are designed for but not yet built.

## Local setup

```bash
composer install
npm install

cp .env.example .env
php artisan key:generate

# Start MySQL (docker-compose maps it to localhost:3307)
docker compose up -d mysql

php artisan migrate:fresh --seed

npm run dev
php artisan serve
```

Log in with the seeded demo account:

- **Email:** tjasa@flowout.com
- **Password:** password

## What's in the demo data

The seeder (`database/seeders/BloomAndCrumbSeeder.php`) creates one workspace ("Bloom & Crumb") with ~30 customers, ~40 conversations across Instagram/Facebook Messenger/WhatsApp/Email/TikTok/Website, realistic message threads, 50+ orders spread across every order/payment status, follow-ups, and an activity timeline — so the app looks like it's been in daily use.

## Core workflow

Incoming DM → open conversation → see whether this person is already a customer → click "Create order" → order is created and the customer is automatically created/linked if they weren't one yet → order appears in Orders → follow-ups appear on Today → next time they message, their full order history is visible beside the conversation.

## Architecture notes

- **Multi-tenant**: everything hangs off `workspaces`, scoped automatically via the `BelongsToWorkspace` trait. One user has one workspace today, but `workspace_members` already supports multiple members per workspace.
- **Integrations vs. Channels**: an `Integration` holds one OAuth-authorized external account (e.g. one Meta login) and its encrypted tokens. A `Channel` is a specific communication endpoint under that integration (one Instagram Professional account, one Facebook Page) — a workspace can connect several Pages and IG accounts under one, or several, integrations. See `app/Models/Integration.php` and `app/Models/Channel.php`.
- **Provider abstraction**: nothing outside `app/Services/Messaging/` knows about Meta's API shape. `MessagingProviderInterface` (implemented today by `MetaMessagingProvider`) defines connect/send/webhook operations; `MessagingProviderManager` resolves the right provider for a given channel. Adding TikTok/Gmail/Outlook/WhatsApp later means adding one new class, not touching the Inbox.
- **Customer identity**: a `customer` can have several `customer_identities`, each keyed on a stable external platform ID (Instagram-scoped ID / PSID), never on display name — so the same person is recognized across messages before and after they become a `Customer`.
- **Ingestion pipeline**: `MessageIngestionService` turns a provider-agnostic `NormalizedIncomingMessage` into `Channel → CustomerIdentity → Customer(optional) → Conversation → Message`, idempotently (`messages.external_message_id` is unique — Meta's webhook retries never create duplicates).
- **Orders are first-class**, decoupled from conversations — a conversation is just the originating channel, not the business record.

## Meta integration setup

This covers connecting real Instagram Professional and Facebook Page accounts for local development/testing, before App Review.

### 1. Create the Meta app

1. Go to [developers.facebook.com](https://developers.facebook.com/) → **My Apps** → **Create App** → choose the **Business** app type.
2. In the app dashboard, add these products: **Facebook Login for Business** and **Messenger** (Messenger's product setup page is also where Instagram messaging is configured, via a Page's linked Instagram Professional account).
3. Under **App Settings → Basic**, note your **App ID** and **App Secret** — these become `META_APP_ID` / `META_APP_SECRET`.

### 2. Configure OAuth redirect

Under **Facebook Login for Business → Settings**, add this as a valid OAuth redirect URI:

```
https://<your-dev-domain>/settings/integrations/meta/callback
```

Meta requires HTTPS for OAuth redirects and webhooks — see step 5 for exposing `localhost` during development.

The Privacy Policy URL for the Meta App Dashboard (App Review) is
`https://belezka.com/politika-zasebnosti` — public, unauthenticated, no
redirect.

### 3. Configure the webhook

Under **Messenger → Settings → Webhooks**, add a callback:

- **Callback URL:** `https://<your-dev-domain>/webhooks/meta`
- **Verify token:** any random string you choose — put the same value in `META_WEBHOOK_VERIFY_TOKEN`

Subscribe to at least the **`messages`** field for both the Page and Instagram webhook objects.

### 4. Required permissions

Requested by the app via **Facebook Login for Business** (see `config/meta.php` → `scopes`):

- `pages_show_list` — list the Pages the user manages
- `pages_messaging` — send/receive Facebook Messenger messages
- `pages_manage_metadata` — subscribe a Page to webhooks
- `instagram_business_basic` — read the linked Instagram Professional account
- `instagram_business_manage_messages` — send/receive Instagram DMs
- `business_management` — required by Meta to resolve Business-owned Pages/IG accounts in some account structures

Standard access to these permissions is enough for testing with your own Pages/accounts and any accounts added as testers/developers below — App Review is only required before *other* businesses can connect their own accounts.

### 5. Add developer/tester accounts

While the app is in Development mode, only people with a role on the app can use it:

1. **App Dashboard → App roles → Roles** — add your own Facebook account as **Administrator** (already there if you created the app), and add teammates as **Developer** or **Tester**.
2. Any Facebook Page you want to connect must be **managed by** one of these accounts (Business Settings → Pages → Add People, or Page Settings → Page Access if it's a personal Page).
3. Any Instagram Professional account (Business or Creator) must be **linked to** one of those Pages (Page Settings → Linked Accounts → Instagram).

### 6. Connect a real Instagram Professional account

1. If you don't have one, convert an Instagram account to **Professional** (Instagram app → Settings → Account type) and link it to a Facebook Page you manage (Page Settings → Linked Accounts).
2. In the CRM: **Settings → Kanali → "Poveži Instagram"**.
3. Approve the Meta OAuth dialog, granting the permissions above.
4. On the account picker that appears back in Settings, select the Page (and its linked Instagram account, if listed) and click **"Poveži izbrane"**.
5. The app subscribes that Page to the webhook automatically.

### 7. Connect a Facebook Page

Same flow as above — **Settings → Kanali → "Poveži Facebook Messenger"**, then pick the Page in the account picker.

### 8. Exposing localhost for webhooks

Meta cannot reach `http://localhost`. During development, tunnel it with `ngrok` (or Cloudflare Tunnel / Expose):

```bash
ngrok http 8010
```

Use the resulting `https://*.ngrok-free.app` URL as `APP_URL`, `META_REDIRECT_URI`, and the webhook Callback URL in the Meta dashboard. ngrok URLs change on restart (on the free tier) — update the Meta dashboard and `.env` each time, or use a reserved ngrok domain.

### 9. Required `.env` values

```bash
META_APP_ID=              # App Dashboard → Settings → Basic
META_APP_SECRET=          # App Dashboard → Settings → Basic
META_REDIRECT_URI="${APP_URL}/settings/integrations/meta/callback"
META_WEBHOOK_VERIFY_TOKEN=   # any random string, must match the Meta dashboard webhook config
META_GRAPH_VERSION=v21.0
```

`APP_URL` must be the same HTTPS tunnel URL used in the Meta dashboard.

### 10. Testing the first real Instagram DM

1. `php artisan queue:work` (webhook processing runs on the `database` queue — nothing is ingested until a worker is running). `composer run dev` already starts this, plus Reverb, for you.
2. Log in, go to **Settings**, connect Instagram (steps 6 above).
3. From a *different* Instagram account, send a DM to the connected account.
4. The conversation appears in **Prejeta pošta** within a couple of seconds, live — no refresh needed (see "Realtime" below).
5. Reply from the existing reply box — it sends through the Graph API and the sent message appears in the thread.
6. Click **"Ustvari stranko"** to attach the Instagram identity to a new Customer, then **"Ustvari naročilo"** to link an order.
7. Send a second DM from the same Instagram account — it resolves to the same Customer and conversation automatically, and the order history is visible in the right-hand sidebar.

### Realtime

The Inbox updates live via **Laravel Reverb** (Laravel's first-party WebSocket server — no third-party service, no extra cost).

- `App\Events\InboxMessageReceived` broadcasts on a private `workspace.{id}.inbox` channel whenever a message (inbound or outbound) is added to a conversation.
- `routes/channels.php` authorizes that channel per-workspace (`$user->current_workspace_id === $workspaceId`) — the same isolation guarantee as everywhere else in the app.
- The broadcast carries no message content, only a conversation id — the frontend reacts by triggering a normal authorized Inertia reload (`resources/js/Pages/Inbox/Index.vue`), so there's no risk of stale/duplicated data or a second permission-check surface.
- A 30-second background poll (`usePoll`) acts as a safety net in case the socket connection silently drops.

Setup: run `composer require laravel/reverb` then `php artisan install:broadcasting --reverb` (this publishes `config/broadcasting.php`, `routes/channels.php`, adds `REVERB_*`/`VITE_REVERB_*` to `.env`, and installs `laravel-echo`/`@laravel/echo-vue`/`pusher-js`). Start it with `php artisan reverb:start` (already wired into `composer run dev`). Reverb runs locally on `REVERB_PORT` (default `8080`) — the browser connects to it directly over your local network, so it does **not** need to go through the Meta webhook tunnel (ngrok/Cloudflare); only Meta's own webhook calls need that.

## Testing

```bash
php artisan test
```

Includes coverage for webhook signature verification, webhook idempotency (Meta's retried deliveries never duplicate a message), workspace isolation (a user can never read or send through another workspace's channel), customer identity matching (repeat senders resolve to the same Customer), and outbound send failures (a failed Graph API call is never shown as sent).
