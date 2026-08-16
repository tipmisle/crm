# Data Lifecycle: Retention, Export, Deletion

Internal reference. Not for external/marketing use — this document describes
what the application does, not a legal conclusion about GDPR/ZVOP-2
compliance. Do not claim "GDPR compliant" anywhere in public UI or copy.

Companion documents: [`docs/data-security.md`](./data-security.md) (sensitive
field inventory and encryption), [`docs/admin-security.md`](./admin-security.md)
(platform admin / support-access model).

## 1. Purpose and scope

Before publishing real Privacy Policy / Terms / DPA pages, the application
needs to actually implement — not just promise — predictable behavior for
exporting and deleting workspace data, deleting an account, and handling a
business customer's own data-subject requests. This document is the
reference for that behavior.

Explicitly out of scope for this milestone (see "Out of scope" below):
final legal-page copy, billing/accounting retention, cookie banners,
KMS/end-to-end encryption, broad admin access to customer content, and any
public "GDPR compliant" claim.

## 2. Controller vs. processor role model

Two distinct roles, never mixed:

- **Beležka as controller** — data about Beležka's own account holders:
  `users` (name, email, auth, `current_workspace_id`), `legal_acceptances`,
  security/audit information (`audit_logs`, `support_access_grants`,
  `support_sessions`). Subscription/billing metadata will fall here once
  billing exists (not yet implemented).
