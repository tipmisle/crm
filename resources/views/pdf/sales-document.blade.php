<!DOCTYPE html>
<html lang="sl">
<head>
<meta charset="UTF-8">
<style>
    @page { margin: 28px 36px; }
    body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; color: #1f2430; }
    .muted { color: #6b7280; }
    table { border-collapse: collapse; width: 100%; }
    .header-table td { vertical-align: top; }
    .logo { max-height: 60px; max-width: 200px; }
    h1 { font-size: 18px; margin: 0 0 2px 0; }
    .doc-number { font-size: 13px; margin: 0 0 12px 0; }
    .section-title { font-size: 9px; text-transform: uppercase; letter-spacing: .04em; color: #6b7280; margin-bottom: 4px; }
    .party-table td { padding-bottom: 2px; }
    .dates-table { margin-top: 16px; margin-bottom: 16px; }
    .dates-table td { padding: 3px 12px 3px 0; }
    .items-table { margin-top: 6px; }
    .items-table th { text-align: left; font-size: 9px; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #d1d5db; padding: 4px 6px; }
    .items-table td { padding: 6px; border-bottom: 1px solid #eef0f3; }
    .items-table .num { text-align: right; }
    .totals-table { width: 260px; margin-left: auto; margin-top: 10px; }
    .totals-table td { padding: 3px 0; }
    .totals-table .grand td { font-size: 13px; font-weight: bold; border-top: 1px solid #1f2430; padding-top: 6px; }
    .payment-box { margin-top: 22px; padding: 12px 14px; border: 1px solid #d1d5db; border-radius: 4px; }
    .payment-box table td { vertical-align: top; }
    .qr { width: 140px; height: 140px; }
    .footer { margin-top: 26px; font-size: 9px; color: #6b7280; }
    .vat-note { margin-top: 8px; font-size: 9px; color: #6b7280; }
    .correction-banner { margin-bottom: 14px; padding: 10px 14px; border: 1px solid #b91c1c; border-radius: 4px; background: #fef2f2; color: #7f1d1d; font-size: 10px; }
    .correction-banner strong { display: block; font-size: 11px; margin-bottom: 2px; }
</style>
</head>
<body>

@if(!empty($is_correction))
    <div class="correction-banner">
        <strong>Ta dokument je DOBROPIS / STORNO računa {{ $corrects_document_number ?? '' }} z dne {{ $corrects_issued_at ?? '' }}.</strong>
        Vsi zneski v tem dokumentu obrnejo prvotno izdani račun in ga v celoti razveljavijo.
        @if(!empty($correction_reason))
            Razlog: {{ $correction_reason }}
        @endif
    </div>
@endif

<table class="header-table">
    <tr>
        <td style="width: 55%;">
            @if(!empty($seller['logo_url']))
                <img class="logo" src="{{ $seller['logo_url'] }}">
            @endif
            <div style="margin-top: 8px;">
                <strong>{{ $seller['company_name'] ?? '' }}</strong><br>
                {{ $seller['address_line'] ?? '' }}<br>
                {{ trim(($seller['postal_code'] ?? '').' '.($seller['city'] ?? '')) }}<br>
                {{ $seller['country'] ?? '' }}<br>
                @if(!empty($seller['tax_number']))
                    ID za DDV / davčna št.: {{ $seller['tax_number'] }}<br>
                @endif
                @if(!empty($seller['email']))
                    {{ $seller['email'] }}<br>
                @endif
                @if(!empty($seller['phone']))
                    {{ $seller['phone'] }}
                @endif
            </div>
        </td>
        <td style="width: 45%; text-align: right;">
            <h1>{{ $type_label }}</h1>
            <p class="doc-number">{{ $document_number }}</p>
            <div class="section-title" style="text-align:right;">Stranka</div>
            <div class="party-table">
                <strong>{{ $customer['name'] ?? '' }}</strong><br>
                @if(!empty($customer['address_line']))
                    {{ $customer['address_line'] }}<br>
                @endif
                @if(!empty($customer['postal_code']) || !empty($customer['city']))
                    {{ trim(($customer['postal_code'] ?? '').' '.($customer['city'] ?? '')) }}<br>
                @endif
                @if(!empty($customer['tax_number']))
                    Davčna št.: {{ $customer['tax_number'] }}
                @endif
            </div>
        </td>
    </tr>
</table>

<table class="dates-table">
    <tr>
        <td><span class="muted">Datum izdaje</span><br>{{ $issued_at }}</td>
        @if(!empty($service_date))
            <td><span class="muted">Datum opravljene storitve</span><br>{{ $service_date }}</td>
        @endif
        @if(!empty($due_date))
            <td><span class="muted">Rok plačila</span><br>{{ $due_date }}</td>
        @endif
        @if(!empty($seller['place_of_issue']))
            <td><span class="muted">Kraj izdaje</span><br>{{ $seller['place_of_issue'] }}</td>
        @endif
    </tr>
</table>

<table class="items-table">
    <thead>
    <tr>
        <th>Postavka</th>
        <th class="num">Količina</th>
        <th class="num">Enota</th>
        <th class="num">Cena/enoto {{ ($vat_registered && ($prices_include_vat ?? true)) ? '(z DDV)' : (($vat_registered) ? '(brez DDV)' : '') }}</th>
        @if($vat_registered)
            <th class="num">DDV</th>
        @endif
        <th class="num">Skupaj</th>
    </tr>
    </thead>
    <tbody>
    @foreach($line_items as $item)
        <tr>
            <td>{{ $item['description'] }}</td>
            <td class="num">{{ rtrim(rtrim(number_format((float) $item['quantity'], 2, ',', '.'), '0'), ',') }}</td>
            <td class="num">{{ $item['unit'] ?? '' }}</td>
            <td class="num">{{ number_format((float) $item['unit_price'], 2, ',', '.') }} {{ $currency }}</td>
            @if($vat_registered)
                <td class="num">{{ $item['vat_rate'] ?? 0 }}%</td>
            @endif
            <td class="num">{{ number_format((float) $item['line_total'], 2, ',', '.') }} {{ $currency }}</td>
        </tr>
    @endforeach
    </tbody>
</table>

@if($vat_registered && !empty($tax_breakdown) && count($tax_breakdown) > 1)
    <table class="items-table" style="margin-top: 14px;">
        <thead>
        <tr>
            <th>Stopnja DDV</th>
            <th class="num">Osnova</th>
            <th class="num">DDV</th>
            <th class="num">Skupaj</th>
        </tr>
        </thead>
        <tbody>
        @foreach($tax_breakdown as $row)
            <tr>
                <td>{{ $row['vat_rate'] }}%</td>
                <td class="num">{{ number_format((float) $row['net'], 2, ',', '.') }} {{ $currency }}</td>
                <td class="num">{{ number_format((float) $row['vat'], 2, ',', '.') }} {{ $currency }}</td>
                <td class="num">{{ number_format((float) $row['gross'], 2, ',', '.') }} {{ $currency }}</td>
            </tr>
        @endforeach
        </tbody>
    </table>
@endif

<table class="totals-table">
    @if($vat_registered)
        <tr><td class="muted">Osnova</td><td class="num">{{ number_format((float) $subtotal, 2, ',', '.') }} {{ $currency }}</td></tr>
        <tr><td class="muted">DDV</td><td class="num">{{ number_format((float) $vat_total, 2, ',', '.') }} {{ $currency }}</td></tr>
    @endif
    <tr class="grand"><td>Skupaj</td><td class="num">{{ number_format((float) $total, 2, ',', '.') }} {{ $currency }}</td></tr>
</table>

@if(!$vat_registered)
    <p class="vat-note">{{ $vat_exempt_note ?: 'Izdajatelj ni zavezanec za DDV. DDV ni obračunan.' }}</p>
@endif

<div class="payment-box">
    <table>
        <tr>
            <td style="width: {{ !empty($qr_data_uri) ? '65%' : '100%' }};">
                <div class="section-title">Plačilo — bančno nakazilo</div>
                <table>
                    <tr><td class="muted" style="width:110px;">Prejemnik</td><td>{{ $seller['company_name'] ?? '' }}</td></tr>
                    <tr><td class="muted">IBAN</td><td>{{ $payment['iban'] ?? '—' }}</td></tr>
                    @if(!empty($seller['bank_name']))
                        <tr><td class="muted">Banka</td><td>{{ $seller['bank_name'] }}</td></tr>
                    @endif
                    <tr><td class="muted">Znesek</td><td>{{ number_format((float) $total, 2, ',', '.') }} {{ $currency }}</td></tr>
                    <tr><td class="muted">Rok plačila</td><td>{{ $due_date ?? '—' }}</td></tr>
                    <tr><td class="muted">Namen / sklic</td><td>{{ $payment['purpose'] ?? '' }}</td></tr>
                </table>
            </td>
            @if(!empty($qr_data_uri))
                <td style="width: 35%; text-align: right;">
                    <img class="qr" src="{{ $qr_data_uri }}">
                </td>
            @endif
        </tr>
    </table>
</div>

@if(!empty($seller['footer_text']))
    <div class="footer">{{ $seller['footer_text'] }}</div>
@endif

</body>
</html>
