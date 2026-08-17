# Production Launch

Internal reference for deploying Beležka to production and for every deploy
after that. Companion documents: [`docs/pre-launch-security.md`](./pre-launch-security.md)
(security checklist — read this too, it lists infrastructure items this
document doesn't repeat), [`docs/billing.md`](./billing.md) (Stripe setup
detail), [`docs/data-security.md`](./data-security.md) (encryption/backups
rationale), [`docs/legal-compliance.md`](./legal-compliance.md).

This document does not claim any of the infrastructure it describes (a
server, a backup job, a proxy) already exists — it specifies what a
deployment target must provide. Nothing here is a hosting-provider
recommendation; adapt the commands to whatever runs the app (systemd,
Supervisor, a PaaS process model, Kubernetes, etc).

## 1. Required environment variables

Full list with comments: [`.env.example`](../.env.example). The variables
below are the ones a *default-safe* local value would silently misconfigure
in production — get these right first, then run `php artisan deploy:check`
(see §11) to catch anything still missing.

| Variable | Production requirement |
|---|---|
| `APP_ENV` | `production` |
| `APP_DEBUG` | `false` — `true` leaks stack traces/config on error |
| `APP_URL` | real `https://` URL — drives asset URLs, forced HTTPS, mail EHLO |
| `APP_KEY` | generated (`php artisan key:generate`), stored in a secret manager |
| `APP_TIMEZONE` | leave `UTC` unless already running otherwise — do not change once real data exists (every stored datetime is reinterpreted against the new value) |
| `TRUSTED_PROXIES` | set if a reverse proxy/load balancer terminates TLS — see §2 |
| `DB_*` | production database credentials |
| `SESSION_SECURE_COOKIE` | `true` |
| `SESSION_SAME_SITE` | `lax` (default; only change if you know why) |
| `CACHE_STORE` / `QUEUE_CONNECTION` | non-`sync`/non-`array` (this app ships `database`, which is fine) |
| `MAIL_*` | a real transactional mail provider (password reset/verification emails only — no marketing mail is sent) |
| `STRIPE_KEY` / `STRIPE_SECRET` | **live** mode keys — see `docs/billing.md` |
| `STRIPE_WEBHOOK_SECRET` | live webhook signing secret — **required**; Cashier only attaches signature verification when this is set |
| `STRIPE_BELEZKA_MONTHLY_PRICE_ID` | live mode Price ID |
| `META_APP_ID` / `META_APP_SECRET` / `META_WEBHOOK_VERIFY_TOKEN` | only needed once a workspace connects Instagram/Messenger — set before advertising that feature |
| `REVERB_APP_ID` / `REVERB_APP_KEY` / `REVERB_APP_SECRET` + matching `VITE_REVERB_*` | required for realtime Inbox; app still works via the 30s poll fallback if absent, degraded |
| `REVERB_HOST` / `REVERB_SCHEME` | point at wherever Reverb is actually reachable, `https`/`wss` in production |
| `VAPID_SUBJECT` / `VAPID_PUBLIC_KEY` / `VAPID_PRIVATE_KEY` | required for follow-up push reminders; generate once with `php artisan webpush:vapid` and never rotate casually |
| `LEGAL_*` | see `docs/legal-compliance.md` and `php artisan legal:check` |

## 2. HTTPS, sessions, proxy

- `SESSION_SECURE_COOKIE=true` in production — without it, the session
  cookie is sent over plain HTTP too. Cookies are already `httpOnly`
  (Laravel default) and `SESSION_SAME_SITE=lax`.
- If a reverse proxy/load balancer sits in front of the app (it almost
  certainly does), set `TRUSTED_PROXIES` (see `.env.example`) — otherwise
  Laravel doesn't trust `X-Forwarded-*` and can generate `http://` URLs or
  misdetect the client IP even though the edge terminates TLS correctly.
  `SecurityHeaders` middleware only sends `Strict-Transport-Security` once
  `$request->secure()` is true, which depends on this being configured
  correctly.
- CSRF is exempted only for the two provider webhook endpoints
  (`bootstrap/app.php`): `webhooks/meta` and `stripe/webhook`. Both verify
  their own request authenticity independently (Meta: HMAC-SHA256 over the
  raw body using `META_APP_SECRET`; Stripe: Cashier's
  `VerifyWebhookSignature` using `STRIPE_WEBHOOK_SECRET`). No other route is
  CSRF-exempt.