- **Beležka as processor** — data a business (the workspace owner) stores
  about *their* customers, processed by Beležka on the business's behalf:
  `workspaces` (the business's own identity), `customers`,
  `customer_identities`, `conversations`, `messages` (incl. embedded
  attachments), `orders`, `order_notes`, `appointments`, `catalog_items`
  (products/services), `follow_ups`, `activity_logs`, `channels`,
  `integrations`.

## 3. Per-model inventory

| Model / table | Owner relation | Category | Role | Deletion dependency | Export | Special handling |
|---|---|---|---|---|---|---|
| `User` | — | Account | Controller | `workspace_members.user_id` cascadeOnDelete; owned workspaces per §6 | Not exported by workspace/customer export | Password hash never exported |
| `Workspace` | root | Business identity | Processor (content) / Controller-adjacent (business record) | Root of the cascade graph, §5 | `workspace.json` | `deletion_requested_at`/`scheduled_deletion_at`, distinct from `is_demo`/`demo_expires_at` |
| `WorkspaceMember` | Workspace | Membership | — | cascadeOnDelete on both FKs | Not exported | No real second role in production today (only `owner` ever inserted) |
| `Integration` | Workspace | Secrets (tokens) | Processor (infra) | cascadeOnDelete | **Never exported** | `access_token`/`refresh_token` encrypted + hidden |
| `Channel` | Workspace | Secrets (token) + config | Processor (infra) | cascadeOnDelete | Whitelisted fields only, no token | `access_token` encrypted + hidden |
| `CustomerIdentity` | Customer, Workspace | Personal identifier | Processor | cascadeOnDelete (customer_id, workspace_id) | Included in customer export | Deleted (not nulled) on customer erasure |
| `Customer` | Workspace | Personal identifier + notes | Processor | cascadeOnDelete; **never hard-deleted by erasure**, see §8 | `customers.csv` / customer export | `notes` encrypted |
| `Conversation` | Workspace, Channel, Customer | Metadata + preview | Processor | cascadeOnDelete | `conversations.json` | `last_message_preview` encrypted |
| `Message` | Conversation | Private content + attachments | Processor | cascadeOnDelete via conversation | `messages.json` (attachment metadata only, not file bytes) | `body`/`metadata` encrypted; no `workspace_id` column, derived via conversation |
| `Order` | Workspace, Customer | Operational + private notes | Processor | cascadeOnDelete | `orders.csv` | `description`/`internal_notes`/`customer_notes` encrypted; retained (anonymized) on customer erasure |
| `OrderNote` | Order | Private content | Processor | cascadeOnDelete | Included in customer export | `body` encrypted |
| `Appointment` | Workspace, Customer | Operational + private notes | Processor | cascadeOnDelete | `appointments.csv` | Same encrypted fields as Order; retained (anonymized) on erasure |
| `CatalogItem` (Product/Service) | Workspace | Business catalog | Processor | cascadeOnDelete | `catalog.csv` | `description` deliberately plaintext (shown to customers) |
| `FollowUp` | Workspace, polymorphic | Private note | Processor | cascadeOnDelete | `follow-ups.csv` | `note` encrypted; nulled on customer erasure |
| `ActivityLog` | Workspace, polymorphic | Identifiers only | Processor | cascadeOnDelete | Not exported | Never contains message/note content |
| `AuditLog` | Workspace (nullable), actor (nullable) | Security trail | Controller | `nullOnDelete` on both FKs — **survives** workspace/user deletion | Not exported | See §11, no purge job |
| `SupportAccessGrant` | Workspace | Security/consent record | Controller | cascadeOnDelete | Not exported | Deleted with workspace; audit trail of the grant persists via AuditLog |
| `SupportSession` | Grant, Workspace | Security record | Controller | cascadeOnDelete | Not exported | Same as above |
| `PushSubscription` (webpush package) | **User**, not Workspace | Device token | Controller | No FK constraint — cleaned explicitly on user deletion (§7) | Not exported | Irrelevant to workspace purge |
| `WorkspaceExport` | Workspace | Transient bookkeeping | — | cascadeOnDelete (row); file swept independently by `WorkspaceDeletionService` | — | Short-lived, see §9 |
| `LegalAcceptance` | User, Workspace (nullable) | Consent record | Controller | cascadeOnDelete on `user_id` | Not exported | Foundation only, not yet wired up, see §13 |
| Demo data (`is_demo`/`demo_expires_at`) | Workspace, User | — | — | `DemoWorkspaceCleanupService` / `demos:cleanup` | N/A | **Entirely separate system** — not touched by this feature |

## 4. Deletion graph

Verified directly against migrations on 2026-08-15. `Workspace` is the root;
every child below is `cascadeOnDelete` on `workspace_id` (or, for `Message`,
via its parent `Conversation`):

`channels`, `integrations`, `customers` (+ `customer_identities`),
`conversations` (+ `messages`), `orders` (+ `order_notes`), `appointments`,
`catalog_items`, `follow_ups`, `activity_logs`, `workspace_members`,
`support_access_grants` (+ `support_sessions`), `workspace_exports`.

`users.current_workspace_id` is `nullOnDelete`, **not** cascade — a
remaining member's current workspace silently becomes `null` after a purge.
`HandleInertiaRequests` already null-safes this (`$user?->currentWorkspace`),
confirmed by reading the middleware.

Re-verify this list if a new workspace-scoped table is added without
`cascadeOnDelete` — nothing enforces it automatically.

## 5. Retention policy (`config/retention.php`)

| Key | Default | Enforced by | Notes |
|---|---|---|---|
| `workspace_grace_days` | 30 | `workspaces:purge-expired` | Configurable via `RETENTION_WORKSPACE_GRACE_DAYS` |
| `export_hours` | 24 | `exports:purge-expired` | Configurable via `RETENTION_EXPORT_HOURS` |
| `backups_note` | — | Not code-enforced | Infra decision, pending — see §14 |
| `audit_log_days` | `null` | **Nothing** | Documented placeholder only — see §11 |

## 6. Workspace deletion lifecycle

1. Owner requests deletion (`Settings → Podatki in račun → Izbriši delovni
   prostor`), owner-only (`WorkspaceMember::isOwnerOf`), gated by
   `password.confirm` + re-entered password. Sets
   `deletion_requested_at = now()`, `scheduled_deletion_at = now() +
   workspace_grace_days`. Logs `privacy.workspace.deletion_requested`.
2. During the grace period the owner can cancel (`Prekliči izbris`), which
   clears both fields and logs `privacy.workspace.deletion_cancelled`.
   Normal operation is not currently disabled during the grace period (no
   read-only/locked state was added — flagged as a possible V2 refinement,
   not implemented here).
3. `workspaces:purge-expired` (scheduled daily) selects real (`is_demo =
   false`) workspaces whose `scheduled_deletion_at <= now()` and calls
   `WorkspaceDeletionService::delete()`, which: best-effort unsubscribes
   Meta webhooks per channel (never blocks, never logs tokens), deletes the
   workspace row (cascading per §4), then deletes local attachment files and
   any lingering export files from disk. Logs
   `privacy.workspace.purge_attempted` before and `privacy.workspace.purged`
   after, so a failure is distinguishable from a success in the audit trail.
4. After purge there is no undo — a fresh workspace is required. Backups
   are a separate lifecycle, see §14.

## 7. Account deletion (`ProfileController::destroy`)

A user may belong to more than one workspace — deleting the account is
never assumed to mean deleting a workspace. Rules:

| Situation | Behavior |
|---|---|
| Owns 0 workspaces | Delete account normally. |
| Owns a workspace where they're the only member | That workspace is cascade-deleted synchronously via `WorkspaceDeletionService` (consent is implicit in deleting the account), then the account is deleted. |
| Owns a workspace with other members too | **Blocked** with a clear error. No ownership-transfer flow exists yet — documented V1 limitation, not silently worked around. |
| Non-owner member of any workspace | No effect on that workspace's data — only the membership row is removed (already correct via existing `cascadeOnDelete` on `workspace_members.user_id`). |

Also: orphaned `push_subscriptions` rows (no FK constraint from the webpush
package) are explicitly deleted; `privacy.account.deleted` is logged before
the user row is removed, while the acting user can still be resolved as the
audit actor.

## 8. Customer export and erasure

Beležka is a processor for customer data — this is data-subject-request
*tooling for the business*, not a public self-service form. Gated on
workspace membership only (there is no real second `WorkspaceMember` role in
production today, and nothing else in the CRM gates by role either).

- **Export** (`CustomerExportService`): a small, synchronous, on-the-fly ZIP
  scoped strictly to one customer's own profile, identities, conversations
  and messages, orders and order notes, appointments, and follow-ups. Not
  persisted — streamed and discarded.
- **Erasure** (`CustomerErasureService`): anonymizes in place. The
  `Customer` row is **never hard-deleted** — `orders.customer_id` and
  `appointments.customer_id` are required, `cascadeOnDelete` FKs, so
  deleting the row would destroy the business's own orders/appointments
  too. Instead:
  - `full_name` → `"Izbrisana stranka"`; `email`, `phone`, `notes`, `tags`
    nulled.
  - `CustomerIdentity` rows **deleted** (nothing else FKs to their id).
  - Message bodies/metadata in the customer's conversations nulled;
    `conversations.last_message_preview` nulled; `customer_display_name`
    replaced, `customer_username` nulled.
  - `follow_ups.note` nulled.
  - `orders.customer_notes` / `appointments.customer_notes` nulled, but the
    operational record itself (title, dates, amounts, status) is **kept** —
    the customer link can't be nulled (non-nullable FK) and deleting it
    would corrupt the business's own operational/financial history for
    something not strictly required to fulfil the request.
  - Logged as `privacy.customer.erased` with the customer id only, no
    content.

This project makes no legal claim about Slovenian tax/accounting retention
law — if a business has a separate lawful basis to retain operational
records longer, that is the business's (the controller's) own obligation to
manage, not something this code decides.

