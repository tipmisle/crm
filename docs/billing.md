# Billing: Stripe Subscription Foundation

Internal reference. Not legal or tax advice. Describes what this codebase
implements, for engineers maintaining it. Companion documents:
[`docs/legal-compliance.md`](./legal-compliance.md) (Terms/subprocessor
alignment), [`docs/data-lifecycle.md`](./data-lifecycle.md) (workspace
deletion — kept strictly separate from subscription cancellation, see Part 9
below), [`docs/pre-launch-security.md`](./pre-launch-security.md) (billing
launch checklist).

## 1. Overview

Beležka has one simple recurring monthly subscription. The **Workspace**
(the paying business) is the Cashier billable entity — never the User. One
workspace has at most one subscription. The public demo never touches
Stripe. Payment is collected exclusively via Stripe Checkout (hosted,
server-created sessions) and managed via the Stripe Customer Portal — this
app never receives or stores raw card data.

**Explicitly not implemented this milestone**: FURS fiscalization,
Slovenian invoice numbering, invoice PDF generation, annual billing,
coupons/promo codes, seat/usage-based pricing, multiple tiers, a free
trial, referral/affiliate billing, Stripe Connect. Stripe Tax is
deliberately never enabled (`Cashier::$calculatesTaxes` stays at its
default `false` — nothing in this codebase calls `Cashier::calculateTaxes()`).

## 2. Environment variables

```
STRIPE_KEY=
STRIPE_SECRET=
STRIPE_WEBHOOK_SECRET=
STRIPE_BELEZKA_MONTHLY_PRICE_ID=
BILLING_CURRENCY=eur
BILLING_DISPLAY_PRICE=
BILLING_PERIOD_LABEL="mesečno"
BILLING_PAST_DUE_ACCESS_POLICY=blocked
BILLING_POST_SUBSCRIPTION_ACCESS_POLICY=blocked
```

`STRIPE_KEY`/`STRIPE_SECRET`/`STRIPE_WEBHOOK_SECRET` are read by Cashier's
own published `config/cashier.php` — never duplicated into
`config/billing.php`, which stays scoped to this app's own billing
policy/product config (price ID, display price, access policies).

## 3. Stripe Dashboard TEST MODE setup checklist

1. Create a Product and a monthly recurring Price in TEST mode. Copy the
   Price ID into `STRIPE_BELEZKA_MONTHLY_PRICE_ID`.
2. Configure a webhook endpoint pointing at `POST /stripe/webhook`,
   subscribed to at minimum: `customer.subscription.created`,
   `customer.subscription.updated`, `customer.subscription.deleted`,
   `invoice.payment_succeeded`, `invoice.payment_failed`. Copy the signing
   secret into `STRIPE_WEBHOOK_SECRET`.
3. Configure the Customer Portal: allow payment-method updates and
   cancellation; do NOT enable plan switching (only one plan exists),
   coupons, pause, or annual billing unless a product decision explicitly
   changes this.
4. Register a real test account (`/registracija`), confirm the Terms/DPA
   checkbox flow, land on `/billing/activate`.
5. Click "Nadaljuj na plačilo", complete Checkout with a Stripe test card
   (`4242 4242 4242 4242`, any future expiry/CVC).
6. Confirm the `customer.subscription.created`/`invoice.payment_succeeded`
   webhooks land and the workspace's subscription state resolves to
   `active` (`WorkspaceSubscriptionStateService::for()`).
7. Confirm the app unlocks — `/danes`, `/sporocila`, etc. no longer redirect to
   `/billing/activate`.
8. Open Settings → Naročnina → "Upravljaj naročnino" and confirm the
   Customer Portal opens correctly.
9. Cancel at period end via the Portal; confirm the app still shows access
   (Canceling state) and the billing banner appears.
10. Resume the subscription via the Portal before the period ends; confirm
    access continues uninterrupted and `billing.subscription_resumed` is
    audit-logged.
