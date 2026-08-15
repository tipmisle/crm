# Encryption Key Runbook

Internal operational reference. **Never paste a real key value into this
file, a commit, a log line, an issue, or a chat message.**

See `docs/data-security.md` for what is encrypted and why.

## Where the key comes from

- Application-level encryption (`'encrypted'` / `'encrypted:array'` casts on
  the fields listed in `docs/data-security.md`, plus the pre-existing
  `Integration`/`Channel` access/refresh tokens, plus session/cookie
  encryption) is all keyed by **`APP_KEY`**, read from the `.env` file /
  process environment via `config('app.key')`.
- `APP_KEY` **must** come from environment or secret-manager configuration
  in every environment beyond local dev — never hardcoded, never committed.
  `.env` is already gitignored in this repository; keep it that way.
- Generate a new key locally with `php artisan key:generate` — this
  **overwrites** the current key in `.env`. Never run this against a
  production `.env` without following the rotation procedure below; doing
  so casually will make all existing encrypted data unreadable.

## What must NEVER happen

- `APP_KEY` (or any derived value) must never be committed to git, logged,
  printed in an error page, returned by any API/admin endpoint, or pasted
  into a support ticket/chat.
- No code in this repository logs `config('app.key')`, and no admin UI
  displays it — verified as part of the `docs/data-security.md` audit. Keep
  it that way in review for any future PR that touches encryption.

## Backup & recovery requirement

- **Losing `APP_KEY` permanently loses every application-encrypted value**:
  all fields in `docs/data-security.md`'s inventory, plus the pre-existing
  integration/channel tokens, plus the ability to decrypt existing session
  cookies (users get logged out, not a data-loss issue on its own).
- Production `APP_KEY` must be stored in a secret manager (or equivalent)
  with its own backup/durability guarantee **independent of the application
  database** — a database backup alone does not help you if the key that
  unlocks it is gone.
- Access to the production `APP_KEY` should be restricted to
  deployment/infra tooling, not routinely visible to individual engineers'
  shells or committed to any per-developer config.

## Key rotation (supported mechanism for this Laravel version)

This Laravel version (12.x) supports **previous-key decryption fallback**
via the `APP_PREVIOUS_KEYS` environment variable — a comma-separated list of
older keys (in the same `base64:...` format as `APP_KEY`) that the
`Encrypter` will also attempt when decrypting, while all *new* encryption
always uses the current `APP_KEY`. This is the only key-rotation mechanism
this codebase relies on — no custom/unsupported behavior was invented.

**Rotation procedure**:

1. Confirm the current `APP_KEY` value is safely backed up (see above)
   before doing anything else.
2. Move the current `APP_KEY` value into `APP_PREVIOUS_KEYS` (append if it
   already has entries): `APP_PREVIOUS_KEYS=base64:OLD_KEY_1,base64:OLD_KEY_2`.
3. Generate a new key and set it as `APP_KEY`
   (`php artisan key:generate --show` to preview without writing, then set
   it via your secret manager/deployment config — not by running
   `key:generate` directly against production, since that also rewrites
   `.env` in place, which most deployment setups don't want).
4. Deploy with both `APP_KEY` (new) and `APP_PREVIOUS_KEYS` (containing the
   old key) set.
5. **Verify decryption immediately after rotation** (see below) before
   removing the old key from `APP_PREVIOUS_KEYS`.
6. Optionally, run a re-encryption pass so all rows move to the new key:
   this is the same idempotent pattern as
   `App\Console\Commands\EncryptSensitiveData` — since that command's
   "already encrypted?" check uses `Crypt::decryptString()` (which already
   tries `APP_PREVIOUS_KEYS`), re-running it will currently report
   everything as `already_encrypted` and do nothing. Re-encrypting under the
   new key specifically is a **future enhancement** to that command (add a
   `--force-re-encrypt` mode that always re-writes using the current key) —
   not required immediately, since `APP_PREVIOUS_KEYS` keeps old rows
   readable indefinitely, but recommended before eventually dropping the old
   key from `APP_PREVIOUS_KEYS`.
7. Only remove the old key from `APP_PREVIOUS_KEYS` once you've confirmed
   (step 5, and ideally the re-encryption pass in step 6) that nothing still
   depends on it.

## Verifying decryption after rotation

Run against a representative sample, in a shell with the new config loaded:

```
php artisan tinker
>>> \App\Models\Message::latest()->first()->body
>>> \App\Models\Customer::whereNotNull('notes')->first()->notes
>>> \App\Models\Integration::whereNotNull('access_token')->first()->access_token
```

All three should return readable plaintext, not throw
`Illuminate\Contracts\Encryption\DecryptException`. If any throws, the
relevant key (new or previous) is misconfigured — stop and fix before
proceeding, do not remove old keys from `APP_PREVIOUS_KEYS` in this state.

For a broader check, `App\Console\Commands\EncryptSensitiveData --dry-run`
can be run post-rotation: since its "already encrypted" detection also goes
through `Crypt::decryptString()`, a healthy rotation should report
`already_encrypted` for every row and zero `errors`. Any `errors` count
indicates rows that are unreadable under both the current and previous
keys.

## What happens if a key is lost with no backup

There is no recovery path — this is inherent to encryption, not a gap in
this implementation. The affected data must be treated as permanently lost:
encrypted fields would need to be nulled out (with the acknowledgment that
this destroys the content) and the incident documented. This is exactly why
the backup requirement above is non-negotiable before this feature is
considered production-ready — see `docs/pre-launch-security.md`.
