<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Reçu — {{ $payment->transaction_id }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.5;
        }
        .accent-bar { width: 100%; height: 4px; background: #F6475F; }
        .header {
            padding: 22px 36px 16px 36px;
            border-bottom: 1px solid #f1f5f9;
        }
        .header-table { width: 100%; }
        .logo-cell { width: 55%; vertical-align: middle; }
        .doc-meta-cell { width: 45%; text-align: right; vertical-align: top; }
        .logo-text { font-size: 18pt; font-weight: bold; color: #F6475F; letter-spacing: -0.5px; }
        .doc-title { font-size: 15pt; font-weight: bold; color: #0f172a; }
        .doc-subtitle { font-size: 8.5pt; color: #64748b; margin-top: 4px; }
        .user-band {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 36px;
        }
        .band-table { width: 100%; }
        .info-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .info-value { font-size: 10pt; font-weight: 600; color: #0f172a; }
        .detail-section { padding: 20px 36px 8px 36px; }
        .section-title {
            font-size: 8.5pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #F6475F;
            margin-bottom: 12px;
            border-bottom: 2px solid #F6475F;
            padding-bottom: 4px;
        }
        .kv-table { width: 100%; border-collapse: collapse; }
        .kv-table td { padding: 8px 0; vertical-align: top; }
        .kv-label { width: 38%; color: #64748b; font-size: 9pt; }
        .kv-val { font-weight: 600; font-size: 10pt; color: #0f172a; }
        .amount-primary { font-size: 20pt; font-weight: bold; color: #F6475F; }
        .amount-sub { font-size: 9pt; color: #64748b; margin-top: 4px; }
        .footer {
            margin-top: 28px;
            padding: 16px 36px 24px 36px;
            border-top: 1px solid #e2e8f0;
            font-size: 8pt;
            color: #94a3b8;
            text-align: center;
        }
        .mono { font-family: DejaVu Sans Mono, DejaVu Sans, monospace; font-size: 9pt; }
    </style>
</head>
<body>
    <div class="accent-bar"></div>
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if(!empty($logoBase64))
                        <img src="{{ $logoBase64 }}" alt="KeyHome" style="height:34px;width:auto;" />
                    @else
                        <span class="logo-text">KeyHome</span>
                    @endif
                </td>
                <td class="doc-meta-cell">
                    <div class="doc-title">Reçu de paiement</div>
                    <div class="doc-subtitle">Émis le {{ $generatedAt }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="user-band">
        <table class="band-table">
            <tr>
                <td style="width:50%;">
                    <div class="info-label">Client</div>
                    <div class="info-value">{{ $user->firstname }} {{ $user->lastname }}</div>
                    <div class="info-value" style="font-weight:500;font-size:9pt;">{{ $user->email }}</div>
                </td>
                <td style="width:50%;text-align:right;">
                    <div class="info-label">Référence transaction</div>
                    <div class="info-value mono">{{ $payment->transaction_id }}</div>
                    <div class="info-label" style="margin-top:8px;">ID interne</div>
                    <div class="mono" style="font-size:8.5pt;color:#64748b;">{{ $payment->id }}</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="detail-section">
        <div class="section-title">Détail du paiement</div>
        <table class="kv-table">
            <tr>
                <td class="kv-label">Date</td>
                <td class="kv-val">{{ $payment->created_at?->format('d/m/Y H:i') }}</td>
            </tr>
            <tr>
                <td class="kv-label">Nature</td>
                <td class="kv-val">{{ $typeLabel }}</td>
            </tr>
            @if($payment->relationLoaded('pointPackage') && $payment->pointPackage)
                <tr>
                    <td class="kv-label">Produit / pack</td>
                    <td class="kv-val">{{ $payment->pointPackage->name }}</td>
                </tr>
                <tr>
                    <td class="kv-label">Crédits</td>
                    <td class="kv-val">{{ $payment->pointPackage->points_awarded ?? '—' }}</td>
                </tr>
            @endif
            <tr>
                <td class="kv-label">Moyen de paiement</td>
                <td class="kv-val">
                    {{ $presentation['payment_method_label'] }}
                    @if(!empty($presentation['payment_method_detail']))
                        <span style="color:#64748b;font-weight:500;"> — {{ $presentation['payment_method_detail'] }}</span>
                    @endif
                </td>
            </tr>
        </table>
    </div>

    <div class="detail-section">
        <div class="section-title">Montant</div>
        <div class="amount-primary">{{ number_format((float) $payment->amount, 0, ',', ' ') }}&nbsp;XAF</div>
        @if($localeCurrency && $localeRate && $localeSymbol)
            <div class="amount-sub">
                ≈ {{ $localeSymbol }}{{ number_format((float) $payment->amount * (float) $localeRate, 2, ',', ' ') }} {{ $localeCurrency }}
                <span style="color:#94a3b8;"> — taux indicatif visiteur</span>
            </div>
        @endif
    </div>

    <div class="footer">
        Document généré automatiquement — KeyHome — ne pas jeter sur la voie publique.
    </div>
</body>
</html>
