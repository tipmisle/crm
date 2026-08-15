# Admin & Support-Access Security Model

Internal reference for the `/admin` console and the temporary support-access
system. Not for external/marketing use.

## Core claim this architecture supports

> Podpora Beležke nima običajnega dostopa do vsebine tvojih pogovorov. Če je
> za reševanje težave potreben vpogled v tvoj delovni prostor, moraš dostop
> izrecno in začasno dovoliti. Dostop lahko kadarkoli prekličeš in vsak tak
> vpogled se beleži.

This is not yet published anywhere customer-facing.

## 1. Platform-admin authorization

- `users.is_platform_admin` (boolean, default `false`) is the *only* thing
  that grants `/admin` access. Workspace role (owner/member) never does.
- Deliberately **not mass-assignable** on `User` — excluded from `$fillable`.
  The only code path that can set it is `php artisan admin:grant {email}`
  (`App\Console\Commands\GrantPlatformAdmin`), which uses `forceFill()`.
  There is no web UI to grant/revoke this flag — granting platform-admin
  status is console/deploy-access-only by design.
- `App\Http\Middleware\EnsurePlatformAdmin` guards every `/admin` route
  (`routes/admin.php`, aliased as `platform.admin`). It denies by default:
  no user → 403, inactive user → 403, non-admin → 403 (with an
  `admin.access_denied` audit row). There is no UI-only hiding anywhere.
- Sensitive admin mutations (starting a support session, deactivating a
  user, deleting a demo workspace, clearing an integration error) are
  additionally gated by Laravel's built-in `password.confirm` middleware —
  a recently-confirmed password is required on top of the platform-admin
  session.
- Login already has rate limiting (5 attempts per email+IP, Laravel's
  standard `RateLimiter`/`Lockout` — unchanged, pre-existing).
