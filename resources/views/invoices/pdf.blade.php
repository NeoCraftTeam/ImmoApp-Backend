<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Facture {{ $invoice->invoice_number }}</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.5;
        }

        /* Accent top bar */
        .accent-bar {
            width: 100%;
            height: 5px;
            background: #F6475F;
            margin-bottom: 0;
        }

        /* Header */
        .header {
            padding: 24px 40px 20px 40px;
            border-bottom: 1px solid #e2e8f0;
        }

        .header-table {
            width: 100%;
        }

        .logo-cell {
            width: 50%;
            vertical-align: middle;
        }

        .logo-cell img {
            height: 40px;
            width: auto;
        }

        .logo-text {
            font-size: 22pt;
            font-weight: bold;
            color: #F6475F;
            letter-spacing: -0.5px;
        }

        .invoice-title-cell {
            width: 50%;
            text-align: right;
            vertical-align: middle;
        }

        .invoice-title-cell h1 {
            font-size: 18pt;
            font-weight: bold;
            color: #0f172a;
            margin: 0;
        }

        .invoice-number {
            font-size: 10pt;
            color: #64748b;
            margin-top: 4px;
        }

        /* Status badge */
        .status-badge {
            display: inline-block;
            background: #f0fdf4;
            color: #166534;
            border: 1px solid #86efac;
            border-radius: 12px;
            padding: 3px 12px;
            font-size: 9pt;
            font-weight: bold;
            margin-top: 6px;
        }

        /* Main content */
        .content {
            padding: 32px 40px;
        }

        /* Two-column: Parties */
        .parties-table {
            width: 100%;
            margin-bottom: 32px;
        }

        .party-cell {
            width: 50%;
            vertical-align: top;
            padding-right: 16px;
        }

        .party-cell.right {
            padding-right: 0;
            padding-left: 16px;
            text-align: right;
        }

        .party-label {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            font-weight: bold;
            margin-bottom: 6px;
        }

        .party-name {
            font-size: 13pt;
            font-weight: bold;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .party-detail {
            font-size: 9pt;
            color: #475569;
        }

        /* Line separator */
        .divider {
            border: none;
            border-top: 1px solid #e2e8f0;
            margin: 24px 0;
        }

        /* Invoice meta (dates, etc.) */
        .meta-table {
            width: 100%;
            margin-bottom: 28px;
        }

        .meta-item {
            width: 33%;
            vertical-align: top;
        }

        .meta-label {
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #94a3b8;
            font-weight: bold;
            margin-bottom: 3px;
        }

        .meta-value {
            font-size: 10pt;
            color: #0f172a;
            font-weight: 600;
        }

        /* Items table */
        .items-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 0;
        }

        .items-table thead tr {
            background: #f8fafc;
        }

        .items-table th {
            padding: 10px 14px;
            font-size: 8pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: bold;
            border-bottom: 2px solid #e2e8f0;
            text-align: left;
        }

        .items-table th.right {
            text-align: right;
        }

        .items-table td {
            padding: 14px 14px;
            font-size: 10pt;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: top;
        }

        .items-table td.right {
            text-align: right;
        }

        .item-name {
            font-weight: 600;
            color: #0f172a;
            margin-bottom: 2px;
        }

        .item-sub {
            font-size: 8.5pt;
            color: #64748b;
        }

        /* Total block */
        .total-block {
            margin-top: 0;
            border-top: 2px solid #e2e8f0;
        }

        .total-table {
            width: 100%;
        }

        .total-spacer {
            width: 60%;
        }

        .total-lines {
            width: 40%;
        }

        .total-row {
            padding: 8px 14px;
            font-size: 10pt;
        }

        .total-row td {
            padding: 8px 14px;
            font-size: 10pt;
        }

        .total-row td.label {
            color: #64748b;
        }

        .total-row td.value {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }

        .total-final {
            background: #fef2f2;
            border-top: 2px solid #F6475F;
        }

        .total-final td {
            padding: 12px 14px;
            font-size: 13pt;
            font-weight: bold;
        }

        .total-final td.label {
            color: #0f172a;
        }

        .total-final td.value {
            color: #F6475F;
            text-align: right;
        }

        /* Info box */
        .info-box {
            background: #f8fafc;
            border-left: 4px solid #F6475F;
            border-radius: 4px;
            padding: 12px 16px;
            margin-top: 28px;
            font-size: 9pt;
            color: #475569;
        }

        /* Footer */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 14px 40px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 8pt;
            color: #94a3b8;
        }

        .footer-table {
            width: 100%;
        }

        .footer-left {
            text-align: left;
        }

        .footer-right {
            text-align: right;
        }

        /* Page number */
        .page-number:after {
            content: counter(page);
        }
    </style>
