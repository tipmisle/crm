# Data Security: Sensitive Data Inventory & Encryption

Internal reference. Not for external/marketing use — see "No false security
claims" at the end of this document.

Companion documents: [`docs/admin-security.md`](./admin-security.md) (platform
admin / support-access architecture), [`docs/encryption-key-runbook.md`](./encryption-key-runbook.md),
[`docs/pre-launch-security.md`](./pre-launch-security.md).

## Standard achieved

If someone obtains **only** a raw database dump, they cannot read customer
message bodies, conversation previews, customer notes, order/appointment
private notes, reminder/follow-up notes, or order note bodies, without the
application's encryption secret (`APP_KEY`). Integration access/refresh
tokens were already protected this way before this milestone and remain so.

This is **server-side application encryption**, not end-to-end encryption.
Beležka's own server decrypts data to serve authorized application requests.
Platform support has no *normal* access to that decrypted content — see
`docs/admin-security.md` for the support-access model that governs the
exception. Do not describe this as E2EE anywhere, including internally.

## Data classification

- **A — Secrets**: tokens, credentials.
- **B — Private content**: messages, notes, descriptions, attachment
  content/paths.
- **C — Personal identifiers**: name, email, phone, social username/external
  IDs.
- **D — Business/operational**: status, dates, price, IDs, counts.

## Sensitive-field inventory

| Field | Type | Class | Storage | Queried? | Encrypted? | Reason |
|---|---|---|---|---|---|---|
| `integrations.access_token` | text | A | `encrypted` cast (pre-existing) | no | ✅ (unchanged) | OAuth credential |
| `integrations.refresh_token` | text | A | `encrypted` cast (pre-existing) | no | ✅ (unchanged) | OAuth credential |
| `channels.access_token` | text | A | `encrypted` cast (pre-existing) | no | ✅ (unchanged) | Page access token |
| `messages.body` | text | B | `encrypted` cast (this milestone) | no | ✅ | Raw conversation content |
| `messages.metadata` | text (was `json`) | B | `encrypted:array` cast (this milestone) | no | ✅ | Holds attachment path/URL — see Part 3 findings below |
| `conversations.last_message_preview` | text | B | `encrypted` cast (this milestone) | no (display only) | ✅ | Snippet of message content |
| `customers.notes` | text | B | `encrypted` cast (this milestone) | no | ✅ | Free text; may contain health/other sensitive disclosures |
| `orders.description` | text | B | `encrypted` cast (this milestone) | no | ✅ | Order detail free text |
| `orders.internal_notes` | text | B | `encrypted` cast (this milestone) | no | ✅ | Staff-only free text |
| `orders.customer_notes` | text | B | `encrypted` cast (this milestone) | no | ✅ | Customer-facing free text |
| `appointments.description` | text | B | `encrypted` cast (this milestone) | no | ✅ | Appointment detail free text |
| `appointments.internal_notes` | text | B | `encrypted` cast (this milestone) | no | ✅ | Staff-only free text; may include health info |
| `appointments.customer_notes` | text | B | `encrypted` cast (this milestone) | no | ✅ | Customer-facing free text |
| `order_notes.body` | text | B | `encrypted` cast (this milestone) | no | ✅ | Free-form order note thread |
| `follow_ups.note` | text (was `varchar(255)`) | B | `encrypted` cast (this milestone) | no | ✅ | Free text; widened before encrypting — see Part 6/7 |
| `bug_reports.subject` | text (was `varchar(255)`) | B | `encrypted` cast | no (only `status` is filtered) | ✅ | Widened before encrypting; not SQL-searched, so no reason to leave it plain |
| `bug_reports.message` | text | B | `encrypted` cast | no | ✅ | Free text a user writes describing a bug — can name a customer/order |
| `feature_requests.subject` | text (was `varchar(255)`) | B | `encrypted` cast | no (only `status` is filtered) | ✅ | Same as `bug_reports.subject` |
| `feature_requests.message` | text | B | `encrypted` cast | no | ✅ | Same as `bug_reports.message` |
| `sales_documents.cancellation_reason` | text | B | `encrypted` cast | no | ✅ | Free text a staff member types when storno-ing an invoice; can name the customer/dispute |
| `customers.full_name` | string | C | plaintext | ✅ `LIKE`, `orderBy`, dedup display | ❌ | Search-critical identifier — see "Why identifiers stay queryable" |
| `customers.email` | string | C | plaintext | ✅ `LIKE` (search) | ❌ | Search-critical identifier |
| `customers.phone` | string | C | plaintext | ✅ `LIKE` (search) | ❌ | Search-critical identifier |
| `conversations.customer_display_name` | string | C | plaintext | ✅ `LIKE` (search) | ❌ | Search-critical identifier |
| `conversations.customer_username` | string | C | plaintext | ✅ `LIKE` (search) | ❌ | Search-critical identifier |
| `customer_identities.external_id` | string | C | plaintext | ✅ exact-match dedup/matching | ❌ | Must remain equality-queryable for Meta identity resolution |
| `customer_identities.username` | string | C | plaintext | display only | ❌ | Low sensitivity; paired with `external_id` above |
| `customer_identities.metadata` | json | C | plaintext | no (bulk-fetched, filtered in PHP) | ❌ | Only holds `avatar_url`; not "private content" tier |
| `orders.title` | string | D | plaintext | ✅ `LIKE` (search) | ❌ | Short label, not treated as private free text |
| `products.description` / `services.description` | text | D | plaintext | no | ❌ | Business-authored catalog copy shown to customers by design |
| `activity_logs.description` | string | D/C | plaintext | no | ❌ | Interpolates `full_name`/order numbers only — see Part 4 findings |
| `activity_logs.metadata` | json | D | plaintext | no | ❌ | Always empty in current call sites |
| `bug_reports.page_url` | string | D | plaintext | no | ❌ | Server-derived from the Referer header path only — no query string/fragment, so it can't carry a search term or email a user typed elsewhere. See `BugReportController::normalizedPagePath()` |
| `audit_logs.metadata` | json | D | plaintext | no | ❌ | Deliberately identifiers-only — see `docs/admin-security.md` |