## 9. Workspace export

`ExportWorkspaceDataService` builds `workspace.json`, `customers.csv`,
`conversations.json`, `messages.json`, `orders.csv`, `appointments.csv`,
`follow-ups.csv`, `catalog.csv` by reading through Eloquent (so encrypted
casts decrypt automatically), zipped to a random filename
(`Str::random(40)`) on the **private** `local` disk — never public storage.
Runs synchronously in the request (no queue worker infra is relied on
elsewhere in this app).

Explicitly excluded: `integrations.access_token`/`refresh_token`,
`channels.access_token`, password hashes, session data, `APP_KEY`. Verified
`Channel`/`Integration` `$hidden` include their token columns; the export
service additionally whitelists columns rather than relying on `$hidden`
alone. Attachment file *bytes* are not bundled — only type/source metadata —
this is data portability, not a full backup.

Download (`WorkspaceExportController::download`) is owner-only, authorized
the same way `AttachmentResolver`'s callers are (app-level auth check, not a
public Laravel signed URL), single-use (`downloaded_at` blocks re-download),
and 410s once expired. `privacy.workspace.export_requested` and
`privacy.workspace.export_downloaded` are logged with the export id only —
never the disk path.

`exports:purge-expired` (scheduled hourly) deletes the file and row past
`expires_at` (default 24h, `RETENTION_EXPORT_HOURS`).

## 10. Attachment and export file cleanup

