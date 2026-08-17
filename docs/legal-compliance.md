# Legal Compliance: Public Pages, Versioning, Consent

Internal reference. Not a legal conclusion or legal advice — describes what
this codebase implements, for engineers maintaining it. Companion documents:
[`docs/data-lifecycle.md`](./data-lifecycle.md) (controller/processor role
split, retention, export/deletion mechanics), [`docs/data-security.md`](./data-security.md)
(encryption/security facts referenced in the TOM annex).

## 1. Purpose and scope

This document is the internal maintenance reference for the public legal
layer (Terms, Privacy Policy, Cookie Policy, DPA, Provider Info,
Subprocessors) and the registration-acceptance mechanism. It is not the
public pages themselves, and nothing in it should be treated as a
substitute for actual legal review before launch.

## 2. Document inventory

| Document | Route | Component | Version config key |
|---|---|---|---|
| Pogoji poslovanja (Terms) | `/pogoji-poslovanja` (`legal.terms`) | `resources/js/Pages/Legal/Terms.vue` | `legal.terms_version` |
| Politika zasebnosti (Privacy) | `/zasebnost` (`legal.privacy`) | `Legal/Privacy.vue` | `legal.privacy_version` |
| Politika piškotkov (Cookies) | `/piskotki` (`legal.cookies`) | `Legal/Cookies.vue` | `legal.cookie_version` |
| Dogovor o obdelavi osebnih podatkov (DPA) | `/obdelava-osebnih-podatkov` (`legal.dpa`) | `Legal/Dpa.vue` | `legal.dpa_version` |
| Podatki o ponudniku (Provider info) | `/podatki-o-ponudniku` (`legal.provider`) | `Legal/Provider.vue` | — (factual, not versioned as a "document") |
| Podobdelovalci (Subprocessors) | `/podobdelovalci` (`legal.subprocessors`) | `Legal/Subprocessors.vue` | — (kept current, not versioned) |

All six routes are registered in `routes/web.php` above the `auth`
middleware group — they must never redirect an authenticated user away
(unlike `MarketingController::home()`), and must never require a workspace.

**To bump a version**: edit the relevant copy in the Vue page, then update
the corresponding `LEGAL_*_VERSION` env var (or the default in
`config/legal.php`) to a new date string (`YYYY-MM-DD`), redeploy. Never
derive a version from a deployment timestamp or client input — see
`RegisteredUserController::store()`, which reads `config('legal.terms_version')`
/`config('legal.dpa_version')` directly.

## 3. Controller vs. processor role split

See `docs/data-lifecycle.md` §2 for the canonical statement. Terms/Privacy/DPA
copy paraphrases this split in Slovenian but does not duplicate it verbatim —
if the underlying model changes, update `docs/data-lifecycle.md` first, then
propagate to the public copy.

## 4. `legal:check`

`php artisan legal:check` (`app/Console/Commands/CheckLegalConfig.php`)
validates `config('legal.*')`:

- **Required** (exit 1 if missing): `company_name`, `registered_address`,
  `registration_number`, `tax_number`, `legal_email`, and all four version
  keys. Also requires `vat_number` when `vat_registered` is true.
- **Advisory** (warns, does not fail): `dpo_contact`, `competent_court`.

`.github/workflows/ci.yml` runs the PHP/JS test suites and `npm run build`
on every push, but does **not** currently invoke `legal:check` or
`deploy:check` — both remain manual pre-deploy gates, run by the operator
per the checklist in `docs/production-launch.md` (steps 10–11), not
enforced automatically in CI.

## 5. Registration acceptance model

`RegisteredUserController::store()` requires `terms_dpa_accepted` (validation
rule `accepted`) and, on success, records two `LegalAcceptance` rows (`Terms`
and `Dpa`) via `LegalAcceptance::record()`, versioned from server-side config.
**Privacy Policy is never recorded as an acceptance** — it's a notice, not a
contract; `LegalDocument` deliberately has no `Privacy` case.

`DemoController::create()` is an entirely separate code path (synthetic
email/password, `is_demo=true`, zero real personal data) that never touches
`RegisteredUserController` — demo accounts never get a `LegalAcceptance` row,
by construction, not by a conditional flag.

Existing seeded/dev/demo accounts are unaffected — this milestone only wires
acceptance into the registration flow going forward; there is no reacceptance
enforcement for pre-existing accounts.

