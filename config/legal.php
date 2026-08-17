<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Provider identity
    |--------------------------------------------------------------------------
    |
    | Factual details about the company operating Beležka, shown on the
    | public "Podatki o ponudniku" page and referenced from Terms/Privacy/
    | DPA. NEVER hardcode a fake/placeholder value here — every key
    | defaults to null, and the public page omits rows for null values
    | rather than showing a placeholder. See docs/legal-compliance.md.
    */
    'company_name' => env('LEGAL_COMPANY_NAME'),
    'company_legal_form' => env('LEGAL_COMPANY_LEGAL_FORM'),
    'registered_address' => env('LEGAL_REGISTERED_ADDRESS'),
    'registration_number' => env('LEGAL_REGISTRATION_NUMBER'),
    'tax_number' => env('LEGAL_TAX_NUMBER'),
    'vat_registered' => (bool) env('LEGAL_VAT_REGISTERED', false),
    'vat_number' => env('LEGAL_VAT_NUMBER'),
    'legal_email' => env('LEGAL_EMAIL'),
    'support_email' => env('LEGAL_SUPPORT_EMAIL', env('LEGAL_EMAIL')),
    'dpo_contact' => env('LEGAL_DPO_CONTACT'),

    /*
    |--------------------------------------------------------------------------
    | Legal document versions
    |--------------------------------------------------------------------------
    |
    | Server-side source of truth for every public legal document's
    | "Zadnja posodobitev" date and the version recorded against a user's
    | LegalAcceptance row at registration. Never derive a version from a
    | client-submitted value or a deployment timestamp — bump these
    | explicitly (and only) when a document materially changes.
    */
    'terms_version' => env('LEGAL_TERMS_VERSION', '2026-08-17'),
    'dpa_version' => env('LEGAL_DPA_VERSION', '2026-08-17'),
    'privacy_version' => env('LEGAL_PRIVACY_VERSION', '2026-08-17'),
    'cookie_version' => env('LEGAL_COOKIE_VERSION', '2026-08-17'),

    /*
    |--------------------------------------------------------------------------
    | Jurisdiction
    |--------------------------------------------------------------------------
    */
    'governing_law' => env('LEGAL_GOVERNING_LAW', 'Republika Slovenija'),
    'competent_court' => env('LEGAL_COMPETENT_COURT'),

    /*
    |--------------------------------------------------------------------------
    | Supervisory authority
    |--------------------------------------------------------------------------
    |
    | Fixed — not owner-dependent, applies to every Slovenian controller.
    */
    'supervisory_authority_name' => 'Informacijski pooblaščenec Republike Slovenije',
    'supervisory_authority_url' => 'https://www.ip-rs.si',

    /*
    |--------------------------------------------------------------------------
    | Article 28 subprocessors — process the business's CUSTOMER data
    |--------------------------------------------------------------------------
    |
    | Only providers that actually receive personal data Beležka processes
    | ON BEHALF OF a workspace's business (i.e. that business's customer
    | data, under the DPA) go here. This is the Article 28 subprocessor
    | list referenced by Legal/Dpa.vue §10. Do NOT add a provider here
    | just because it's integrated somewhere in the app — if it only ever
    | receives the Beležka USER's own account/billing data, it belongs in
    | 'account_billing_providers' below instead. See the audit in
    | docs/legal-compliance.md.
    |
    | 'transfer_mechanism'/'transfer_more_info_url': only set once
    | confirmed against that provider's own current, verifiable published
    | terms — never guessed or assumed to still be valid. Leave null
    | (NEEDS OWNER INPUT) rather than invent a certification or safeguard.
    | Hosting/database/backup vendors that store Customer data MUST be
    | added here once the production infrastructure choice is known —
    | tracked as NEEDS OWNER INPUT until then.
    */
    'subprocessors' => [
        [
            'name' => 'Meta Platforms, Inc.',
            'purpose' => 'Povezava z Instagram Direct in Facebook Messenger (sprejemanje in pošiljanje sporočil strank prek povezanih kanalov)',
            'data' => 'Vsebina sporočil, identifikatorji strank (Instagram/Facebook uporabniško ime, ID)',
            'location' => null, // NEEDS OWNER INPUT — confirm current processing location per Meta's own disclosures
            'transfer_mechanism' => null, // NEEDS OWNER INPUT — confirm Meta's current EU/EGP transfer safeguard before publishing a specific mechanism
            'transfer_more_info_url' => null,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Providers/recipients of the Beležka USER's own account/billing data
    |--------------------------------------------------------------------------
    |
    | These providers do not receive a workspace's customer data — they
    | only ever see the Beležka user's/workspace's own account or billing
    | identifiers. They are disclosed in Privacy §11/§12 as recipients, but
    | are NOT Article 28 subprocessors of customer data and must not be
    | listed as such in the DPA. See docs/legal-compliance.md §7 and the
    | separation required by Legal/Subprocessors.vue.
    */
    'account_billing_providers' => [
        [
            'name' => 'Stripe, Inc. / Stripe Payments Europe, Ltd.',
            'purpose' => 'Obdelava plačil naročnine na Beležko (Stripe Checkout / Customer Portal) in upravljanje plačilnih podatkov',
            'data' => 'Naziv delovnega prostora, e-poštni naslov lastnika za obračun, podatki o plačilni kartici (obdeluje izključno Stripe — Beležka jih ne vidi in ne shranjuje)',
            'location' => null, // NEEDS OWNER INPUT — exact Stripe legal entity/region for this account, see docs/billing.md
            'transfer_mechanism' => null, // NEEDS OWNER INPUT — confirm against Stripe's current DPA/terms before publishing a specific mechanism
            'transfer_more_info_url' => null,
        ],
        [
            'name' => 'Ponudniki brskalniških push obvestil (Google, Mozilla, Apple)',
            'purpose' => 'Dostava push obvestil o zapadlih opomnikih do brskalnika uporabnika Beležke',
            'data' => 'Endpoint naprave uporabnika Beležke (brez vsebine sporočila, opombe ali podatkov o stranki)',
            'location' => null,
            'transfer_mechanism' => null,
            'transfer_more_info_url' => null,
        ],
    ],

];