- `SecurityHeaders` (`app/Http/Middleware/SecurityHeaders.php`) sends
  `X-Content-Type-Options`, `Referrer-Policy`, a minimal
  `Content-Security-Policy: frame-ancestors 'none'`, `Permissions-Policy`,
  and HSTS on secure requests. A full page-content CSP (`script-src` etc.)
  is deliberately not shipped — see `docs/pre-launch-security.md` — do not
  add one without dedicated testing against the Vite/Inertia/Reverb asset
  pipeline.

## 3. Required long-running processes

Three things must run continuously, in addition to the web server itself:

| Process | Command | If it's not running |
|---|---|---|
| Queue worker | `php artisan queue:work` | Meta webhook ingestion (`ProcessMetaWebhook`) and profile backfill (`FetchCustomerIdentityProfile`) never execute — inbound messages never appear, jobs queue up indefinitely |
| Scheduler | cron: `* * * * * php artisan schedule:run` | Follow-up reminders, demo cleanup, expired-export purge, and scheduled workspace deletion never fire |
| Reverb | `php artisan reverb:start` (behind TLS termination) | Realtime Inbox updates stop; the app still works via the existing 30s poll fallback in `Inbox/Index.vue`, just delayed — not a hard outage |

**Only one cron entry is needed**: `* * * * * php artisan schedule:run` —
Laravel's scheduler (`routes/console.php`) dispatches every registered
command (`app:send-due-follow-up-reminders` every minute,
`demos:cleanup` hourly, `workspaces:purge-expired` daily,
`exports:purge-expired` hourly) from that single cron trigger. Do not add
separate cron lines per command.

Everything queued (`ProcessMetaWebhook`, `FetchCustomerIdentityProfile`)
dispatches to the default queue with no named queue — a single
`queue:work` with no `--queue` flag covers all of it.

**Worker restart on deploy**: `queue:work` loads application code once at
boot and keeps it in memory — it will keep running the *previous* deploy's
code until restarted. Every deploy must run:

```
php artisan queue:restart
```

This signals workers to finish their current job and exit; your process
supervisor (systemd/Supervisor/etc) must be configured to restart them
automatically, or `queue:restart` just stops processing until someone
notices. `demo/data-security.md`'s encryption cutover runbook is the
sharpest example of why this matters (stale casts must never straddle a
data-format change) but it applies to every deploy, not just that one.

## 4. Queue failure safety

- `ProcessMetaWebhook`: `tries=3`, `backoff=[30, 120, 300]`, `timeout=60`.
  The raw webhook payload is encrypted before it's placed on the job
  property (`Crypt::encryptString`), so a `failed_jobs` row after repeated
  failure never holds plaintext message content or tokens. Ingestion is
  idempotent — `messages.external_message_id` is unique, so a retried/
  duplicate Meta delivery is a safe no-op, not a duplicate message.
- `FetchCustomerIdentityProfile`: `tries=2`, `backoff=[30]`, `timeout=30`.
  Best-effort profile enrichment; failure only logs (`channel_id` + error
  message, no tokens) and returns — never blocks message ingestion.
- Failures that exhaust retries land in `failed_jobs` (`config/queue.php`,
  `database-uuids` driver) — **these require operator attention**, nothing
  silently discards them. Retry with:
  ```
  php artisan queue:retry all      # or a specific --id=
  php artisan queue:failed         # list them
  ```
- No job logs a token, password, or full message body — verified by
  `tests/Feature/Security/QueuePayloadPrivacyTest.php` and the audits in
  `docs/data-security.md` Part 16-18.

## 5. Meta production path

OAuth connect → `channels` row created → subscribe the app to that Page's
webhook → Meta calls `POST /webhooks/meta` (HMAC-SHA256 signed) →
`MetaWebhookController` verifies the signature and payload shape → dispatches
`ProcessMetaWebhook` (queue) → `MessageIngestionService::ingest()` (dedup'd
by `external_message_id`) → `InboxMessageReceived` broadcast
(`ShouldBroadcastNow` — synchronous, not queue-dependent) → Inbox updates in
realtime (or via the 30s poll fallback) → an outbound reply goes through
`MetaMessagingProvider::sendMessage()`, with failures logged (`channel_id`,
HTTP status, Meta error code — never the token or full response body) and
surfaced to the sender as a failed-send result, not silently dropped.

**Webhook URL to register in the Meta App Dashboard**:
`https://<APP_URL>/webhooks/meta`, verify token = `META_WEBHOOK_VERIFY_TOKEN`.

