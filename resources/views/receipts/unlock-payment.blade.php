<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu — {{ $payment->transaction_id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 13px;
            color: #1a1a1a;
            background: #ffffff;
            line-height: 1.5;
        }

        /* ── TOP ACCENT ── */
        .top-bar { height: 4px; background: #F6475F; }

        /* ── HEADER ── */
        .header { padding: 32px 48px 28px; display: table; width: 100%; border-bottom: 1px solid #ebebeb; }
        .header-logo { display: table-cell; vertical-align: middle; }
        .logo-img { height: 48px; }
        .logo-text { font-size: 22px; font-weight: 700; color: #1a1a1a; letter-spacing: -0.02em; }
        .logo-sub { font-size: 10px; color: #999999; margin-top: 2px; text-transform: uppercase; letter-spacing: 0.08em; }
        .header-title { display: table-cell; text-align: right; vertical-align: bottom; }
        .doc-type { font-size: 11px; text-transform: uppercase; letter-spacing: 0.12em; color: #999999; font-weight: 700; margin-bottom: 4px; }
        .doc-number { font-size: 26px; font-weight: 700; color: #1a1a1a; letter-spacing: -0.02em; }

        /* ── META STRIP ── */
        .meta-strip { padding: 14px 48px; background: #fafafa; border-bottom: 1px solid #ebebeb; display: table; width: 100%; }
        .meta-item { display: table-cell; font-size: 11px; color: #666666; padding-right: 32px; }
        .meta-item strong { color: #1a1a1a; font-weight: 700; }
        .meta-status { display: table-cell; text-align: right; font-size: 11px; }
        .status-pill { display: inline-block; background: #fff0f1; border: 1px solid #ffc7cc; color: #c0303f; border-radius: 20px; padding: 3px 12px; font-size: 10px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.06em; }

        /* ── BODY ── */
        .body { padding: 36px 48px; }

        /* ── PARTIES ── */
        .parties { display: table; width: 100%; margin-bottom: 36px; }
        .party { display: table-cell; width: 50%; vertical-align: top; }
        .party-right { padding-left: 40px; }
        .party-label { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.14em; color: #F6475F; margin-bottom: 8px; }
        .party-name { font-size: 14px; font-weight: 700; color: #1a1a1a; margin-bottom: 5px; }
        .party-line { font-size: 12px; color: #555555; line-height: 1.8; }

        /* ── TABLE ── */
        .table-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.14em; color: #999999; margin-bottom: 8px; }
        .items-table { width: 100%; border-collapse: collapse; margin-bottom: 0; }
        .items-table thead tr { background: #f5f5f5; border-top: 2px solid #F6475F; }
        .items-table thead th { padding: 9px 14px; font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.1em; color: #777777; text-align: left; border-bottom: 1px solid #e5e5e5; }
        .items-table thead th.th-right { text-align: right; }
        .items-table tbody td { padding: 16px 14px; vertical-align: top; border-bottom: 1px solid #f0f0f0; }
        .item-name { font-size: 13px; font-weight: 700; color: #1a1a1a; margin-bottom: 4px; }
        .item-sub { font-size: 11px; color: #777777; line-height: 1.6; }
        .item-badge { display: inline-block; margin-top: 6px; border: 1px solid #e0e0e0; border-radius: 3px; padding: 1px 7px; font-size: 9px; color: #999999; font-weight: 700; letter-spacing: 0.04em; }
        .item-amount { font-size: 14px; font-weight: 700; color: #1a1a1a; text-align: right; white-space: nowrap; }
        .col-ref { color: #999999; font-size: 12px; }

        /* ── TOTALS ── */
        .totals-section { margin-top: 0; border-top: 1px solid #f0f0f0; }
        .totals-inner { display: table; margin-left: auto; width: 310px; padding: 20px 0 0; }
        .t-row { display: table-row; }
        .t-label { display: table-cell; font-size: 12px; color: #777777; padding: 4px 16px 4px 0; }
        .t-value { display: table-cell; font-size: 12px; color: #444444; text-align: right; padding: 4px 0; }
        .t-divider { border: none; border-top: 2px solid #1a1a1a; margin: 14px 0 10px; }
        .t-total-label { display: table-cell; font-size: 14px; font-weight: 700; color: #1a1a1a; padding: 6px 16px 6px 0; }
        .t-total-value { display: table-cell; font-size: 20px; font-weight: 700; color: #1a1a1a; text-align: right; padding: 6px 0; }

        /* ── TRANSACTION INFO ── */
        .txn-section { margin-top: 32px; padding-top: 24px; border-top: 1px solid #ebebeb; }
        .txn-title { font-size: 9px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.14em; color: #999999; margin-bottom: 12px; }
        .txn-grid { display: table; width: 100%; }
        .txn-cell { display: table-cell; width: 33.33%; vertical-align: top; padding-right: 16px; }
        .txn-cell-last { display: table-cell; width: 33.33%; vertical-align: top; }
        .txn-label { font-size: 9px; text-transform: uppercase; letter-spacing: 0.1em; color: #bbbbbb; font-weight: 700; margin-bottom: 4px; }
        .txn-value { font-size: 12px; font-weight: 700; color: #1a1a1a; }
        .txn-ok { color: #c0303f; }

        /* ── FOOTER ── */
        .footer { margin-top: 36px; padding: 18px 48px; background: #fafafa; border-top: 1px solid #ebebeb; display: table; width: 100%; }
        .footer-note { display: table-cell; font-size: 10px; color: #999999; line-height: 1.8; vertical-align: middle; }
        .footer-right { display: table-cell; text-align: right; vertical-align: middle; }
        .footer-brand { font-size: 14px; font-weight: 700; color: #1a1a1a; }
        .footer-copy { font-size: 9px; color: #bbbbbb; margin-top: 3px; }
    </style>
</head>
<body>

<div class="top-bar"></div>

{{-- HEADER --}}
<div class="header">
    <div class="header-logo">
        @if($logoBase64)
            <img src="data:image/png;base64,{{ $logoBase64 }}" class="logo-img" alt="Keyhome">
        @else
            <div class="logo-text">Keyhome</div>
        @endif
        <div class="logo-sub">Plateforme immobilière</div>
    </div>
    <div class="header-title">
        <div class="doc-type">Reçu de paiement</div>
        <div class="doc-number">N° {{ $payment->transaction_id }}</div>
    </div>
</div>

{{-- META STRIP --}}
<div class="meta-strip">
    <div class="meta-item">Échéance &nbsp;<strong>{{ $payment->updated_at->format('d/m/Y') }}</strong></div>
    <div class="meta-item">Date &nbsp;<strong>{{ $payment->updated_at->format('d/m/Y') }}</strong></div>
    <div class="meta-item">Mode &nbsp;<strong>{{ strtoupper(is_string($payment->payment_method) ? $payment->payment_method : ($payment->payment_method?->value ?? 'Mobile Money')) }}</strong></div>
    <div class="meta-status">
        <span class="status-pill">Payé</span>
    </div>
</div>

<div class="body">

    {{-- PARTIES --}}
    <div class="parties">
        <div class="party">
            <div class="party-label">Émetteur</div>
            <div class="party-name">Keyhome</div>
            <div class="party-line">
                Plateforme immobilière<br>
                support@keyhome.cm
            </div>
        </div>
        <div class="party party-right">
            <div class="party-label">Facturé à</div>
            <div class="party-name">{{ $user->firstname }} {{ $user->lastname }}</div>
            <div class="party-line">
                {{ $user->email }}<br>
                @if($user->phone){{ $user->phone }}@endif
            </div>
        </div>
    </div>

    {{-- TABLE --}}
    <div class="table-title">Désignation</div>
    <table class="items-table">
        <thead>
            <tr>
                <th style="width:50%;">Prestation</th>
                <th style="width:32%;">Référence annonce</th>
                <th class="th-right" style="width:18%;">Montant HT</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <div class="item-name">Déblocage de coordonnées</div>
                    <div class="item-sub">{{ $ad->title }}</div>
                    @if($ad->adresse)<div class="item-sub">{{ $ad->adresse }}</div>@endif
                </td>
                <td class="col-ref">
                    <div class="item-badge">{{ $ad->slug ?? $ad->id }}</div>
                </td>
                <td>
                    <div class="item-amount">{{ number_format((float) $payment->amount, 0, ',', ' ') }} FCFA</div>
                </td>
            </tr>
        </tbody>
    </table>

    {{-- TOTALS --}}
    <div class="totals-section">
        <div class="totals-inner">
            <div class="t-row">
                <div class="t-label">Prix HT</div>
                <div class="t-value">{{ number_format((float) $payment->amount, 0, ',', ' ') }} FCFA</div>
            </div>
            <div class="t-row">
                <div class="t-label">Taxes (0%)</div>
                <div class="t-value">0 FCFA</div>
            </div>
            <hr class="t-divider">
            <div class="t-row">
                <div class="t-total-label">Total payé</div>
                <div class="t-total-value">{{ number_format((float) $payment->amount, 0, ',', ' ') }} FCFA</div>
            </div>
        </div>
    </div>

    {{-- TRANSACTION INFO --}}
    <div class="txn-section">
        <div class="txn-title">Informations de transaction</div>
        <div class="txn-grid">
            <div class="txn-cell">
                <div class="txn-label">Référence</div>
                <div class="txn-value">#{{ $payment->transaction_id }}</div>
            </div>
            <div class="txn-cell">
                <div class="txn-label">Date</div>
                <div class="txn-value">{{ $payment->updated_at->format('d/m/Y à H:i') }}</div>
            </div>
            <div class="txn-cell-last">
                <div class="txn-label">Statut</div>
                <div class="txn-value txn-ok">Paiement confirmé</div>
            </div>
        </div>
    </div>

</div>

{{-- FOOTER --}}
<div class="footer">
    <div class="footer-note">
        Conservez ce document comme preuve de paiement.<br>
        En cas de litige, contactez <strong>support@keyhome.cm</strong> avec la réf. <strong>#{{ $payment->transaction_id }}</strong>.
    </div>
    <div class="footer-right">
        <div class="footer-brand">Keyhome</div>
        <div class="footer-copy">© {{ now()->year }} — keyhome.cm</div>
    </div>
</div>

</body>
</html>