### Why identifiers stay queryable (Part 5)

`Customer.full_name/email/phone`, `Conversation.customer_display_name/username`,
and `CustomerIdentity.external_id/username` are used in:

- `SearchController` (`LIKE` search across customers/orders/appointments/conversations)
- `CustomerController::index` search
- `OrderController`/`AppointmentController` search (`orWhereHas('customer', ...)`)
- `MessageIngestionService::resolveIdentity` (equality match on `external_id`
  to deduplicate the same Instagram/Messenger sender across messages)

Laravel's `encrypted` cast produces non-deterministic ciphertext (a random IV
per encryption), so `LIKE`/equality queries against an encrypted column
return nothing useful — encrypting these fields in place would silently break
search and Meta identity deduplication. Verified by grepping every
controller's `where`/`orderBy`/`orWhereHas` call against each field before
deciding what to encrypt (see git history for this milestone).

**Decision for V1**: these identifiers stay plaintext, protected instead by
tenant isolation (`BelongsToWorkspace`, hardened this milestone — see
`docs/admin-security.md`), strict platform-admin access controls, and
infrastructure-level protection (DB access restriction, disk/backup
encryption — see `docs/pre-launch-security.md`). This is a deliberate,
documented trade-off, not an oversight. A real "searchable encryption"
design (blind indexes, deterministic-encryption columns for exact-match
lookups) is future work — see "Future: per-workspace keys / KMS" below. No
homemade searchable-encryption scheme was implemented.

## Part 3 — Message.metadata findings

Audited every producer of `Message.metadata`:

- **Inbound (Meta webhook)**: `MetaMessagingProvider::normalizeWebhookPayload`
  builds `NormalizedIncomingMessage` with a `rawMetadata` field set to the
  *entire* raw Meta `messaging` event. **This field is never persisted** —
  `MessageIngestionService::ingest` only stores
  `['attachments' => $normalized->attachments]`, where each attachment is
  reduced to `{type, source: 'external', url}`. No raw webhook payload,
  caption, or sender data is stored. `rawMetadata` is effectively dead data
  today; left in the DTO in case future normalization needs it, but nothing
  reads it — flagged here so it doesn't quietly grow into a leak later.
