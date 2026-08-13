# Bloom & Crumb — Turn conversations into customers and orders

A business operating system for small business owners who sell through Instagram, Facebook Messenger, TikTok, WhatsApp, email, and their website. Built as a Laravel + Inertia + Vue 3 + TypeScript + Tailwind app, seeded with a realistic demo business ("Bloom & Crumb", a custom cake and dessert box business).

## Stack

- Laravel 12, MySQL
- Inertia.js + Vue 3 + TypeScript
- Tailwind CSS v4
- Demo data only — no live social/email integrations yet (see "Future integrations" below)

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
- **Customer identity**: a `customer` can have several `customer_identities` (Instagram handle, email, phone, …) so identities can be merged later without a schema change.
- **Channels are structural, not just cosmetic**: every conversation/order carries a `channel_id`, so wiring up real Instagram/Messenger/TikTok/WhatsApp/email APIs later is additive — insert real webhook payloads into the same `conversations`/`messages` tables.
- **Orders are first-class**, decoupled from conversations — a conversation is just the originating channel, not the business record.

## Future integrations (intentionally not built yet)

Instagram/Messenger/TikTok/WhatsApp APIs, email sync, Stripe payments, quote generation, canned responses, AI summaries, attachments, and team management are all deferred — the schema and controllers are shaped so they can be added without a rewrite.
