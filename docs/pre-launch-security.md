# Pre-Launch Security Checklist

Internal reference. Distinguishes work verifiably done **in this
repository's code** from work that can only be verified/completed in
**production infrastructure** — do not mark an infrastructure item complete
without independently verifying it against the actual production
environment.

Related: `docs/admin-security.md` (platform admin / support access),
`docs/data-security.md` (encryption inventory),
`docs/encryption-key-runbook.md`.

## Production encryption cutover runbook

Covers deploying the encrypted casts introduced in
`docs/data-security.md` (`Message.body`, `Conversation.last_message_preview`,
`Customer.notes`, `Order.*`, `Appointment.*`, `OrderNote.body`,
`FollowUp.note`, `Message.metadata`) to a production database that still has
plaintext data. There are currently no meaningful production customers, so a
short maintenance window is acceptable and this runbook keeps the procedure
simple rather than building a zero-downtime dual-read/dual-write system.

**The problem this avoids**: the model casts (`'encrypted'`) and the
migration command (`security:encrypt-sensitive-data`) are released together.
If application code with the casts already deployed starts serving traffic
against a database that still has plaintext rows, every read of an
unmigrated row throws `DecryptException`. The order below guarantees the
backfill always completes *before* any code that expects ciphertext runs.

**1. Back up the database before touching anything.** This is the actual
rollback mechanism — see "Rollback / recovery" below; there is deliberately
no automated down-migration for the ciphertext-affecting schema change.

```
# example, adapt to your actual DB host/tooling:
mysqldump --single-transaction -h $DB_HOST -u $DB_USERNAME -p $DB_DATABASE > pre-encryption-backup.sql
```

Confirm the backup file is non-trivial in size and store it somewhere
access-controlled (not the app server's local disk only).

**2. Put the app in maintenance mode** (stop accepting new writes to the
tables being migrated):

```
php artisan down
```

**3. Deploy the schema-compatible migration** (widens `follow_ups.note` to
`text`, converts `messages.metadata` from `json` to `text` — the migration
that also adds the `channels` global-uniqueness constraint from this fix
pass can run in the same step, it's unrelated to encryption and always
safe/reversible):

```
php artisan migrate --force
```

At this point the schema can hold ciphertext, but every row is still
plaintext and the application's model casts are **not yet deployed** — old
code is still running, so nothing attempts to decrypt anything yet.

**4. Dry run the backfill** and read the counts before writing anything:

```
php artisan security:encrypt-sensitive-data --dry-run
```

Confirm `errors=0`. Every row should show up as `encrypted` (first run) —
`already_encrypted`/`normalized_empty` should be 0 on a true first run.

**5. Run the backfill for real:**

```
php artisan security:encrypt-sensitive-data
```

If it's interrupted (deploy timeout, DB blip), it is safe to just run it
again — it's idempotent and resumes correctly (see
`docs/data-security.md` Part 6).

**6. Verify** before deploying the code that adds the casts:

```
php artisan security:encrypt-sensitive-data --dry-run
```

Every row should now report `already_encrypted` and `errors=0`. If
`errors` is non-zero, **stop** — do not proceed to step 7 until every row
is accounted for (investigate the specific row ids logged by the command).