- **Outbound (Beležka user upload)**: `ConversationController::sendSingle`
  stores `['attachments' => [$attachment->toArray()]]`, where
  `OutboundAttachment::toArray()` returns `{type, source: 'local', path}` —
  a private-disk relative path, not a URL (see Part 11/12 below).

**Conclusion**: metadata was already minimal (no raw webhook payload ever
stored) — no further data-minimization change was needed beyond what
already existed. It is still encrypted (`encrypted:array` cast) because it
contains a private-disk file path (Part B — indirectly grants access to
private image/file content) and, for external attachments, a Meta CDN URL
that may itself grant temporary content access if leaked from a DB dump.

## Part 4 — ActivityLog findings

Audited every `ActivityLog::record()` call site
(`AppointmentController`, `OrderController`, `CustomerController`,
`Inbox\ConversationController`, `Integrations\MetaIntegrationController`).
All `description` strings interpolate only `full_name` (an already-plaintext
identifier, tier C) and business identifiers (order/appointment number,
status label, channel display name) — e.g. `"Naročilo BC-0004 označeno kot
dokončano"`. **No message body, note content, or health information is
embedded in any activity log description.** `metadata` is passed as `[]` in
every current call site.

**Conclusion**: no sanitization of existing `activity_logs` rows was
necessary — there was nothing sensitive to sanitize. No schema/cast change
was made to `ActivityLog`; it remains a workspace-facing, user-editable
activity feed (distinct from the admin-only, append-only `audit_logs` table
introduced in the previous milestone).

## Part 6 — Safe existing-data migration strategy

**Never** simply add an `encrypted` cast to a plaintext column — Laravel
would attempt to decrypt existing plaintext and throw. The strategy used:

1. **Schema prerequisite migration**
   (`2026_08_15_211331_widen_follow_ups_note_and_messages_metadata_columns.php`):
   widened `follow_ups.note` from `varchar(255)` to `text` (ciphertext is
   larger than plaintext — see Part 7), and converted `messages.metadata`
   from native MySQL `json` to `text` (a `json`-typed column rejects
   ciphertext, which is a plain string, not valid JSON).
2. **Backfill command** (`php artisan security:encrypt-sensitive-data`,
   `App\Console\Commands\EncryptSensitiveData`): operates on raw DB rows via
   `Illuminate\Support\Facades\DB` — **not** Eloquent models — so it can run
   safely *before* any model cast changes. For every target column, it:
   - iterates with `DB::table(...)->orderBy('id')->chunkById($size, ...)` —
     bounded memory regardless of table size;
   - for each non-null value, first attempts `Crypt::decryptString()`
     (`json_decode` afterwards for the JSON-shaped `messages.metadata`
     column). Success ⇒ already encrypted ⇒ skipped. This makes the command
     **idempotent and safely restartable** — interrupting it mid-run and
     re-running is a correctness no-op, and it can also be re-run safely
     after deploy if any row was written by old code in the meantime;
   - otherwise encrypts with `Crypt::encryptString()` and writes back via
     `DB::table(...)->where('id', ...)->update(...)`;
   - null values are left untouched;
   - **fixed during the security fix pass**: legacy empty strings (`''`)
     were previously excluded from the query entirely (`where($column, '!=', '')`)
     and silently left as plaintext `''` forever — reading them once the
     `encrypted` cast went live threw `DecryptException` (`''` is not a
     valid ciphertext payload). Every target column now declares whether it
     is nullable; on a nullable column a legacy `''` is normalized to
     `NULL` (an empty note is semantically "no note"); on a `NOT NULL`
     column (`order_notes.body`, `follow_ups.note`) it is instead encrypted
     as an empty string, which round-trips correctly through the
     `'encrypted'` cast. See
     `tests/Feature/Security/EncryptSensitiveDataCommandTest.php` for
     coverage of null / legacy-empty / plaintext / already-encrypted values
     all reading correctly through the model after migration;
   - supports `--dry-run` (report without writing) and `--chunk=N`.