- **MFA: not implemented. This is a required pre-launch follow-up** — see
  §20. No custom OTP was built; per the task brief, a weak home-grown 2FA
  is worse than none. Recommend `pragmarx/google2fa` (or Laravel
  Fortify's TOTP support) gated specifically on `is_platform_admin` users.

## 2. Admin routes/pages

All under `/admin`, `routes/admin.php`, registered via
`bootstrap/app.php`'s `then:` callback so they exist independent of
`routes/web.php`:

- `GET /admin` — Dashboard
- `GET /admin/workspaces`, `GET /admin/workspaces/{workspace}` — Workspaces
- `GET /admin/users`, `GET /admin/users/{user}` — Users
- `GET /admin/integrations` — Integration diagnostics
- `GET /admin/audit-log` — Audit log
- `POST /admin/workspaces/{workspace}/support/start`,
  `POST /admin/support/end` — support-session lifecycle
- `GET /admin/workspaces/{workspace}/support/{conversations,customers,orders}/{id}`
  — content viewers, gated by an active `workspace_content` session
- `POST /admin/users/{user}/{deactivate,reactivate}`
- `DELETE /admin/workspaces/{workspace}/demo`
- `POST /admin/integrations/{integration}/clear-error`

Frontend: `resources/js/Layouts/AdminLayout.vue` (separate visual system
from the customer app — no shared marketing/product chrome), pages under
`resources/js/Pages/Admin/**`.

## 3. Dashboard data

Aggregate counts only: real vs. demo workspace counts, total users,
Instagram/Messenger connected-integration counts, integrations currently in
`error` status, newest real workspaces, most-recently-failed integrations.
No per-customer or per-conversation data.

## 4. Workspace admin detail

`Admin\WorkspaceController::show` returns: basic workspace fields, owner
name/email, usage counts (members, customers, conversations, messages,
orders, appointments, products, services, follow-ups — counts only),
integration summaries (see §6), and support-access status. Every view
records an `admin.workspace.view` audit row.

Usage counts and integration lookups deliberately call
`Model::withoutGlobalScopes()->where('workspace_id', $workspace->id)`
rather than relying on `BelongsToWorkspace`'s scope, because the acting
platform admin has no `current_workspace_id` of their own — see §13.

## 5. User admin functionality

`Admin\UserController` supports: search, viewing account metadata,
deactivate/reactivate (`is_active` + `deactivated_at`, enforced at login in
`LoginRequest::authenticate` — a deactivated user cannot sign in even with
a correct password). No password viewing, no admin-initiated password
reset, no impersonation, no route from a user record into their workspace's
content.

## 6. Integration diagnostics

`Admin\IntegrationController::index` lists every integration
cross-workspace with status, external account id/display name,
connected_at, last_synced_at, token_expires_at, scopes. `access_token` and
`refresh_token` are never selected/returned — `Integration` already hides
them via `$hidden`, and the admin summary array only ever includes the
allow-listed fields above (see `WorkspaceController::integrationSummary`).
One operational action exists: "clear error" resets a stuck `error` status
without contacting the provider or touching tokens.

## 7. Fields intentionally hidden from the admin app

Never rendered, selected, or logged anywhere in `/admin` outside an active
`workspace_content` support session:

- `integrations.access_token`, `integrations.refresh_token`
- `channels.access_token`
- message bodies / attachments
- customer notes, order `internal_notes`/`customer_notes`
- raw webhook payloads

## 8. Support-access schema

`support_access_grants` (customer-owner-controlled permission record):
`id, workspace_id, granted_by_user_id, granted_at, expires_at, revoked_at,
scope, created_at, updated_at`. `App\Models\SupportAccessGrant`.

`support_sessions` (a specific admin's live use of a grant):
`id, support_access_grant_id, workspace_id, admin_user_id, scope,
started_at, expires_at, ended_at, ended_reason, created_at, updated_at`.
`App\Models\SupportSession`.

Kept as two tables deliberately: a grant is "permission exists"; a session
is "someone is actually using it right now." A grant can outlive many (or
zero) sessions; an admin must explicitly start a session even when a valid
grant exists.

## 9. Support-access scopes

**Updated in the security fix pass**: `App\Enums\SupportAccessScope` now has
exactly one case, `WorkspaceContent`. The original design also had a
`technical` scope, but normal admin metadata (workspace config, integration
status, operational counts — see §3/§4/§6) was already visible without any
grant at all, so `technical` granted almost nothing beyond the no-grant
baseline and only confused the owner-facing consent copy about what they
were approving. It was removed from the enum, the grant form, and
`SupportSessionManager::require()` (which no longer takes a scope
parameter — a valid session now always implies content access, since
there's nothing narrower left to check). A migration
(`2026_08_15_215622_migrate_legacy_technical_support_scope_to_workspace_content`)
rewrites any pre-existing `scope='technical'` row to `'workspace_content'`
for enum-cast validity, while simultaneously revoking the grant / ending the
session if it was still active — so no row silently gains access an owner
never explicitly approved for content.

Grant flow (`Settings\SupportAccessController`, customer-facing, "Nastavitve
→ Podpora"): only the workspace **owner** (`WorkspaceMember.role ===
'owner'`) can grant or revoke; duration is restricted to 30/60/240 minutes
server-side; granting a new access supersedes (revokes) any prior active
grant for that workspace so duration can never silently extend by stacking.

## 10. Support-session behavior

`App\Support\SupportSessionManager` owns the whole lifecycle:

- `start()` requires an active grant on the workspace; creates a
  `SupportSession` bound to that grant, workspace, admin, and scope;
  stores only the session id in the admin's own Laravel session.
- `current()` re-resolves the session **and its grant from the database on
  every call** — session/cookie state is never trusted alone. If the
  session's own `expires_at` has passed, or the backing grant is missing,
  revoked, or itself expired, the session is closed server-side and `null`
  is returned. **Updated in the security fix pass**: `current()` also
  verifies `$session->admin_user_id === $request->user()->id` — a support
  session id living in the browser's Laravel session is only ever honored
  for the admin it actually belongs to. A mismatch does not end the
  session (it may still be legitimately active for its real owner
  elsewhere) — it just refuses to use it for the mismatched requester,
  forgets the reference in *this* browser session, and records a
  `support_session.admin_mismatch` audit row. Never rely on the session
  cookie alone as proof of identity.
- `start()` also now closes any existing active support session for that
  admin browser session first (via `end($request, 'replaced')`) before
  creating the new one — an admin can never have two "active" sessions
  where only one is reachable through `current()`, with the other silently
  orphaned until it expires on its own.
- `require($request, $workspace)` is what every content-viewing controller
  action calls: 403s if there's no session, or if the session's workspace
  doesn't match the URL's workspace. (No scope parameter as of the fix
  pass — see §9.)

## 11. Revocation / expiration enforcement

Revocation is enforced at the data layer, not the UI: the owner revoking a
grant (`SupportAccessController::destroy`) sets `revoked_at`; the very next
`current()`/`require()` call — whether mid-way through an existing "active"
session or a fresh request — sees `grant->isValid() === false` and closes
the session immediately, before any protected data is returned. The same
path handles natural expiration. There is no cache or flag that could leave
a stale "still allowed" state.

## 12. Audit-log architecture and events

`audit_logs` (`App\Models\AuditLog`): `actor_user_id, actor_type,
workspace_id, event, target_type, target_id, ip_address, user_agent,
metadata (json), created_at`. No `updated_at` — the model sets
`UPDATED_AT = null`; nothing in the codebase ever calls `update()` on an
`AuditLog` row. This is separate from the pre-existing `ActivityLog` model,
which is a workspace-facing feed users can already see/edit context around
— audit_logs is admin/security-only and not exposed to workspace members.

Events emitted: `admin.access_denied`, `admin.workspace.view`,
`admin.workspace.changed`, `admin.user.changed`, `admin.integration.changed`,
`support_access.granted`, `support_access.revoked`,
`support_session.started`, `support_session.ended`,
`support_session.expired`, `support_session.admin_mismatch`,
`support.content_access`.

`AuditLog::record()` only ever accepts identifiers and short metadata —
callers pass things like `['resource' => 'conversation']` or
`['grant_id' => ..., 'scope' => ...]`, never model bodies. Content-access
logging is per-resource-open (one row per conversation/customer/order
viewed), not per message rendered.

## 13. Workspace-isolation changes (found + fixed during this task)

**Fixed a real cross-workspace data-leak vector** in
`App\Models\Concerns\BelongsToWorkspace`: the global scope only applied a
`workspace_id` filter when `Auth::check() && Auth::user()->current_workspace_id`
was truthy. Any authenticated user *without* a resolvable
`current_workspace_id` — which is exactly the situation for every platform
admin — fell through with **no scope applied at all**, meaning a
straightforward `Customer::all()` (or any workspace-scoped model query) run
in that context would have returned every workspace's rows. Fixed by
defaulting to a scope that matches nothing (`whereRaw('1 = 0')`) whenever
the user is authenticated but has no resolvable workspace, instead of
omitting the scope. Covered by
`tests/Feature/WorkspaceIsolationHardeningTest.php` ("an authenticated user
with no current workspace cannot see any workspace-scoped rows") and
implicitly by every admin test (admin routes only work at all because they
explicitly bypass the scope with `withoutGlobalScopes()->where('workspace_id', ...)`).

All admin-side reads of workspace-scoped models (`Integration`, `Channel`,
`Conversation`, `Customer`, `Order`, `Appointment`, `Product`, `Service`,
`FollowUp`, `WorkspaceMember`) go through explicit
`withoutGlobalScopes()->where('workspace_id', $workspace->id)` rather than
relying on the implicit scope, and admin route parameters for those models
are bound as plain `int` and resolved manually — Laravel's implicit route
model binding would otherwise apply the (now default-deny) global scope and
404 for a platform admin with no `current_workspace_id`.

Pre-existing isolation (route-model-binding + the scope) was verified with
new tests covering customers, orders (update + delete), and appointments
across workspaces, alongside the pre-existing `Inbox/WorkspaceIsolationTest`.

## 14. Policies / authorization added

No Laravel Policy classes existed before this task and none were
introduced — the codebase's existing pattern is scope + explicit in-
controller checks (e.g. route model binding 404s cross-tenant access). This
task follows the same pattern for consistency: `EnsurePlatformAdmin`
middleware for the admin boundary, `SupportSessionManager::require` for the
support-content boundary, and explicit `abort_if`/`abort_unless` checks in
controllers (e.g. `SupportAccessController::isOwner`,
`WorkspaceController::destroyDemo`'s `is_demo === true` guard).

## 15. Webhook/job tenancy changes

Audited `ProcessMetaWebhook` → `MessageIngestionService` → `MetaMessagingProvider`.
No changes were needed: the job resolves its `Channel` (and therefore
`workspace_id`) from Meta's own `external_account_id` in the payload via
`Channel::withoutGlobalScopes()->where('external_account_id', ...)`, never
from any authenticated user's session or `current_workspace_id`. This
architecture already avoids the failure mode this task was checking for.

## 16. Logging redaction changes

Audited every `Log::` call in `app/`. All existing calls already log only
identifiers (`channel_id`, `conversation_id`, `error code/status`) — no
message bodies, tokens, or raw webhook payloads were found being logged.
No changes were required. The new admin/support code follows the same
discipline: `AuditLog::record()` metadata is always a short, explicit,
hand-picked array of identifiers.

## 17. Tests added

- `tests/Feature/Admin/AdminAuthTest.php` — deny-by-default admin access
- `tests/Feature/Admin/AdminPrivacyTest.php` — no tokens in admin responses,
  no content without a grant, metadata routes work without one
- `tests/Feature/Admin/SupportAccessTest.php` — grant/owner-only, expiry,
  revocation mid-session, scope enforcement, cross-workspace session
  rejection, session expiry, audit events
- `tests/Feature/Admin/AuditLogTest.php` — view generates audit row;
  metadata never contains a message body
- `tests/Feature/Admin/DemoSafetyTest.php` — demo deletion rejects real
  workspaces
- `tests/Feature/WorkspaceIsolationHardeningTest.php` — cross-workspace
  customer/order/appointment access, and the no-workspace-context leak fix

118/118 tests pass (92 pre-existing + 26 new).

## 18. Vulnerabilities discovered and fixed during this task

1. **Cross-tenant data leak for any authenticated-but-workspace-less user**
   in `BelongsToWorkspace` — see §13. This is the one PART 25 explicitly
   asks to stop and fix; done as part of this change.

No other pre-existing high-severity issues were found: tokens were already
`encrypted` casts + `$hidden`, logging was already clean, webhook tenancy
was already trustworthy, and no admin/impersonation surface existed before
this task to have a bypass in.

## 19. MFA status

**Not implemented — required pre-launch follow-up.** See §1 and §20.

## 20. Remaining pre-launch security TODOs

1. **MFA for platform admins — still a PRE-LAUNCH BLOCKER, not implemented.**
   Re-verified during the security fix pass: `composer.json`/`composer.lock`
   contain no Fortify, `pragmarx/google2fa`, or any other 2FA/TOTP package —
   there is no mature MFA implementation already installed to wire up.
   Deliberately not built in this fix pass (a rushed custom OTP
   implementation would be worse than none). Any platform admin who can
   enter `workspace_content` support mode must not do so in production
   without MFA in front of their account. Recommended: TOTP via Fortify or
   `pragmarx/google2fa`, required specifically for `is_platform_admin`
   accounts (not the whole user base).
2. **Message-body encryption at rest** — explicitly out of scope for this
   task per the brief; a separate milestone. This task did not make the
   plaintext situation worse and added no new feature that surfaces
   message bodies outside an audited, temporary, owner-granted session.
3. **Break-glass access** — deliberately not built. Current behavior is
   "no grant → no content access," full stop, including for platform
   admins. If a genuine incident-response need for break-glass access
   emerges, it must satisfy every condition in PART 14 of the original
   brief (platform-admin only, password reconfirmation, MFA, mandatory
   reason, short max duration, obvious UI warning, full audit trail) —
   none of that exists today.
4. **Rate limiting on `/admin` routes specifically** — login itself is
   rate-limited (pre-existing), but no additional throttle sits on top of
   `platform.admin`-gated actions. Low priority given MFA is the bigger
   gap, but worth adding alongside MFA.
5. **`admin:grant` command has no audit trail** — it's a deliberately
   console-only, deploy-access-gated operation, but currently doesn't write
   an `AuditLog` row itself (it isn't running inside an authenticated HTTP
   request). Worth adding an `AuditLog::record()` call with
   `actor_type = 'system'`/operator context if this becomes a frequent
   operation.
6. **Confirm production session/password-confirm timeout** — Laravel's
   default `password.confirm` window is 3 hours; consider shortening it
   specifically for the admin area if that feels too long for this
   threat model.

## Security fix pass (post-launch review)

A follow-up review of the first two milestones found and fixed several
issues, none of which changed the overall architecture:

1. **Meta webhook tenant routing** (critical). `channels.external_account_id`
   was only unique per-workspace, so nothing stopped the same Instagram/
   Messenger account from being connected to two different workspaces —
   `MessageIngestionService`'s inbound-webhook channel lookup
   (`Channel::where('external_account_id', ...)->first()`) would then
   arbitrarily pick one, silently routing a customer's DM into the wrong
   business's Inbox. Fixed with a DB-level invariant (a global
   `unique(type, external_account_id)` index on `channels`, migration
   `2026_08_15_215001_...`) plus an application-level rejection in
   `MetaIntegrationController::store` (connecting an already-claimed
   account is refused with a clear Slovenian error, nothing is silently
   reassigned) plus a hardened ingestion lookup (now filters to
   `status='connected'` and explicitly refuses to guess — logging
   `messaging.ingest.ambiguous_channel` with identifiers only — if more
   than one connected channel somehow matches). Disconnecting a channel now
   clears `external_account_id`, freeing the account for a different
   workspace ("one workspace at a time," not "ever"). See
   `tests/Feature/Webhooks/MetaTenantRoutingTest.php` and
   `tests/Feature/Integrations/MetaAccountClaimTest.php`.
2. **Admin aggregate query bug**: `DashboardController`'s connected-
   integration counts used `Integration::withoutGlobalScopes()->whereHas('channels', ...)`
   — but the `channels` relation's query still carried `Channel`'s own
   `BelongsToWorkspace` scope, which (per §13 below) resolves to "match
   nothing" for a platform admin with no `current_workspace_id`. Every
   count silently came back as 0 regardless of real data. Fixed by adding
   `->withoutGlobalScopes()` inside the `whereHas` closure too. The same
   bug was found and fixed in `SupportContentController` (customer/channel
   eager-loads on conversation/order/appointment detail and browse views)
   while building the support browser below — see
   `tests/Feature/Admin/DashboardAggregateTest.php` and
   `tests/Feature/Admin/SupportBrowserTest.php`.
3. **Support content browser**: `/admin/workspaces/{workspace}/support`
   (`SupportContentController::browse`), available only during a valid
   support session for that exact workspace. Read-only tabs — Pogovori,
   Stranke, Naročila, Termini — each a minimal-metadata list (no decrypting
   every message body just to render a list) linking into the existing
   per-resource detail pages. An Appointment support detail page
   (`Admin/Support/Appointment.vue`, `SupportContentController::appointment`)
   was added — it was the one resource type missing a detail view. Browsing
   the list itself is not separately audited (consistent with the app's
   normal index-page-isn't-logged pattern); opening an individual resource
   is. `admin.current_workspace_id` is never touched and there is still no
   impersonation — every query is explicitly re-constrained to the support
   session's workspace, not switched into it.
4. **Support session binding**: see the updated §10 above —
   `SupportSessionManager::current()` now verifies the session belongs to
   the requesting admin, and `start()` closes any prior active session for
   that admin first. See `tests/Feature/Admin/SupportSessionBindingTest.php`.
5. **Technical scope removed**: see the updated §9 above.
6. **Demo deletion centralized**: `App\Services\DemoWorkspaceCleanupService`
   is now the single implementation both `demos:cleanup` and
   `Admin\WorkspaceController::destroyDemo` (manual admin deletion) call —
   previously the admin manual-deletion path only deleted the `Workspace`
   row itself, skipping demo-user cleanup and private-attachment file
   cleanup that the scheduled command already did. See
   `tests/Feature/Admin/DemoSafetyTest.php`.
7. **Empty-string encryption gap**: see `docs/data-security.md` for the
   `security:encrypt-sensitive-data` fix (legacy `''` rows were previously
   skipped entirely, which would throw `DecryptException` on read once the
   `encrypted` cast went live).

## Explicit confirmations

- Admin cannot normally read message bodies. ✅ (only inside an active
  `workspace_content` support session, and only via the dedicated
  `SupportContentController` routes, each of which is audited)
- Admin cannot normally read customer notes. ✅ (same mechanism)
- Integration tokens never appear in admin UI. ✅ (`$hidden` on the model +
  explicit allow-listed summary arrays; verified by test)
- There is no unrestricted impersonation feature. ✅ (never built; V1 uses
  temporary, scoped, audited support sessions instead)
- Support content access requires a valid temporary grant. ✅ (enforced in
  `SupportSessionManager::require`, re-checked against the DB every request)
- Expired/revoked access is enforced server-side. ✅ (not UI-only; verified
  by "revoked grant stops working immediately, even mid-session" and
  "an expired session cannot continue accessing content" tests)
- Every support content session is audited. ✅ (`support_session.started`/
  `ended`/`expired` plus one `support.content_access` row per resource open)
