# Pre-Launch Security Checklist

Internal reference. Distinguishes work verifiably done **in this
repository's code** from work that can only be verified/completed in
**production infrastructure** — do not mark an infrastructure item complete
without independently verifying it against the actual production
environment.

Related: `docs/admin-security.md` (platform admin / support access),
`docs/data-security.md` (encryption inventory),
`docs/encryption-key-runbook.md`.

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