**Future reacceptance on material version change**: not implemented. A
future milestone could compare a logged-in user's latest `LegalAcceptance`
version per document against `config('legal.terms_version')`/`dpa_version`
and prompt for reacceptance if they differ — this needs a UX decision (block
vs. banner) and is out of scope here.

## 6. Cookie/consent decision record

**No consent banner is implemented.** Audited and confirmed at this
milestone: no non-essential cookies, no `localStorage`/`sessionStorage`
usage, and `resources/js/lib/analytics.ts` is a no-op stub (only pushes to
`window.dataLayer` if it already exists — no GTM/GA/Meta Pixel script is
loaded anywhere). The only cookies set are Laravel's session cookie,
`XSRF-TOKEN`, and (only if the user ticks "Zapomni si me" at login,
`LoginRequest`/`Auth::attempt($credentials, $remember)`) Laravel's own
`remember_web_...` persistent auth cookie — all three are strictly
necessary for functionality the user explicitly requested, so no consent
is legally required under ZEKom-2's necessary-cookie exemption. Building a
banner over nothing would be decorative, not compliance. The Rubik font is
self-hosted via `@fontsource-variable/rubik` (see `resources/css/app.css`)
— no remote Google Fonts request is made, so `Legal/Cookies.vue`'s former
"Pisave tretjih ponudnikov" disclosure is now a statement that the font is
self-hosted, not a disclosure of a third-party IP request.

**Trigger condition — re-read before adding any of the following:** if
Google Analytics/GTM, a marketing pixel, or any non-essential client-side
storage is ever added to this app, a real consent-manager UI becomes
necessary **before** that script/storage initializes. At that point:
extend `resources/js/lib/analytics.ts`'s gating so `pushEvent()` checks a
stored consent decision, build a banner with "Sprejmi vse" / "Zavrni
nenujne" / "Nastavitve" (equally easy accept/reject, no pre-ticked optional
categories), and update `Legal/Cookies.vue`'s cookie table (which is
already structured as a small, per-cookie list — extend it, don't rewrite
it) plus `config('legal.cookie_version')`.

## 7. Subprocessor review process

`config('legal.php')` now keeps **two separate lists**, rendered as two
separate sections by `Legal/Subprocessors.vue` — do not merge them back
into one:

- `config('legal.subprocessors')` — Article 28 subprocessors that actually
  receive a workspace's **customer** data. Currently only Meta (Instagram
  DM / Facebook Messenger Graph API — message content, customer
  identifiers). This is the list `Legal/Dpa.vue` §10 refers to as
  authorized subprocessors.
- `config('legal.account_billing_providers')` — providers that only ever
  process the Beležka **user's/workspace's own** account or billing data,
  never a workspace's customer data. Currently Stripe (subscription
  billing) and the generic "browser push notification providers" entry
  (Web Push/VAPID endpoint for the logged-in user, not a customer). These
  are disclosed in `Legal/Privacy.vue` §13 as recipients, but must never be
  listed as Article 28 subprocessors — they don't process customer data.

Each entry also carries `location`/`transfer_mechanism`/
`transfer_more_info_url`, left `null` (rendered as "NEEDS OWNER INPUT")
until confirmed against that provider's own current published terms —
never inferred or assumed.

**Checklist for future integration PRs**: does this PR add a new external
service that receives personal data? If yes, first ask *whose* data it
receives:
- Receives a workspace's **customer** data (hosting, database, backups,
  email delivery to customers, error tracking that sees message content,
  etc.) → add to `config('legal.subprocessors')`, update `Legal/Dpa.vue`
  §11 and the notice-before-use language in §10 if this is a new/replacement
  provider, and check `Legal/Subprocessors.vue`'s transfer-mechanism column.
- Receives only the **Beležka user's own** account/billing data → add to
  `config('legal.account_billing_providers')` instead, and confirm
  `Legal/Privacy.vue` §13 still accurately describes it.

Per `Legal/Subprocessors.vue` §1 and `Legal/Dpa.vue` §10: the public
Subprocessors page is **not** the sole notification mechanism for a new or
replacement Article 28 subprocessor — Beležka commits to notifying the
workspace owner by email and/or in-app notice in advance, with a
reasonable opportunity to object, before such a subprocessor starts
processing that workspace's customer data. No consent-workflow UI is built
for this yet — it's an operational commitment to notify, not an automated
gate; document/track how notification is actually sent when this is first
exercised.