**7. Deploy the application code with the `'encrypted'` / `'encrypted:array'`
casts live** (i.e. the release containing this milestone's model changes),
and restart the queue workers along with the app (queued jobs — e.g.
`ProcessMetaWebhook` — run the same model code and must not straddle the
cutover with stale casts):

```
php artisan queue:restart
```

**8. Bring the app back up:**

```
php artisan up
```

**9. Verify the running application**, not just the database:

```
php artisan tinker
>>> \App\Models\Message::latest()->first()->body
>>> \App\Models\Customer::whereNotNull('notes')->first()->notes
```

Both should return readable plaintext. Spot-check the actual Inbox/Customers
UI as a real authenticated user, not only tinker.

## Rollback / recovery procedure

There is **no automated rollback** for the schema migration once any row has
been encrypted — `messages.metadata`'s `down()` deliberately throws instead
of attempting to convert ciphertext-containing `text` back to a native
`json` column (which would fail or corrupt data), and shrinking
`follow_ups.note` back to `varchar(255)` would silently truncate ciphertext.
This is intentional (see PART 3 of the original task: choose either an
explicitly irreversible migration with a documented restore procedure, or a
safe decrypt/backout command — the irreversible-migration option was chosen
as the simplest production-safe approach given there are no real customers
yet to justify the complexity of a backout command).

**If something goes wrong before step 7 (casts not yet deployed)**: the
backfill command is safe to re-run; there is no need to roll back at all —
diagnose the `errors` count and fix the underlying row(s), or restore from
the step-1 backup if the database itself is suspect.

**If something goes wrong after step 7 (casts live, reads failing)**:
restore the database from the step-1 backup, redeploy the pre-cutover
application code (casts removed), bring the app back up, and restart the
cutover from step 1 once the root cause is understood. Do not attempt
`php artisan migrate:rollback` against a database that has any encrypted
row — the migration's `down()` will refuse to run for exactly this reason.

## CODE — implemented and tested in this repository

- [x] Private conversation/note content application-encrypted at rest
      (`Message.body`, `Conversation.last_message_preview`, `Customer.notes`,
      `Order.*`, `Appointment.*`, `OrderNote.body`, `FollowUp.note`,
      `Message.metadata`) — see `docs/data-security.md`
- [x] Safe, idempotent, chunked migration of existing plaintext to
      ciphertext (`php artisan security:encrypt-sensitive-data`)
- [x] Integration/Channel access & refresh tokens encrypted (pre-existing,
      preserved, never downgraded)
- [x] Private message attachments moved off the public disk; served only
      through authorized, workspace-checked routes
      (`inbox.attachments.show`, admin support-content equivalent)
- [x] Demo-workspace cleanup deletes demo-owned attachment files, not just
      DB rows
- [x] Queue payload privacy: `ProcessMetaWebhook`'s raw webhook payload is
      encrypted before being placed on the queue (protects `failed_jobs`)
- [x] Push notification content minimized (`FollowUpDue` no longer includes
      note text in the OS notification body)
- [x] Log redaction audited (two full passes across two milestones): no
      message bodies, notes, tokens, or full payloads are intentionally
      logged anywhere in `app/`
- [x] Tenant isolation hardened: `BelongsToWorkspace` default-denies when no
      workspace context is resolvable (fixed a real cross-tenant leak — see
      `docs/admin-security.md` §13)
- [x] Broadcast channel authorization verified with tests (cross-workspace
      subscription is rejected)
- [x] Platform-admin support-access model: explicit, time-boxed,
      owner-granted, server-side-enforced, fully audited — see
      `docs/admin-security.md`
- [x] Admin audit logging (append-only `audit_logs` table, identifiers only
      in metadata, never message content)
- [x] Baseline security response headers
      (`X-Content-Type-Options`, `Referrer-Policy`,
      `Content-Security-Policy: frame-ancestors 'none'`, `Permissions-Policy`)
- [x] File upload validation (size, real-content MIME check via Laravel's
      `mimes` rule, random non-guessable storage filenames)
- [x] Meta webhook tenant routing hardened: `channels.(type, external_account_id)`
      is globally unique, connecting an already-claimed account is rejected,
      and inbound-webhook channel lookup refuses to guess on ambiguity — see
      `docs/admin-security.md` "Security fix pass"
- [x] Legacy empty-string encryption gap fixed in
      `security:encrypt-sensitive-data` — see `docs/data-security.md` Part 6
- [x] Admin aggregate-query tenant-scope bug fixed (dashboard integration
      counts and support-browser customer/channel eager-loads) — see
      `docs/admin-security.md` "Security fix pass"
- [x] Read-only support content browser
      (`/admin/workspaces/{workspace}/support`), Appointment support detail
      page added, demo deletion centralized into one service used by both
      the scheduled cleanup and manual admin deletion, support sessions
      bound to the requesting admin — see `docs/admin-security.md`
      "Security fix pass"

## CODE — explicitly deferred, with reasoning

- [ ] **Full page-content Content-Security-Policy** (`script-src`,
      `style-src`, etc.) — deliberately not shipped this milestone; risks
      breaking the Vite/Inertia asset pipeline or a future Meta
      embed/widget without dedicated testing. Scope as its own task.
- [ ] **Per-workspace encryption keys / KMS / envelope encryption** —
      documented future design in `docs/data-security.md`; not implemented,
      per explicit instruction not to over-engineer V1.
- [ ] **Blind-index / searchable encryption** for identifier fields
      (`Customer.full_name/email/phone`, etc.) — these remain plaintext by
      deliberate V1 decision (see `docs/data-security.md` "Why identifiers
      stay queryable"). Revisit only alongside the KMS work above.
- [ ] **Break-glass admin access** — deliberately not built; current
      behavior is "no owner-granted access ⇒ no content access," full stop,
      even for platform admins. See `docs/admin-security.md` PART 14.

## CODE — carried over from the previous milestone, still open

- [ ] **MFA for platform admins** — not implemented. See
      `docs/admin-security.md` §1/§20. Recommended: TOTP via Laravel
      Fortify or `pragmarx/google2fa`, scoped to `is_platform_admin` users
      only.
- [ ] Rate limiting specifically on `/admin`-gated mutating actions (beyond
      the existing login throttle) — low priority given MFA is the larger
      gap, worth bundling together.

## CODE — billing (Stripe/Cashier)

- [x] **Workspace is the billed entity, never User** — Cashier's `Billable`
      trait lives only on `App\Models\Workspace`, configured via
      `Cashier::useCustomerModel(Workspace::class)`. See `docs/billing.md`.
- [x] **Subscription state is never inferred ad hoc** — every consumer
      (gating middleware, shared Inertia prop, Settings/Billing, Admin
      view) goes through `App\Services\Billing\WorkspaceSubscriptionStateService`.
      Confirmed by `tests/Feature/Billing/AccessGatingTest.php`.
- [x] **Checkout price ID is always server-chosen** — read only from
      `config('billing.monthly_price_id')`, never from client input.
      Confirmed by `tests/Feature/Billing/CheckoutTest.php`.
- [x] **Webhook signatures verified** via Cashier's own
      `VerifyWebhookSignature` middleware; a duplicate Stripe event never
      duplicates a custom side effect (`stripe_webhook_events` unique
      constraint). Confirmed by `tests/Feature/Webhooks/StripeWebhookTest.php`.
- [x] **Demo workspaces never touch Stripe** — no `stripe_id`, no
      subscription row, no checkout route reachable. Confirmed by
      `tests/Feature/Billing/DataExposureTest.php` and `AccessGatingTest.php`.
- [x] **Cancellation and deletion remain separate, directional systems** —
      a Stripe cancellation webhook only ever updates local subscription
      status; workspace purge cancels Stripe (never the reverse). Confirmed
      by `tests/Feature/Billing/DeletionPurgeInteractionTest.php` and
      `CancellationTest.php`.
- [x] **No Stripe secret or card detail ever reaches an Inertia response or
      application log.** Confirmed by `tests/Feature/Billing/DataExposureTest.php`.

## INFRASTRUCTURE — must be verified in the actual production environment

None of the following can be confirmed from this repository's code — they
depend on hosting/deployment choices made outside this codebase. **Do not
check these off without verifying against the real production
environment.**

- [ ] **HTTPS enforced in production** (`APP_URL` uses `https://`,
      `SESSION_SECURE_COOKIE=true`, and the actual TLS termination — load
      balancer/reverse proxy/app server — is configured correctly; verify
      no redirect loop behind whatever proxy is used)
- [ ] **`APP_KEY` backup & access control** — stored in a secret manager
      with its own durability guarantee, access restricted to
      deployment/infra tooling only. See `docs/encryption-key-runbook.md`.
- [ ] **Database volume encryption at rest** (provider/infra-level — e.g.
      RDS/Cloud SQL encrypted storage, or disk-level encryption if
      self-hosted)
- [ ] **Object/file storage encryption at rest** for the `local` private
      disk's underlying volume (or, if migrated to S3-compatible storage
      per `config/filesystems.php`'s existing `s3` disk config,
      server-side bucket encryption)
- [ ] **Encrypted, access-controlled backups** — no backup mechanism exists
      in this repository to audit (see `docs/data-security.md` Part 24).
      Whatever backup solution is configured must provide: encryption at
      rest, encryption in transit, restricted access, a defined retention
      policy, a periodically-tested restore procedure, and confirmation
      backups are never publicly exposed (no open S3 bucket, etc.)
- [ ] **Restricted production database access** (no broad developer direct
      access; access via bastion/VPN/audited tooling only)
- [ ] **Secret manager / environment protection** for all `.env` values,
      not just `APP_KEY` (Meta app secret, DB credentials, mail credentials)
- [ ] **Restore test performed** — a backup that has never been restored is
      not a verified backup
- [ ] **Monitoring/alerting** in place for the application and its queue
      workers (a stuck/failing queue worker means webhook processing and
      encrypted-payload jobs silently pile up)
- [ ] **Production error tracking scrubbed** — if a third-party error
      tracker (Sentry, Bugsnag, etc.) is added, it must be configured to
      scrub the same fields this codebase already avoids logging
      (message bodies, notes, tokens, `APP_KEY`) — verify its default
      request-body capture doesn't defeat this codebase's log-redaction
      work
- [ ] **Admin MFA enforced at the identity layer** if implemented outside
      this codebase (e.g. an SSO/IdP in front of platform-admin accounts)
      instead of the in-app TOTP option above
- [ ] **Reverb/websocket transport secured** (`wss://` in production, not
      `ws://`) for the realtime broadcast channels this milestone verified
      the *authorization* of
- [ ] **Live Stripe API keys** (`STRIPE_KEY`/`STRIPE_SECRET`) configured in
      the production secret manager, not test-mode keys. See `docs/billing.md`.
- [ ] **Live-mode Product/Price created** in the Stripe Dashboard and
      `STRIPE_BELEZKA_MONTHLY_PRICE_ID` updated to the live price ID.
- [ ] **Live webhook endpoint configured** (correct URL, subscribed to the
      exact event types `docs/billing.md` lists) with its signing secret
      set as `STRIPE_WEBHOOK_SECRET`.
- [ ] **Stripe Customer Portal reviewed in live mode** — no plan switching
      to a nonexistent plan, no coupons, no pause; cancellation behavior
      matches the finalized owner decision (see NEEDS OWNER INPUT below).
- [ ] **Billing email/address collection reviewed** against actual
      invoicing needs (this milestone does not build Slovenian fiscal
      invoicing — see `docs/billing.md` Part on FURS/invoicing).
- [ ] **Payment-failure recovery emails** (Stripe's own dunning emails)
      reviewed for tone/branding before going live.
- [ ] **`Terms.vue` §8 billing copy finalized** and republished with a
      bumped `LEGAL_TERMS_VERSION` — it currently still says the service is
      free with no payment system, which becomes false the moment billing
      ships.
- [ ] **Final price/VAT display decision made** — `BILLING_DISPLAY_PRICE`
      and `config/legal.php`'s VAT fields set accordingly.
- [ ] **Slovenian fiscal invoicing (FURS/ZOI/EOR) milestone completed** —
      Stripe's own receipt/invoice is explicitly not a substitute; see
      `docs/billing.md`.

## Final standard this milestone achieves (code-verifiable)

A raw database dump alone does not reveal customer message bodies,
conversation previews, customer notes, or order/appointment/follow-up
private notes — confirmed by
`tests/Feature/Security/EncryptedFieldsTest.php`, which writes known
plaintext through each model and asserts the raw DB column does not contain
it. Integration tokens remain encrypted (unchanged, verified). Private
attachments are not publicly reachable and cross-workspace access is
impossible — confirmed by
`tests/Feature/Attachments/AttachmentAuthorizationTest.php`. No E2EE claim
exists anywhere in this codebase or its documentation.