11. Use Stripe's test card `4000 0000 0000 0341` (or the Dashboard's
    "simulate a failed payment" tooling) to trigger `invoice.payment_failed`;
    confirm the app surfaces "Plačila ni bilo mogoče izvesti."
12. Update the payment method via the Portal and confirm subsequent
    renewal succeeds.
13. Replay a webhook event from the Stripe Dashboard's event log; confirm
    no duplicate audit rows are created (`stripe_webhook_events` unique
    constraint).
14. Confirm visiting `/registracija` → `/demo` never creates a Stripe customer
    or subscription for the demo workspace.

## 4. Local development

Use the [Stripe CLI](https://stripe.com/docs/stripe-cli) to forward
webhooks to your local app: `stripe listen --forward-to
localhost:8000/stripe/webhook`, and copy the CLI's printed webhook signing
secret into `STRIPE_WEBHOOK_SECRET` for local testing. No development-only
billing bypass is shipped — Stripe's own TEST MODE is the supported way to
develop against billing locally.

## 5. Access-state reference

| Cashier/Stripe state | `SubscriptionAccessState` | Grants access? | Slovenian UI copy |
|---|---|---|---|
| No subscription row | `no_subscription` | No | "Za uporabo Beležke aktiviraj naročnino." |
| `incomplete` | `incomplete` | No | "Naročnina ni bila dokončana." |
| `active`, no `cancel_at_period_end` | `active` | Yes | "Beležka je aktivna." |
| `cancel_at_period_end` scheduled, still in period | `canceling` | Yes | "Naročnina bo potekla [datum]." |
| `past_due` | `past_due` | Only if `BILLING_PAST_DUE_ACCESS_POLICY=grace` (default `blocked`) | "Plačila ni bilo mogoče izvesti. Posodobi način plačila." |
| `canceled`/ended | `canceled` | No | "Za uporabo Beležke aktiviraj naročnino." |

Demo workspaces (`is_demo=true`) bypass this entirely — the gating
middleware checks `is_demo` before consulting subscription state at all.

## 6. Registration → Checkout flow

1. `RegisteredUserController::store()` creates the User, Workspace (owner),
   records Terms/DPA `LegalAcceptance`, then redirects to
   `route('billing.activate')` instead of the dashboard.
2. `Billing\ActivationController::edit()` shows plan/price/features.
3. `Billing\ActivationController::checkout()` — owner-only, 404 for demo,
   no-ops back to Settings/Billing if already active — creates a Stripe
   Checkout Session server-side via
   `$workspace->newSubscription(config('billing.subscription_name'), config('billing.monthly_price_id'))->checkout([...])`
   and redirects to Stripe's hosted page. The price ID is read only from
   config — never from the request.
4. On success, Stripe redirects to `billing.activate.success`, which does
   **not** itself grant access — it just redirects into the app with a
   "processing" message.
5. The `customer.subscription.created`/`invoice.payment_succeeded` webhook
   (near-synchronous, not guaranteed instant) is what actually activates
   the subscription server-side.
6. **Known race**: if the webhook hasn't landed by the time the success
   redirect completes, `EnsureWorkspaceHasActiveSubscription` simply
   bounces the user back to `/billing/activate` once more until it does.
   This is an accepted, small UX tradeoff — access must never depend on
   the redirect alone (Stripe recommends against trusting client-side
   Checkout success for exactly this reason).

## 7. Cancellation vs. deletion — kept strictly separate

`WorkspaceDeletionService::delete()` (the only path to permanent data
deletion) best-effort-cancels the Stripe subscription (`cancelNow()`,
immediate — the one place that's correct, since the workspace is being
destroyed regardless) as a non-blocking step before the DB transaction.
**The reverse never happens**: `StripeWebhookController::handleCustomerSubscriptionDeleted()`
only ever updates local subscription-status columns via Cashier's own
`parent::` sync — it never calls `WorkspaceDeletionService` or touches any
workspace/customer/message data. A canceled or fully-ended subscription
results in restricted *access* (`NoSubscription`/`Canceled` state), not
data loss — the workspace, its customers, and its conversations remain
exactly as governed by `docs/data-lifecycle.md`'s explicit deletion flow.

## 8. Out of scope — do not claim otherwise

This milestone implements subscription + payment + access state. It does
**not** implement Slovenian invoice issuance or FURS fiscalization. A
`BillingPaymentSucceeded` event (workspace ID, Stripe customer/subscription/
invoice IDs, amount, currency, paid-at) is dispatched on every successful
invoice payment specifically so a *future* invoicing milestone can consume
it — no listener is registered yet. If the Stripe Customer Portal exposes
Stripe's own invoice history to a customer, that must not be presented in
the UI as a legally compliant Slovenian fiscal invoice until that future
milestone ships.

## 9. Finalized launch pricing

**Resolved, production values** — set via `BILLING_DISPLAY_PRICE="9,90 €"`,
`BILLING_DISPLAY_PRICE_VAT_INCLUDED=true`, `BILLING_PERIOD_LABEL="mesečno"`.
Both `MarketingController::home()` and `Billing\ActivationController::edit()`
read the same `config('billing.*')` keys, so the marketing page and the
activation page always show the identical price and VAT-inclusive note
("Cena vključuje DDV."). `legal:check` now requires both
`billing.display_price` and `billing.display_price_vat_included` to be
set — a missing launch price/VAT treatment fails the check, not just a
price set without its VAT flag. `Billing\ActivationController::checkout()`
additionally refuses to start a Stripe Checkout session in production
(`APP_ENV=production`) if either value is missing, even if
`STRIPE_BELEZKA_MONTHLY_PRICE_ID` is configured.

Current launch commercial model: **1 subscription = 1 workspace =
9,90 €/month**, VAT included. A future multi-workspace pricing tier
(first workspace 9,90 €/month, each additional workspace +6,90 €/month)
is a decided product direction but explicitly **not implemented** —
no Stripe quantity billing, no extra Prices, no multi-workspace account
logic exists yet. When that milestone is built, it needs its own Stripe
Price(s) and a decision on how `config('billing.monthly_price_id')`
generalizes beyond the current single-price model.

## 9a. NEEDS OWNER INPUT

**COMMERCIAL**
- Confirm no free trial is actually wanted (none is implemented).
- Cancellation policy specifics: self-serve via Portal vs. contact-only;
  immediate vs. period-end (period-end is what's implemented as the
  in-app default).
- Payment-failure grace period — none invented;
  `BILLING_PAST_DUE_ACCESS_POLICY` defaults to `blocked`.
- Discounts/coupons/refunds — explicitly out of scope this milestone.

**TAX**
- VAT registration status, VAT-inclusive/exclusive display, final amount.
- Legal seller identity — ties to `config/legal.php`'s currently-empty
  `company_name`/`registered_address`/etc.

**LEGAL**
- `resources/js/Pages/Legal/Terms.vue` §9 ("Naročnina in plačilo") now
  correctly states Beležka is paid, describes Stripe Checkout/Portal,
  automatic recurring billing, period-end cancellation, and that
  cancellation does not delete workspace data — see
  `docs/legal-compliance.md` §8. It intentionally does not hardcode a
  price; it references the price shown on the activation page
  (`config('billing.display_price')`) and its VAT treatment
  (`config('billing.display_price_vat_included')`), both still unset.
- Stripe is listed under `config('legal.account_billing_providers')`, not
  the Article 28 `config('legal.subprocessors')` list — Stripe only ever
  receives the paying workspace's own billing data, never a workspace's
  customer data. Its `location`/`transfer_mechanism` fields are left
  `null` pending confirmation of the exact Stripe legal entity/region —
  see `docs/legal-compliance.md` §7–8.