## 8. NEEDS OWNER INPUT tracker

Living checklist — update as the owner supplies each fact, then set the
corresponding env var and re-run `legal:check` (and `deploy:check` for
infrastructure/Stripe facts).

**COMPANY / PROVIDER** (`config/legal.php`, blocks `legal:check` until set):
`LEGAL_COMPANY_NAME`, `LEGAL_REGISTERED_ADDRESS`, `LEGAL_REGISTRATION_NUMBER`,
`LEGAL_TAX_NUMBER`, `LEGAL_EMAIL`, plus `LEGAL_VAT_NUMBER` if
`LEGAL_VAT_REGISTERED=true`.

**COMMERCIAL / PRICING** (`config/billing.php`, blocks `legal:check` once a
price is displayed):
- `BILLING_DISPLAY_PRICE` — final production monthly price. Unset today;
  the marketing page and activation page both omit the price line rather
  than show a placeholder, and both now read from this single config key
  (`MarketingController::home()` / `Billing\ActivationController::edit()`)
  — no more separate hardcoded marketing price.
- `BILLING_DISPLAY_PRICE_VAT_INCLUDED` — whether that price is VAT-inclusive.
  `legal:check` fails if `BILLING_DISPLAY_PRICE` is set but this is still
  unset.
- Confirm no free trial is actually wanted (none is implemented).
- Payment-failure access policy is currently `blocked`
  (`BILLING_PAST_DUE_ACCESS_POLICY`) — confirm this is the intended policy.

**INFRASTRUCTURE / TRANSFERS** (`config/legal.php` subprocessor entries,
`deploy:check`):
- Hosting/database/backup/email-delivery provider(s) that will actually
  store production Customer data — not yet chosen; once known, add as an
  Article 28 subprocessor entry (§7) with location + transfer mechanism.
- Exact Stripe legal entity/processing region and transfer mechanism for
  this account.
- Meta's current processing location and transfer mechanism (never assume
  a prior audit's finding is still current — re-verify against Meta's own
  disclosures before publishing).
- Audit/security-log retention period (`RETENTION_AUDIT_LOG_DAYS`) — still
  unset; `Legal/Privacy.vue` §15 renders this as NEEDS OWNER INPUT rather
  than inventing a period.

## 9. Test coverage map

- `tests/Feature/Legal/PublicAccessTest.php` — all 6 routes load logged
  out/in, correct version rendered, Provider page never shows a fake
  placeholder for null config.
- `tests/Feature/Legal/SecurityLeakTest.php` — no `APP_KEY`, Meta app
  secret, or bcrypt hash pattern ever appears in a legal page response.
- `tests/Feature/Legal/ContentAccuracyTest.php` — no stale "brezplačna
  uporaba" / "ni vzpostavljenega plačilnega sistema" copy anywhere in
  Terms/Privacy; no FURS/fiscalization compliance claim; DPA/Subprocessors
  correctly separate Article 28 subprocessors from account/billing
  providers; Cookies discloses the remember-me cookie.
- `tests/Feature/Console/CheckLegalConfigTest.php` — required/advisory/VAT
  conditional validation, exit codes, and the new display-price-VAT gate.
- `tests/Feature/Marketing/PricingDisplayTest.php` — marketing page and
  activation page render the same server-sourced price, no hardcoded
  placeholder string ships.
- `tests/Feature/Auth/RegistrationTest.php` — registration requires
  `terms_dpa_accepted`.
- `tests/Feature/LegalAcceptanceTest.php` — registration records exactly
  Terms+DPA (not Privacy) with server-side-sourced versions; no rows created
  without acceptance.
- No consent-banner tests — N/A per §6's decision, not an oversight.

## 10. Known gaps / explicitly deferred

- No consent banner (§6) — correct today, re-evaluate per the trigger
  condition above.
- Article 28 subprocessors list is incomplete pending hosting/email/backup
  vendor decisions — see NEEDS OWNER INPUT (§8). `account_billing_providers`
  (Stripe, push) is believed complete as of this milestone.
- No automated re-acceptance flow for a future material Terms/DPA version
  change (§5) — manual/future work.
- `legal:check`/`deploy:check` run in CI for tests/build but are not yet
  wired as blocking CI steps — they remain manual operator steps per
  `docs/production-launch.md`.
- Audit/security-log retention period is undecided — tracked as NEEDS OWNER
  INPUT (§8), not silently assumed.
