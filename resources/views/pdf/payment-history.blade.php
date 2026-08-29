<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Relevé de paiements — {{ $user->firstname }} {{ $user->lastname }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, Helvetica, Arial, sans-serif;
            font-size: 9.5pt;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.55;
        }

        /* ── Accent bar ── */
        .accent-bar {
            width: 100%;
            height: 4px;
            background: #F6475F;
        }

        /* ── Header ── */
        .header {
            padding: 22px 36px 18px 36px;
            border-bottom: 1px solid #f1f5f9;
        }
        .header-table { width: 100%; }
        .logo-cell { width: 50%; vertical-align: middle; }
        .logo-cell img { height: 36px; width: auto; }
        .logo-text { font-size: 20pt; font-weight: bold; color: #F6475F; letter-spacing: -0.5px; }
        .doc-meta-cell { width: 50%; text-align: right; vertical-align: middle; }
        .doc-title {
            font-size: 16pt;
            font-weight: bold;
            color: #0f172a;
            letter-spacing: -0.3px;
        }
        .doc-subtitle {
            font-size: 9pt;
            color: #64748b;
            margin-top: 3px;
        }
        .period-badge {
            display: inline-block;
            background: #fef2f2;
            color: #F6475F;
            border: 1px solid #fecdd3;
            border-radius: 20px;
            padding: 3px 10px;
            font-size: 8pt;
            font-weight: bold;
            margin-top: 5px;
        }

        /* ── User info band ── */
        .user-band {
            background: #f8fafc;
            border-bottom: 1px solid #e2e8f0;
            padding: 14px 36px;
        }
        .user-band-table { width: 100%; }
        .user-info-cell { width: 50%; vertical-align: top; }
        .user-info-right { width: 50%; text-align: right; vertical-align: top; }
        .info-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            color: #94a3b8;
            font-weight: bold;
            margin-bottom: 2px;
        }
        .info-value { font-size: 10pt; font-weight: 600; color: #0f172a; }
        .info-sub { font-size: 8.5pt; color: #64748b; margin-top: 1px; }

        /* ── Stats cards ── */
        .stats-section { padding: 18px 36px 14px 36px; }
        .stats-table { width: 100%; border-collapse: separate; border-spacing: 8px 0; }
        .stat-card {
            width: 33%;
            padding: 12px 14px;
            border-radius: 6px;
            vertical-align: top;
            text-align: center;
        }
        .stat-card-transactions { background: #f0f9ff; border: 1px solid #bae6fd; }
        .stat-card-amount       { background: #fef2f2; border: 1px solid #fecdd3; }
        .stat-card-credits      { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .stat-label {
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: bold;
            margin-bottom: 4px;
        }
        .stat-value { font-size: 17pt; font-weight: bold; }
        .stat-value-blue   { color: #0284c7; }
        .stat-value-pink   { color: #F6475F; }
        .stat-value-green  { color: #16a34a; }
        .stat-sub { font-size: 8pt; color: #94a3b8; margin-top: 2px; }

        /* ── Section heading ── */
        .section-heading {
            padding: 0 36px 8px 36px;
            border-bottom: 2px solid #F6475F;
            margin-bottom: 0;
        }
        .section-title-text {
            font-size: 9pt;
            font-weight: bold;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: #F6475F;
        }

        /* ── Transactions table ── */
        .transactions-section { padding: 0 36px; }
        .tx-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 0;
        }
        .tx-table thead tr { background: #f8fafc; }
        .tx-table th {
            padding: 9px 10px;
            font-size: 7.5pt;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: bold;
            border-bottom: 1px solid #e2e8f0;
            text-align: left;
        }
        .tx-table th.right { text-align: right; }
        .tx-table th.center { text-align: center; }
        .tx-table td {
            padding: 10px 10px;
            font-size: 9pt;
            border-bottom: 1px solid #f1f5f9;
            color: #334155;
            vertical-align: middle;
        }
        .tx-table td.right { text-align: right; }
        .tx-table td.center { text-align: center; }
        .tx-row-even { background: #fafafa; }

        /* ── Type badge ── */
        .type-badge {
            display: inline-block;
            border-radius: 4px;
            padding: 2px 7px;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .type-credit       { background: #eff6ff; color: #1d4ed8; }
        .type-unlock       { background: #fef3c7; color: #92400e; }
        .type-subscription { background: #f0fdf4; color: #166534; }
        .type-boost        { background: #fdf4ff; color: #7e22ce; }
        .type-other        { background: #f1f5f9; color: #475569; }

        /* ── Status badge ── */
        .status-badge {
            display: inline-block;
            border-radius: 20px;
            padding: 2px 9px;
            font-size: 7.5pt;
            font-weight: bold;
        }
        .status-success   { background: #f0fdf4; color: #166534; border: 1px solid #86efac; }
        .status-pending   { background: #fffbeb; color: #92400e; border: 1px solid #fde68a; }
        .status-failed    { background: #fef2f2; color: #991b1b; border: 1px solid #fca5a5; }
        .status-cancelled { background: #f1f5f9; color: #475569; border: 1px solid #cbd5e1; }
        .status-refunded  { background: #f0f9ff; color: #075985; border: 1px solid #7dd3fc; }

        /* ── Reference monospace ── */
        .ref-mono { font-size: 7.5pt; color: #94a3b8; font-family: "DejaVu Sans Mono", monospace; }

        /* ── Amount ── */
        .amount-paid { font-weight: 700; color: #0f172a; }
        .amount-other { color: #94a3b8; }

        /* ── Credits chip ── */
        .credits-chip {
            display: inline-block;
            background: #eff6ff;
            color: #1d4ed8;
            border-radius: 20px;
            padding: 1px 7px;
            font-size: 7.5pt;
            font-weight: bold;
        }

        /* ── Summary total row ── */
        .total-row { background: #fef2f2; }
        .total-row td {
            font-weight: bold;
            font-size: 10pt;
            color: #0f172a;
            border-top: 2px solid #F6475F;
            border-bottom: none;
            padding: 11px 10px;
        }
        .total-row td.value-cell { color: #F6475F; text-align: right; }

        /* ── Empty state ── */
        .empty-state {
            text-align: center;
            padding: 40px 36px;
            color: #94a3b8;
            font-size: 10pt;
        }

        /* ── Footer ── */
        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px 36px;
            background: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 7.5pt;
            color: #94a3b8;
        }
        .footer-table { width: 100%; }
        .footer-left { text-align: left; }
        .footer-right { text-align: right; }
        .page-number::after { content: counter(page); }

        /* ── Confidential note ── */
        .confid-note {
            margin: 18px 36px 0 36px;
            padding: 10px 14px;
            background: #f8fafc;
            border-left: 3px solid #F6475F;
            border-radius: 3px;
            font-size: 8pt;
            color: #64748b;
        }
    </style>
</head>
<body>

    <div class="accent-bar"></div>

    {{-- ── HEADER ── --}}
    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="KeyHome">
                    @else
                        <span class="logo-text">KeyHome</span>
                    @endif
                </td>
                <td class="doc-meta-cell">
                    <div class="doc-title">RELEVÉ DE PAIEMENTS</div>
                    <div class="doc-subtitle">Document officiel — usage personnel</div>
                    <div><span class="period-badge">{{ $periodLabel }}</span></div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── USER INFO BAND ── --}}
    <div class="user-band">
        <table class="user-band-table">
            <tr>
                <td class="user-info-cell">
                    <div class="info-label">Compte</div>
                    <div class="info-value">{{ $user->firstname }} {{ $user->lastname }}</div>
                    <div class="info-sub">{{ $user->email }}</div>
                    @if($user->phone)
                        <div class="info-sub">{{ $user->phone }}</div>
                    @endif
                </td>
                <td class="user-info-right">
                    <div class="info-label">Généré le</div>
                    <div class="info-value">{{ $generatedAt }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── STATS CARDS ── --}}
    <div class="stats-section">
        <table class="stats-table">
            <tr>
                <td class="stat-card stat-card-transactions">
                    <div class="stat-label">Transactions</div>
                    <div class="stat-value stat-value-blue">{{ $totalCount }}</div>
                    <div class="stat-sub">{{ $paidCount }} réussie{{ $paidCount > 1 ? 's' : '' }}</div>
                </td>
                <td class="stat-card stat-card-amount">
                    <div class="stat-label">Montant total dépensé</div>
                    @if($localeCurrency && $localeRate)
                        {{-- Visitor sees their LOCAL currency (CHF/EUR/USD…) as the
                             primary big number; the FCFA canonical value is rendered
                             smaller below as a reference. Two decimals for fiat (CHF
                             1.40), zero for the no-decimal currencies the controller
                             handles by collapsing localeRate display logic. --}}
                        <div class="stat-value stat-value-pink">{{ number_format($totalAmount * $localeRate, 2, ',', ' ') }} {{ $localeSymbol }}</div>
                        <div class="stat-sub">≈ {{ number_format($totalAmount, 0, ',', ' ') }} FCFA</div>
                    @else
                        <div class="stat-value stat-value-pink">{{ number_format($totalAmount, 0, ',', ' ') }}</div>
                        <div class="stat-sub">XOF (FCFA)</div>
                    @endif
                </td>
                <td class="stat-card stat-card-credits">
                    <div class="stat-label">Crédits achetés</div>
                    <div class="stat-value stat-value-green">{{ number_format($creditsEarned) }}</div>
                    <div class="stat-sub">via paiements réussis · solde actuel : {{ number_format((int) $user->point_balance) }}</div>
                </td>
            </tr>
        </table>
    </div>

    {{-- ── TRANSACTIONS TABLE ── --}}
    <div class="section-heading">
        <span class="section-title-text">Détail des transactions</span>
    </div>

    <div class="transactions-section">
        @if($payments->isEmpty())
            <div class="empty-state">
                Aucune transaction trouvée pour cette période.
            </div>
        @else
            @php
                $typeLabels = [
                    'credit'       => 'Crédits',
                    'unlock'       => 'Déblocage',
                    'subscription' => 'Abonnement',
                    'boost'        => 'Boost',
                ];
            @endphp
            <table class="tx-table">
                <thead>
                    <tr>
                        <th style="width:13%">Date</th>
                        <th style="width:14%">Type</th>
                        <th style="width:20%">Description</th>
                        <th style="width:17%">Référence</th>
                        <th style="width:11%" class="center">Crédits</th>
                        <th style="width:11%" class="center">Méthode</th>
                        <th style="width:8%" class="center">Statut</th>
                        <th style="width:6%" class="right">Montant</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($payments as $i => $payment)
                        @php
                            $typeKey   = $payment->type->value ?? 'other';
                            $typeLbl   = $typeLabels[$typeKey] ?? ucfirst($typeKey);
                            $statusKey = $payment->status->value ?? 'other';
                            $presentation = \App\Support\PaymentPresentation::forPayment($payment);
                            $packName  = $payment->pointPackage?->name;
                            $credits   = $payment->pointPackage?->points_awarded;
                            $isPaid    = $statusKey === 'success';
                        @endphp
                        <tr class="{{ $i % 2 === 1 ? 'tx-row-even' : '' }}">
                            <td style="font-size:8.5pt; color:#64748b; white-space:nowrap;">
                                {{ $payment->created_at->format('d/m/Y') }}<br>
                                <span style="font-size:7.5pt;">{{ $payment->created_at->format('H:i') }} UTC</span>
                            </td>
                            <td>
                                <span class="type-badge type-{{ $typeKey }}">{{ $typeLbl }}</span>
                            </td>
                            <td style="font-size:8.5pt;">
                                @if($packName)
                                    <span style="font-weight:600; color:#0f172a;">{{ $packName }}</span>
                                @elseif($payment->ad)
                                    <span style="font-weight:600; color:#0f172a;">Annonce</span>
                                    <span class="ref-mono">{{ mb_strimwidth($payment->ad->title ?? '', 0, 20, '…') }}</span>
                                @else
                                    <span style="color:#94a3b8;">—</span>
                                @endif
                            </td>
                            <td class="ref-mono">
                                {{ $payment->transaction_id ? mb_strtoupper(substr($payment->transaction_id, 0, 14)) : '—' }}
                            </td>
                            <td class="center">
                                @if($credits)
                                    <span class="credits-chip">+{{ $credits }}</span>
                                @else
                                    <span style="color:#cbd5e1;">—</span>
                                @endif
                            </td>
                            <td class="center" style="font-size:7.6pt; color:#475569; line-height:1.35;">
                                <strong style="font-weight:600;color:#334155;">
                                    {{ $presentation['payment_method_label'] }}
                                </strong>
                                @if(filled($presentation['payment_method_detail']))
                                    <div style="font-size:7pt;color:#64748b;margin-top:2px;">
                                        {{ $presentation['payment_method_detail'] }}
                                    </div>
                                @endif
                                <div style="font-size:7pt;color:#94a3b8;margin-top:3px;">
                                    Passerelle : {{ $presentation['gateway_label'] }}
                                </div>
                            </td>
                            <td class="center">
                                <span class="status-badge status-{{ $statusKey === 'success' ? 'success' : $statusKey }}">
                                    @if($statusKey === 'success')
                                        Réussi
                                    @elseif($statusKey === 'pending')
                                        En attente
                                    @elseif($statusKey === 'failed') Échoué
                                    @elseif($statusKey === 'cancelled') Annulé
                                    @elseif($statusKey === 'refunded') Remboursé
                                    @else {{ ucfirst($statusKey) }}
                                    @endif
                                </span>
                            </td>
                            <td class="right {{ $isPaid ? 'amount-paid' : 'amount-other' }}">
                                @if($localeCurrency && $localeRate)
                                    {{ number_format((float)$payment->amount * $localeRate, 2, ',', ' ') }}
                                    <span style="font-size:7.5pt; font-weight:400; color:#94a3b8;">{{ $localeSymbol }}</span>
                                    <div style="font-size:7pt; font-weight:400; color:#cbd5e1; margin-top:1px;">
                                        ≈ {{ number_format((float)$payment->amount, 0, ',', ' ') }} FCFA
                                    </div>
                                @else
                                    {{ number_format((float)$payment->amount, 0, ',', ' ') }}
                                    <span style="font-size:7.5pt; font-weight:400; color:#94a3b8;">XOF</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach

                    {{-- TOTAL ROW --}}
                    @if($totalCount > 0)
                        <tr class="total-row">
                            <td colspan="7" style="text-align:left; font-size:9pt; font-weight:700; color:#0f172a;">
                                Total encaissé ({{ $paidCount }} transaction{{ $paidCount > 1 ? 's' : '' }} réussie{{ $paidCount > 1 ? 's' : '' }})
                            </td>
                            <td class="value-cell" style="white-space:nowrap;">
                                @if($localeCurrency && $localeRate)
                                    {{ number_format($totalAmount * $localeRate, 2, ',', ' ') }}
                                    <span style="font-size:7.5pt; font-weight:400;">{{ $localeSymbol }}</span>
                                    <div style="font-size:7pt; font-weight:400; color:#94a3b8; margin-top:1px;">
                                        ≈ {{ number_format($totalAmount, 0, ',', ' ') }} FCFA
                                    </div>
                                @else
                                    {{ number_format($totalAmount, 0, ',', ' ') }}
                                    <span style="font-size:7.5pt; font-weight:400;">XOF</span>
                                @endif
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        @endif
    </div>

    {{-- ── CONFIDENTIAL NOTE ── --}}
    <div class="confid-note">
        <strong>Note :</strong> Ce document est un relevé personnel de vos transactions KeyHome.
        Il ne constitue pas une facture fiscale. Pour toute question, contactez
        <strong>support@keyhome.app</strong> en mentionnant votre adresse email.
    </div>

    {{-- ── FOOTER ── --}}
    <div class="footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">
                    KeyHome · keyhome.app · support@keyhome.app
                </td>
                <td class="footer-right">
                    Généré le {{ $generatedAt }} · Page&nbsp;<span class="page-number"></span>
                </td>
            </tr>
        </table>
    </div>

</body>
</html>