</head>
<body>

    <div class="accent-bar"></div>

    {{-- HEADER --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if ($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="{{ config('app.name') }}">
                    @else
                        <span class="logo-text">{{ config('app.name') }}</span>
                    @endif
                </td>
                <td class="invoice-title-cell">
                    <h1>FACTURE</h1>
                    <div class="invoice-number">{{ $invoice->invoice_number }}</div>
                    <div><span class="status-badge">✓ PAYÉ</span></div>
                </td>
            </tr>
        </table>
    </div>

    {{-- MAIN CONTENT --}}
    <div class="content">

        {{-- PARTIES --}}
        <table class="parties-table">
            <tr>
                <td class="party-cell">
                    <div class="party-label">Émetteur</div>
                    <div class="party-name">{{ config('app.name') }}</div>
                    <div class="party-detail">{{ config('app.url') }}</div>
                    <div class="party-detail">support@keyhome.cm</div>
                </td>
                <td class="party-cell right">
                    <div class="party-label">Facturé à</div>
                    <div class="party-name">{{ $invoice->agency->name ?? '—' }}</div>
                    @if ($user)
                        <div class="party-detail">{{ $user->firstname }} {{ $user->lastname }}</div>
                        <div class="party-detail">{{ $user->email }}</div>
                    @endif
                </td>
            </tr>
        </table>

        <hr class="divider">

        {{-- META DATES --}}
        <table class="meta-table">
            <tr>
                <td class="meta-item">
                    <div class="meta-label">Date d'émission</div>
                    <div class="meta-value">{{ $invoice->issued_at->format('d/m/Y') }}</div>
                </td>
                <td class="meta-item">
                    <div class="meta-label">Période couverte</div>
                    <div class="meta-value">
                        @if ($invoice->period_start && $invoice->period_end)
                            {{ $invoice->period_start->format('d/m/Y') }} — {{ $invoice->period_end->format('d/m/Y') }}
                        @else
                            —
                        @endif
                    </div>
                </td>
                <td class="meta-item">
                    <div class="meta-label">Mode de paiement</div>
                    <div class="meta-value">
                        @php
                            $methodLabels = [
                                'fedapay' => 'FedaPay',
                                'orange_money' => 'Orange Money',
                                'mobile_money' => 'Mobile Money',
                                'stripe' => 'Stripe',
                            ];
                            $method = $invoice->payment?->payment_method?->value ?? 'fedapay';
                        @endphp
                        {{ $methodLabels[$method] ?? ucfirst($method) }}
                    </div>
                </td>
            </tr>
        </table>

        <hr class="divider">

        {{-- ITEMS TABLE --}}
        <table class="items-table">
            <thead>
                <tr>
                    <th style="width: 55%">Description</th>
                    <th style="width: 20%">Période</th>
                    <th class="right" style="width: 25%">Montant</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>
                        <div class="item-name">Abonnement {{ $invoice->plan_name }}</div>
                        <div class="item-sub">{{ $invoice->billing_period === 'yearly' ? 'Facturation annuelle' : 'Facturation mensuelle' }}</div>
                    </td>
                    <td style="font-size: 9.5pt; color: #64748b;">
                        @if ($invoice->period_start && $invoice->period_end)
                            {{ $invoice->period_start->format('d/m/Y') }}<br>
                            {{ $invoice->period_end->format('d/m/Y') }}
                        @else
                            —
                        @endif
                    </td>
                    <td class="right" style="font-weight: 600;">{{ $invoice->formatted_amount }}</td>
                </tr>
            </tbody>
        </table>

        {{-- TOTAL --}}
        <div class="total-block">
            <table class="total-table">
                <tr>
                    <td class="total-spacer"></td>
                    <td class="total-lines">
                        <table style="width: 100%; border-collapse: collapse;">
                            <tr>
                                <td style="padding: 8px 14px; color: #64748b; font-size: 9.5pt;">Sous-total</td>
                                <td style="padding: 8px 14px; text-align: right; font-size: 9.5pt; font-weight: 600; color: #0f172a;">{{ $invoice->formatted_amount }}</td>
                            </tr>
                            <tr>
                                <td style="padding: 8px 14px; color: #64748b; font-size: 9.5pt;">TVA (0%)</td>
                                <td style="padding: 8px 14px; text-align: right; font-size: 9.5pt; font-weight: 600; color: #0f172a;">0 {{ $invoice->currency }}</td>
                            </tr>
                            <tr class="total-final" style="background: #fef2f2; border-top: 2px solid #F6475F;">
                                <td style="padding: 12px 14px; font-size: 12pt; font-weight: bold; color: #0f172a;">Total</td>
                                <td style="padding: 12px 14px; font-size: 12pt; font-weight: bold; color: #F6475F; text-align: right;">{{ $invoice->formatted_amount }}</td>
                            </tr>
                        </table>
                    </td>
                </tr>
            </table>
        </div>

        {{-- NOTE --}}
        <div class="info-box">
            <strong>Note :</strong> Cette facture fait office de reçu de paiement officiel.
            Conservez-la pour votre comptabilité. Pour toute question relative à ce paiement,
            contactez notre équipe à <strong>support@keyhome.cm</strong> en mentionnant la référence
            <strong>{{ $invoice->invoice_number }}</strong>.
        </div>

    </div>

    {{-- FOOTER --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">
                    {{ config('app.name') }} · {{ config('app.url') }} · support@keyhome.cm
                </td>
                <td class="footer-right">
                    Page <span class="page-number"></span> · Facture {{ $invoice->invoice_number }} · Générée le {{ now()->format('d/m/Y à H:i') }}
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