3. **Deploy the model casts** (`'encrypted'` / `'encrypted:array'`) *only
   after* step 2 has run successfully against production data — see the
   exact deployment order in `docs/pre-launch-security.md` / the report for
   this milestone.

No temporary duplicate "new ciphertext column" was needed: because V1 uses a
single global `APP_KEY` (no per-row/per-workspace key selection), the
ciphertext fits in the same (now correctly-sized) column the plaintext used,
so there's no dual-write/cutover window to manage beyond "run the backfill,
then deploy the casts."

**Recovery if the backfill command fails partway**: it's chunked and
idempotent, so the fix is simply "run it again" — already-migrated rows are
detected and skipped, unmigrated rows are picked up from wherever the run
stopped. There is no partial/unknowable state: a row is either still
plaintext (readable, cast not yet deployed) or ciphertext (readable via
`Crypt::decryptString`); nothing in between.

## Part 7 — Column length changes

| Column | Before | After | Why |
|---|---|---|---|
| `follow_ups.note` | `varchar(255)` | `text` | Encrypted-cast ciphertext (base64 JSON envelope: IV + MAC + value) for even a short string exceeds 255 bytes; would have silently truncated ciphertext otherwise |
| `messages.metadata` | `json` | `text` | Ciphertext is a plain string, not valid JSON — MySQL's native `json` column type rejects it on write |

All other encrypted columns (`messages.body`, `conversations.last_message_preview`,
`customers.notes`, `orders.*`, `appointments.*`, `order_notes.body`) were
already `text` (MySQL: up to 65,535 bytes), comfortably large enough for
ciphertext of their existing `max:2000`–`max:5000` validation limits.

## Part 8/9 — Encryption primitive & key management