Both `DemoWorkspaceCleanupService` and `WorkspaceDeletionService` share the
`CollectsLocalAttachmentPaths` trait: collect local attachment paths
*before* the DB transaction, delete the DB rows transactionally, then
delete files from disk *after* commit, best-effort (logged by path hash
only, never fatal). `WorkspaceDeletionService` additionally sweeps any
`WorkspaceExport` files for the workspace being purged, since the DB row's
`cascadeOnDelete` removes the tracking row but not the file itself.

Orders and appointments have no attachment fields of their own — the only
file-cleanup surface is `Message.metadata['attachments']`.

## 11. Audit log and support-access retention

- `support_access_grants` / `support_sessions`: already `cascadeOnDelete` on
  `workspace_id` — no code needed. When a workspace is purged, these
  disappear with it; the fact that support access was granted/used remains
  visible only through `AuditLog` events (`support_access.granted`,
  `support_session.started`, etc.), which survive independently.
- `audit_logs`: **no purge job exists, intentionally**.
  `config('retention.audit_log_days')` is a documented, unenforced
  placeholder. Do not wire it into a scheduled command without legal review
  — retention requirements for security/processing logs (e.g. ZVOP-2
  Article 22) may set a mandatory minimum this app must not undercut. This
  document makes no legal conclusion about which events fall under that
  article; it only keeps the architecture capable of complying once that's
  determined.

## 12. Meta integration cleanup

Before a real workspace is purged, `WorkspaceDeletionService` best-effort
calls `MetaMessagingProvider::unsubscribeWebhooks()` for each
Instagram/Facebook Messenger channel — mirrors
`MetaIntegrationController::destroy()`'s existing disconnect flow. Failures
are logged (channel id only, never a token) and never block deletion —
deletion must not become permanently stuck because Meta is temporarily
down. Local credentials are destroyed regardless, via the normal cascade.

## 13. Legal acceptance foundation

`legal_acceptances` (model `LegalAcceptance`, enum `LegalDocument`:
`terms`/`dpa`) exists as schema-only foundation for future versioned
Terms/DPA acceptance tracking. **Nothing calls `LegalAcceptance::record()`
yet** — registration is not blocked on acceptance until real legal documents
with real version strings exist. Must never be recorded for `is_demo=true`
users if/when wired up. `user_id` is `cascadeOnDelete` — acceptance history
is account-personal data and is deleted with the account, unlike
`audit_logs` which intentionally survives.

## 14. Backups

Backup infrastructure is not configured in this repository. This document
does not implement a fake backup-deletion API. Requirements once hosting is
finalized: backups have a defined maximum retention period; deleted
workspace/customer data naturally ages out of backups rather than being
individually purged from them; backups remain access-controlled and
encrypted; backups are restored only for disaster recovery, never to
casually resurrect an individually-deleted customer's data into production.
Exact backup retention is an **infrastructure decision, not yet made**.

## 15. Demo differences

Demo workspaces/users are governed entirely by `is_demo` /
`demo_expires_at` and `DemoWorkspaceCleanupService` / `demos:cleanup` — a
wholly separate system this feature does not modify.
`workspaces:purge-expired` explicitly excludes `is_demo = true` workspaces
even if `scheduled_deletion_at` were somehow set on one. Demo users/
workspaces never require Terms/DPA acceptance and are never eligible for the
30-day real-workspace deletion flow. If demo-scoped export is ever added, it
must expire no later than the demo workspace itself — not implemented here.

## 16. Remaining owner / infrastructure decisions

- **Owner decisions still needed**: final legal-page copy (Terms, Privacy
  Policy, DPA); whether/when to build an ownership-transfer flow (currently
  a hard block on deleting a multi-member workspace's owner); whether to
  disable normal operation during the grace period.
- **Infrastructure decisions still needed**: backup retention period and
  disaster-recovery restore process; Stripe/billing legal retention
  (deliberately not invented in this milestone); production disk-encryption
  configuration for the `local` disk (referenced in
  `docs/pre-launch-security.md`, not re-litigated here).

## Out of scope for this milestone

Final legal-page copy, billing/accounting retention assumptions, cookie
banners, KMS/end-to-end encryption, AI features, broad admin access to
customer content, any "GDPR compliant" public claim, and blocking
registration on legal acceptance.
