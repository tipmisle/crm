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
    'terms_version' => env('LEGAL_TERMS_VERSION', '2026-08-15'),
    'dpa_version' => env('LEGAL_DPA_VERSION', '2026-08-15'),
    'privacy_version' => env('LEGAL_PRIVACY_VERSION', '2026-08-15'),
    'cookie_version' => env('LEGAL_COOKIE_VERSION', '2026-08-15'),

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
    | Subprocessors
    |--------------------------------------------------------------------------
    |
    | Only providers verified as actually integrated go here — see the
    | audit in docs/legal-compliance.md. Do NOT add hosting/email/backup/
    | error-tracking/billing vendors until they are actually chosen and
    | configured; those are tracked as NEEDS OWNER INPUT instead.
    */
    'subprocessors' => [
        [
            'name' => 'Meta Platforms, Inc.',
            'purpose' => 'Povezava z Instagram Direct in Facebook Messenger (sprejemanje in pošiljanje sporočil strank prek povezanih kanalov)',
            'data' => 'Vsebina sporočil, identifikatorji strank (Instagram/Facebook uporabniško ime, ID)',
            'location' => null,
        ],
        [
            'name' => 'Ponudniki brskalniških push obvestil (Google, Mozilla, Apple)',
            'purpose' => 'Dostava push obvestil o zapadlih opomnikih do uporabnikovega brskalnika',
            'data' => 'Endpoint naprave (brez vsebine sporočila ali opombe)',
            'location' => null,
        ],
        [
            'name' => 'Stripe, Inc. / Stripe Payments Europe, Ltd.',
            'purpose' => 'Obdelava plačil naročnine na Beležko in upravljanje plačilnih podatkov',
            'data' => 'Naziv delovnega prostora, e-poštni naslov za obračun, podatki o plačilni kartici (obdeluje izključno Stripe — Beležka jih ne vidi in ne shranjuje)',
            'location' => null, // NEEDS OWNER INPUT — exact Stripe legal entity/region, see docs/billing.md
        ],
    ],

];