Uses Laravel's standard `Crypt` facade / Eloquent `'encrypted'` casts
(AES-256-CBC with a random IV and HMAC, via `Illuminate\Encryption\Encrypter`)
— no custom cryptography was written. Centralized in one place
conceptually: every encrypted field's *cast declaration* is the single
source of truth (see each model's `casts()`), and the one-time backfill
logic lives in a single command (`EncryptSensitiveData`) rather than being
duplicated ad hoc.

**Key**: `APP_KEY` (the same key used for session/cookie encryption
already). This is the V1-appropriate choice — see Part 9 of the original
task brief ("acceptable for V1... do not over-engineer"). Full detail,
rotation, and recovery procedure: `docs/encryption-key-runbook.md`. In
short: `APP_KEY` must come from environment/secret management, must never be
committed, and losing it makes every encrypted value (including this
milestone's fields, and the pre-existing integration tokens) permanently
unreadable. There is no per-row or per-workspace key in V1.

## Part 10 — Fail-closed behavior

Laravel's `encrypted` cast throws `Illuminate\Contracts\Encryption\DecryptException`
on read if a value cannot be decrypted (wrong/rotated key, corrupted data).
The application does **not** catch this and silently return ciphertext or
null — it propagates as a 500, which is the correct fail-closed behavior:
better to break a single record's page than to leak ciphertext or silently
lose data. Nothing in this codebase logs the plaintext or ciphertext value
on such failure; if a `DecryptException` needs bespoke handling in a future
task (e.g. a per-record "content unavailable" UI state), it must be
diagnosed using `workspace_id` / model / model id / field only — never by
logging the value itself.

## Part 11/12/13/14 — Private message attachments

**Audit findings**:

- **Inbound (Meta)**: attachments are **never downloaded** to Beležka
  storage. `MetaMessagingProvider::normalizeWebhookPayload` stores the
  attachment's Meta-hosted CDN URL as-is (`{type, source: 'external', url}`)
  — these URLs are provider-hosted and typically short-lived/signed by Meta
  itself. Nothing here needed to change; this is documented, not modified.
- **Outbound (Beležka user upload, `ConversationController::sendMessage`)**:
  **before this milestone**, files were stored on the `public` disk
  (`storage/app/public`, symlinked to `public/storage`) with a permanent,
  unauthenticated, guessable-path URL — exactly the anti-pattern Part 12
  warns against. **Fixed**: now stored on the `local` (private) disk under
  `inbox-attachments/`, with a random filename (`UploadedFile::store()`'s
  default — never the original filename). The private `local` disk's
  built-in Laravel `storage/{path}` serving route (`storage.local`, enabled
  via `'serve' => true` in `config/filesystems.php`) already requires a
  valid signed URL for non-public-visibility disks (verified by reading
  `Illuminate\Filesystem\ServeFile` — it 403s without one) — but this
  codebase does **not** rely on that mechanism, since local-disk temporary
  signed URLs aren't supported out of the box the way S3's are. Instead:

  - `App\Http\Controllers\Inbox\AttachmentController@show` — customer-facing,
    `auth`-protected route (`GET /inbox/attachments/{message}/{index}`,
    named `inbox.attachments.show`). Verifies the message's conversation
    belongs to the requesting user's `current_workspace_id`; 404 otherwise
    (matching the rest of the app's cross-tenant-is-a-404 convention).
  - `App\Http\Controllers\Admin\SupportContentController@attachment` — the
    admin equivalent (`GET /admin/workspaces/{workspace}/support/attachments/{message}/{index}`).
    Requires an active `workspace_content` support session for that exact
    workspace (`SupportSessionManager::require`), same as the other
    support-content routes; records one `support.content_access` audit row
    per attachment view.
  - Both share `App\Support\AttachmentResolver::respond()`, which streams
    the file via `Storage::disk('local')->response($path, ...)` with
    `Cache-Control: private, no-store` — after the caller has already
    authorized the request. It never serves a `source: 'external'`
    (Meta-hosted) entry — those aren't ours to serve.
  - The frontend (`MessageBubble.vue`) resolves the attachment `<img>`/link
    URL client-side: `source: 'local'` → `route('inbox.attachments.show', [message.id, index])`;
    `source: 'external'` → the stored Meta CDN URL directly. No public URL
    for a local attachment is ever stored or serialized to the frontend.

**File validation** (unchanged, already adequate): `sendMessage()` validates
`'attachment' => 'nullable|file|max:15360|mimes:jpg,jpeg,png,gif,webp,mp4,mov,pdf,doc,docx'`
— Laravel's `mimes` rule inspects the file's actual content type via
fileinfo, not just the browser-supplied `Content-Type` header or extension.

## Part 15/27 — Demo workspaces & attachments

Demo workspaces (Studio Nola / Sladka delavnica / Foto Studio Luna) use the
same `Customer`/`Order`/`Appointment`/`Message` models and thus the same
`encrypted` casts transparently — `DemoController`'s seeders write plaintext
through the model as before, and Eloquent encrypts it on save exactly like
any other row. No demo-specific code path was needed.

Demo users *can* upload real attachments in the Inbox (the mock provider
still exercises the same `storeOutboundAttachment()` path), so
`App\Console\Commands\CleanupExpiredDemos` was extended: before deleting an
expired demo workspace, it collects every `source: 'local'` attachment path
referenced by that workspace's messages, and — **after** the DB transaction
that deletes the workspace successfully commits — deletes those files from
the private disk. File deletion is best-effort and non-fatal (a stray
orphaned file is a cleanup nuisance, not a data-integrity problem); failures
are logged with a path hash, never the raw path.

## Part 16/17/18 — Logging, HTTP client errors, queue privacy

**Re-audited** every `Log::`, `logger(`, `report(`, `dump(`, `dd(`, `ray(`
call in `app/` (same conclusion as the prior admin-security milestone):
still clean. All log calls use identifiers only —
`channel_id`/`conversation_id`/`workspace_id`/HTTP status/Meta error
code/subcode — never message bodies, notes, tokens, or full payloads. Meta
HTTP error logging (`MetaMessagingProvider::sendMessage`) logs
`channel_id`, `conversation_id`, response status, and the Meta `error_code`/
`error_subcode` — never the full response body.

**Queue payload privacy (new finding, fixed this milestone)**:
`ProcessMetaWebhook` previously stored the *entire raw Meta webhook payload*
(may include message text, sender identifiers, attachment URLs) as a plain
public constructor property — meaning Laravel's queue serialization wrote
it in plaintext into the `jobs` table, and, on repeated failure, into
`failed_jobs`, where it could persist indefinitely. **Fixed**: the payload
is now encrypted (`Crypt::encryptString(json_encode($payload))`) in the
constructor and decrypted only inside `handle()`. Verified by
`tests/Feature/Security/QueuePayloadPrivacyTest.php`, which PHP-serializes
the job object (mirroring exactly what the queue driver persists) and
asserts a unique marker string is absent.

`FetchCustomerIdentityProfile` was already privacy-safe — it only carries
`channelId`/`customerIdentityId` (ints), never full model objects.

## Part 19 — Broadcast/realtime findings

`App\Events\InboxMessageReceived` (already existing) carries only
`workspaceId` and `conversationId` — no message content — on a
`PrivateChannel("workspace.{workspaceId}.inbox")`. `routes/channels.php`
authorizes that channel with
`(int) $user->current_workspace_id === (int) $workspaceId`. No change was
needed; added
`tests/Feature/Security/BroadcastAuthorizationTest.php` to prove it: a
user can authorize their own workspace's channel, cannot authorize another
workspace's channel (403), and an anonymous request is rejected (403).
(These tests must force the `reverb` broadcaster and re-require
`routes/channels.php` — the test environment's default `null` broadcaster
is a total no-op that would make every assertion pass trivially regardless
of the actual authorization callback; see the test file's comments.)

## Part 20/21 — Push & email notification privacy

**Push (fixed this milestone)**: `App\Notifications\FollowUpDue::toWebPush()`
previously put the raw follow-up note text directly into the OS/lock-screen
notification body — exactly the anti-pattern Part 20 warns against (a
follow-up note may reference private customer/health details). **Fixed** to
a generic body: `"Opomnik je zapadel. Odpri Beležko za podrobnosti."` The
notification's `data.url` still deep-links into the authenticated app for
the actual detail.

**Email**: no notification classes send email in this codebase currently
(`FollowUpDue` is WebPush-only; password reset/verification emails are
Laravel's stock framework notifications, which never contain customer
content). Nothing to change; documented for completeness.

## Part 22/23 — Passwords, sessions, HTTPS

Unchanged (verified, not modified): passwords use Laravel's default
`'hashed'` cast (bcrypt); password reset uses the framework-standard
`PasswordResetLinkController`/token broker; login is rate-limited (5
attempts/email+IP). Session cookies: `http_only` defaults to `true`,
`same_site` defaults to `'lax'`, `secure` follows `SESSION_SECURE_COOKIE`
(unset by default — **must be set to `true` in production**, see
`docs/pre-launch-security.md`; left as an infrastructure/environment
requirement rather than hardcoded, so local HTTP development isn't broken).
CSRF protection is Laravel's default `ValidateCsrfToken` middleware, with
the documented, narrow `webhooks/meta` exemption (verified via HMAC
signature instead — pre-existing, appropriate).

## Part 24/25 — Backups & infrastructure encryption

No backup implementation exists in this repository to audit — there is no
backup script, scheduled command, or cloud-backup integration configured
here. **Not claiming this is implemented.** See
`docs/pre-launch-security.md` "PRE-LAUNCH INFRASTRUCTURE REQUIREMENTS" for
the exact requirements (encrypted at rest/in transit, restricted access,
defined retention, restore test, not publicly exposed) that must be
satisfied by whatever hosting/infrastructure this deploys to. Same for
database volume and object/file storage disk encryption — these are
infrastructure-provider responsibilities this repository cannot enable or
verify from application code, and are listed as infrastructure checklist
items, not marked complete.

## Part 26 — Data exports / temp files

Searched for CSV/PDF/export generation and any `tempnam`/`sys_get_temp_dir`
usage: none exists in this codebase today. Nothing to fix; documented so a
future export feature implements it correctly from the start (non-public
storage, prompt deletion, authorized download, random filenames — the same
pattern used for attachments in this milestone).

## Part 28 — Development/seed data

Seeders (`StudioNolaSeeder`, `BloomAndCrumbSeeder`, `FotoStudioLunaSeeder`,
and the demo-creation flow) already use only synthetic, hand-authored data
— no mechanism exists anywhere in this codebase to copy or import real
production data into a local/dev environment. Nothing to change.

## Part 33 — Security headers

Added `App\Http\Middleware\SecurityHeaders` (registered globally in
`bootstrap/app.php`) applying, on every response: `X-Content-Type-Options:
nosniff`, `Referrer-Policy: strict-origin-when-cross-origin`,
`Content-Security-Policy: frame-ancestors 'none'` (modern equivalent of
`X-Frame-Options: DENY`), `Permissions-Policy:
geolocation=(), microphone=(), camera=()`. A full page-content CSP
(`script-src`/`style-src`/etc.) was deliberately **not** added — it risks
breaking the existing Vite/Inertia asset pipeline and any future Meta
embed/widget without careful, separate testing. Tracked as a discrete
pre-launch task in `docs/pre-launch-security.md`.

## Part 39 — Performance

Verified the Inbox conversation list (`ConversationController::conversationList()`)
does **not** select or decrypt individual `Message.body` rows — it only
touches `Conversation.last_message_preview` (one encrypted field decrypted
once per conversation row shown), consistent with "only decrypt what the
screen needs." The single-conversation `show()` view legitimately loads and
decrypts that conversation's own messages (unavoidable — that's the
screen's job).

`Customer`/`Order`/`Appointment` index pages use `SELECT *` (pre-existing
pattern, unchanged) and so do decrypt their few encrypted fields
(`notes`/`internal_notes`/`customer_notes`/`description`) for every row on
the page. At current pagination sizes (20–24 rows/page) this is negligible
(AES decryption is sub-millisecond); flagged here as a known, accepted
trade-off rather than restructured, since explicit per-page column
selection across every list controller is a larger, separate refactor with
its own regression risk. Revisit if production telemetry ever shows list
pages as slow.

## Future: per-workspace encryption keys / KMS (not implemented)

Documented per Part 36/9 of the task brief — **not implemented in this
milestone**, deliberately, per "do not over-engineer V1."

**What would need to change** to move from the current single-`APP_KEY`
model to per-workspace keys + envelope encryption / KMS:

1. **Key storage**: a `workspace_encryption_keys` table (or KMS reference
   per workspace) holding a per-workspace Data Encryption Key (DEK),
   itself encrypted ("wrapped") by a root Key Encryption Key (KEK) held in
   a KMS (AWS KMS, GCP KMS, HashiCorp Vault, etc.) — never the raw DEK at
   rest.
2. **Custom Eloquent cast**: replace the built-in `'encrypted'` cast with a
   workspace-aware custom cast (e.g. `App\Casts\WorkspaceEncrypted`) that
   resolves the correct DEK for the model's `workspace_id` at encrypt/decrypt
   time, instead of always using `APP_KEY`. This is the main code-shape
   change — every encrypted field's cast declaration would need to move
   from `'encrypted'` to this new cast.
3. **Key rotation per workspace**: re-encrypt a single workspace's rows
   without touching others — requires the same chunked/idempotent backfill
   pattern as `EncryptSensitiveData`, scoped to one `workspace_id` and one
   old/new DEK pair.
4. **Demo workspace consideration**: ephemeral demo workspaces would need
   their own short-lived DEK, generated at creation and destroyed at
   cleanup — natural fit with the existing `CleanupExpiredDemos` command.
5. **Support-access implications**: none — `docs/admin-security.md`'s
   support-session model is already key-agnostic; it gates *whether* the
   app is allowed to decrypt/show content to an admin, not *which* key is
   used to do so.
6. **Searchable identifiers**: if queryable identifier fields (Part 5) are
   ever encrypted, this same KMS/DEK infrastructure is the natural place to
   also introduce **blind indexes** (a separate deterministic HMAC column
   used only for equality lookup, alongside the randomly-encrypted display
   value) — explicitly the "another design" the original task brief asks to
   defer rather than build ad hoc now.

This is a genuinely larger project (new infra dependency, migration
tooling, operational key-management process) and should be scoped as its
own milestone once there's a concrete driver for it (compliance
requirement, customer demand, or a specific incident).