Signature verification is unconditional — there is no environment-based
bypass. A misconfigured `META_APP_SECRET` causes real webhooks to be
rejected (a config-driven outage, logged as `meta.webhook.*` failures), not
a silently-accepted spoofed request.

## 6. Stripe production path

Registration → `/billing/activate` → server-created Checkout Session (price
ID from `config('billing.monthly_price_id')` only, never client input) →
Stripe-hosted Checkout → success redirect to `billing.activate.success`
(**does not grant access by itself** — it's a "processing" holding page) →
`customer.subscription.created`/`invoice.payment_succeeded` webhook lands →
local subscription state updates → `EnsureWorkspaceHasActiveSubscription`
unlocks the app. If the webhook is delayed, the success redirect just
bounces the user back to `/billing/activate` until it lands — access is
never granted from the browser redirect alone.

Webhook signature verification (Cashier's `VerifyWebhookSignature`) is only
attached when `STRIPE_WEBHOOK_SECRET` is set — **this must be set in every
environment that accepts real traffic**; `deploy:check` fails loudly on this
in production. Duplicate/retried events are deduplicated via the
`stripe_webhook_events` table's unique constraint on `stripe_event_id`
before any side effect runs.

Cancellation and deletion are one-way, separate systems (verified by
`tests/Feature/Billing/DeletionPurgeInteractionTest.php` and
`CancellationTest.php`): a Stripe subscription-deleted webhook only ever
updates local subscription status — it never deletes workspace data.
Workspace purge (`WorkspaceDeletionService::delete()`) explicitly
best-effort-cancels the Stripe subscription as a step before destroying the
workspace — never the reverse.

`SalesDocuments` (this SaaS customer's own invoices/proformas to *their*
end-customers) are architecturally unrelated to the Stripe subscription that
bills the SaaS customer for using Beležka — no shared model, no shared
webhook path. Do not conflate the two.

**Stripe Dashboard checklist and full TEST-mode walkthrough**: see
`docs/billing.md` §3.

**Webhook URL**: `https://<APP_URL>/stripe/webhook`.

## 7. Reverb / realtime

- `routes/channels.php` authorizes `workspace.{workspaceId}.inbox` against
  `(int) $user->current_workspace_id === (int) $workspaceId` — tenant-safe,
  covered by `tests/Feature/Security/BroadcastAuthorizationTest.php`.
- Frontend env: all of `VITE_REVERB_APP_KEY/HOST/PORT/SCHEME` must be set at
  **build time** (Vite bakes them into the compiled JS) — rebuilding after
  changing these is required, restarting the app server alone is not
  enough.
- `Inbox/Index.vue` already has a 30-second poll fallback
  (`usePoll(30000, {...})`) specifically so a dropped/blocked websocket
  connection degrades to "a little slower," not "broken."
- Reverb must sit behind TLS (`wss://` in production) — this repository
  does not terminate TLS itself; whatever reverse proxy sits in front of it
  (the same one handling `APP_URL`, or a separate one) must do so. Do not
  expose Reverb's plain `ws://` port directly to the internet in production.

## 8. Storage

| Category | Disk | Exposure |
|---|---|---|
| Message/inbox attachments | `local` (private) | Authenticated controller only (`inbox.attachments.show`, admin support equivalent) — never a public URL |
| Invoice/proforma/storno PDFs | `local` (private) | Authenticated download/send controllers only |
| External/uploaded invoice PDFs | `local` (private), workspace-scoped path | Authenticated only |
| Workspace data exports | `local` (private) | Authenticated, owner-only download controller |
| Invoice logo | `public` | Intentionally public (embedded in generated PDFs) — reachable at `/storage/invoice-logos/...` |
| User avatar | `public` | Intentionally public — reachable at `/storage/avatars/...` |

The `public` disk requires the `storage:link` symlink
(`public/storage` → `storage/app/public`) — run
`php artisan storage:link` as part of first deploy; it is not created
automatically and avatar/logo URLs 404 without it.

**Must persist across deployments** (do not let these live only inside an
ephemeral container filesystem): `storage/app/private`, `storage/app/public`,
and `storage/logs` if logs aren't already shipped elsewhere. A deploy that
wipes `storage/app` deletes every attachment, PDF, export, avatar, and logo
that hasn't been backed up.

Nothing private is ever served through a raw/public storage URL — only the
two categories above (logo, avatar) use the `public` disk, and both are
deliberately meant to be public.

## 9. Backups / restore

No backup mechanism is implemented in this repository — this section is a
requirement for the infrastructure this app deploys to, not a description of
something already configured here.

A production deployment must back up:
- the database (full logical or snapshot backup, on a defined schedule)
- persistent private storage (`storage/app/private` — attachments, PDFs,
  exports)
- required public uploaded assets (`storage/app/public` — logos, avatars)

Backups must be encrypted at rest and in transit, access-restricted, and on
a defined retention policy. **A backup that has never been restored is not
a verified backup** — schedule a periodic restore test (e.g. quarterly:
restore into a scratch environment, run `deploy:check` and a login smoke
test against it) and treat an untested backup as equivalent to no backup
when assessing launch readiness.

## 10. Logging / monitoring

Important failures are already structured and observable without leaking
secrets:

- Stripe webhook signature failure / processing error → Cashier + this
  app's `AuditLog` rows (`billing.*` events)
- Meta webhook signature/shape failure → `Log::` with reason strings only
  (`verify_failed`, `invalid_signature`, `malformed_payload`) — no token or
  payload value
- Meta ingestion failure → `meta.webhook.ingest_failed` (channel/message
  IDs + exception message, never body text)
- Outbound Meta send failure → `meta.send_message.failed` (status + Meta
  error code, never the full response)
- Queue job failure → lands in `failed_jobs`, requires the operator
  workflow in §4 — nothing is silently swallowed
- Scheduled cleanup command failure → surfaces as a non-zero cron exit;
  wire your cron/process supervisor to alert on that (not built into this
  repo)
- PDF/invoice generation failure → propagates as an exception to the
  request (visible in logs / error tracking, not swallowed)

Never logged, anywhere in `app/` (re-audited across two milestones — see
`docs/data-security.md` Part 16-18): access tokens, `APP_KEY`, passwords,
message bodies/notes, or full webhook payloads.

An optional Slack channel for `critical`-and-above logs is already wired
(`config/logging.php`'s `slack` channel) — set `LOG_SLACK_WEBHOOK_URL` in
production if you want failures surfaced somewhere a human will see them.
No error-tracking SaaS (Sentry/Bugsnag/etc.) is integrated; if one is added,
it must be configured to scrub the same fields this app already avoids
logging — verify its default request-body capture doesn't defeat that.

## 11. Health checks

- **Public**: `GET /up` (Laravel's built-in health route, registered in
  `bootstrap/app.php`). Cheap — it only fires an internal event and returns
  200, no DB/queue query. Safe to point a public load balancer's health
  check at as-is.
- **Internal, operator-run**: `php artisan deploy:check`
  (`app/Console/Commands/CheckDeploymentReadiness.php`). Verifies DB
  connectivity, and — only when `APP_ENV=production` — `APP_DEBUG`,
  `APP_URL` scheme, `SESSION_SECURE_COOKIE`, queue connection sanity,
  required Stripe config (fails if missing), Reverb config (fails if
  missing while `BROADCAST_CONNECTION=reverb`), and warns (non-blocking) on
  missing Meta/VAPID config. Never prints a secret's value, only whether
  it's present. Exit code is non-zero on any blocking failure — wire it into
  your deploy pipeline (§12) rather than the public health endpoint; it's
  not meant for a load balancer to poll.

## 12. Database deployment safety

- **Never** run `php artisan migrate:fresh` against a production database —
  it drops every table. Migrations are additive/forward-only in normal
  operation.
- Standard deploy migration command:
  ```
  php artisan migrate --force
  ```
  (`--force` is required outside local/testing environments — Laravel
  refuses to run migrations in production without it, by design.)
- **Back up the database before any migration flagged as destructive** in
  its own docblock. Only one exists in this codebase's history
  (`2026_08_15_*` encryption-cutover migrations) and it has its own
  dedicated runbook — see `docs/pre-launch-security.md` "Production
  encryption cutover runbook." Do not improvise a different order for that
  one; every other migration in `database/migrations/` is a normal
  additive change.
- **Cache/config rebuild order** (after `migrate --force`, before serving
  new traffic):
  ```
  php artisan config:cache
  php artisan route:cache
  php artisan view:cache
  ```
  If any of these were cached from the *previous* deploy, clear first
  (`config:clear` etc.) — a stale cached config is a classic source of
  "works in code review, broken in prod."
- **Restart the queue worker after every deploy** — see §3. This is easy to
  forget and produces confusing bugs (old code still processing new jobs).

## 13. CI

`.github/workflows/ci.yml` runs on every push/PR: the PHP test suite (Pest,
via `php artisan test` — `phpunit.xml` already points it at an in-memory
SQLite DB, sync queue, array cache/session/mail, and fake Stripe test keys,
so no real credentials or external services are needed), `vendor/bin/pint
--test` for code style, and the frontend build (`npm run build`, which runs
`vue-tsc` then `vite build`). No Stripe/Meta live credentials are used or
required in CI.

## 14. Deploy sequence

### FIRST DEPLOY checklist

1. Provision infrastructure: app server(s), database, persistent storage
   volume for `storage/app/{private,public}` (§8), reverse proxy with TLS
   (§2), and something to run the scheduler cron + queue worker + Reverb
   continuously (§3).
2. Set every environment variable in §1 (real production values, secrets
   from a secret manager — see `docs/pre-launch-security.md`'s
   infrastructure checklist for `APP_KEY` handling specifically).
3. `composer install --no-dev --optimize-autoloader`
4. `npm ci && npm run build`
5. `php artisan key:generate` (only if `APP_KEY` isn't already provisioned
   via the secret manager — do not regenerate a key that's already backing
   live encrypted data)
6. `php artisan migrate --force`
7. `php artisan storage:link`
8. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
9. Start/enable the long-running processes (§3): web server, queue worker
   (under a supervisor that restarts it), `* * * * * php artisan
   schedule:run` in cron, Reverb.
10. `php artisan deploy:check` — must exit 0 before going further.
11. `php artisan legal:check` — must exit 0 before going further.
12. Configure the Meta webhook (§5) and Stripe webhook (§6) endpoints in
    their respective dashboards, using this deployment's real URL.
13. Run the smoke test plan (§15) end to end, with Stripe in **test** mode
    first.
14. Only after a clean smoke test, switch `STRIPE_KEY`/`STRIPE_SECRET`/
    `STRIPE_WEBHOOK_SECRET`/`STRIPE_BELEZKA_MONTHLY_PRICE_ID` to live mode
    and repeat the billing portion of the smoke test once for real.
15. Confirm backups are actually running (§9) before treating this as
    launched.

### NORMAL DEPLOY checklist

1. CI green on the commit being deployed (§13).
2. `composer install --no-dev --optimize-autoloader`
3. `npm ci && npm run build`
4. `php artisan down` (only if the deploy includes a migration flagged
   destructive — see §12; a normal additive migration does not need
   downtime)
5. `php artisan migrate --force`
6. `php artisan config:cache && php artisan route:cache && php artisan view:cache`
7. `php artisan queue:restart`
8. `php artisan up` (if step 4 was taken)
9. `php artisan deploy:check`
10. Spot-check `GET /up` returns 200 and do a quick manual pass of Today /
    Inbox / an existing Order or Appointment.

## 15. Final smoke test plan (~10-15 minutes)

Run against a real deployment, Stripe in **test** mode first. Do not mark
any provider step "passed" without actually exercising it — if Meta/Stripe
test credentials aren't available in this environment, say so explicitly
rather than assuming success.

1. Register a fresh user (`/register`) — new workspace created, redirected
   to `/billing/activate`.
2. Complete Stripe **test-mode** Checkout (`4242 4242 4242 4242`) — confirm
   the webhook lands and the app unlocks (no bounce back to
   `/billing/activate`).
3. Complete onboarding.
4. Create a product or service in the catalog.
5. Connect a **test** Meta account/channel (Instagram or Messenger) — if no
   test Meta app/account is available in this environment, note that
   explicitly instead of skipping silently.
6. Send a DM to the connected test account from another account; confirm it
   appears in the Inbox (realtime, or within 30s via the poll fallback).
7. Send a reply from the Inbox; confirm it's delivered.
8. Create a Customer.
9. Create an Order (or Appointment, depending on the workspace's mode).
10. Add a follow-up reminder on that Order/Appointment/Customer.
11. Issue an invoice or proforma for it.
12. Download the generated PDF — confirm it opens and content is correct.
13. Open Today — confirm the new order/appointment/follow-up all appear
    correctly.
14. Verify realtime: open Inbox in two tabs, send a message in one, confirm
    it appears in the other without a manual refresh; then simulate a
    dropped websocket (e.g. block the Reverb port) and confirm the 30s poll
    fallback still delivers the update.
15. Open Settings → Naročnina → "Upravljaj naročnino" and confirm the
    Stripe Customer Portal opens correctly.
