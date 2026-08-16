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

No CI/deploy pipeline exists in this repository yet to wire this into
automatically (no `Dockerfile`/Forge/Vapor config found) — run it manually
before any production deploy, or add it as a step to whatever deploy
mechanism is eventually chosen (e.g. before `php artisan migrate --force`).

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

**No consent banner is implemented.** Audited and confirmed at the time of
this milestone: no non-essential cookies, no `localStorage`/`sessionStorage`
usage, and `resources/js/lib/analytics.ts` is a no-op stub (only pushes to
`window.dataLayer` if it already exists — no GTM/GA/Meta Pixel script is
loaded anywhere). The only cookies set are Laravel's session cookie and
`XSRF-TOKEN`, both strictly necessary — no consent is legally required for
those under ZEKom-2's necessary-cookie exemption. Building a banner over
nothing would be decorative, not compliance.

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

`config('legal.subprocessors')` is the single source of truth rendered by
`Legal/Subprocessors.vue`. Only providers actually integrated and verified in
code are listed — currently Meta (Instagram DM / Facebook Messenger Graph
API) and a generic "browser push notification providers" entry (Web Push /
VAPID, talks directly to Google/Mozilla/Apple's own push infrastructure).

**Checklist for future integration PRs**: does this PR add a new external
service that receives personal data (hosting, email, error tracking,
billing, analytics, etc.)? If yes: add an entry to
`config('legal.subprocessors')`, check whether `Legal/Dpa.vue` §11 (Prenosi
izven EGP) needs updating for a non-EU provider, and confirm whether the
provider needs disclosing in `Legal/Privacy.vue` §11/§12.

## 8. NEEDS OWNER INPUT tracker

See the implementation report for the full categorized list (COMPANY /
COMMERCIAL / INFRASTRUCTURE-SUBPROCESSORS). Living checklist — update as the
owner supplies each fact, then set the corresponding `LEGAL_*` env var and
re-run `legal:check`.

## 9. Test coverage map

- `tests/Feature/Legal/PublicAccessTest.php` — all 6 routes load logged
  out/in, correct version rendered, Provider page never shows a fake
  placeholder for null config.
- `tests/Feature/Legal/SecurityLeakTest.php` — no `APP_KEY`, Meta app
  secret, or bcrypt hash pattern ever appears in a legal page response.
- `tests/Feature/Console/CheckLegalConfigTest.php` — required/advisory/VAT
  conditional validation, exit codes.
- `tests/Feature/Auth/RegistrationTest.php` — registration requires
  `terms_dpa_accepted`.
- `tests/Feature/LegalAcceptanceTest.php` — registration records exactly
  Terms+DPA (not Privacy) with server-side-sourced versions; no rows created
  without acceptance.
- No consent-banner tests — N/A per §6's decision, not an oversight.

## 10. Known gaps / explicitly deferred

- No consent banner (§6) — correct today, re-evaluate per the trigger
  condition above.
- Subprocessors list is incomplete pending hosting/email/error-tracking/
  billing vendor decisions — see NEEDS OWNER INPUT.
- No automated re-acceptance flow for a future material Terms/DPA version
  change (§5) — manual/future work.
- `legal:check` is not wired into any CI/deploy pipeline (none exists in
  this repo yet) — run manually before production launch.
