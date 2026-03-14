<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Rapport KeyHome — {{ $generated_at }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; font-size: 11px; color: #1f2937; line-height: 1.5; }
        .header { background: linear-gradient(135deg, #0d9488, #0891b2); color: white; padding: 30px; text-align: center; }
        .header h1 { font-size: 24px; margin-bottom: 5px; }
        .header p { opacity: 0.9; font-size: 12px; }
        .content { padding: 25px; }
        .section { margin-bottom: 25px; }
        .section-title { font-size: 14px; font-weight: 700; color: #0d9488; border-bottom: 2px solid #0d9488; padding-bottom: 5px; margin-bottom: 12px; }
        .metrics-grid { display: table; width: 100%; }
        .metric-row { display: table-row; }
        .metric-label { display: table-cell; padding: 6px 10px; border-bottom: 1px solid #e5e7eb; color: #6b7280; width: 60%; }
        .metric-value { display: table-cell; padding: 6px 10px; border-bottom: 1px solid #e5e7eb; font-weight: 600; text-align: right; }
        .highlight { background: #f0fdfa; }
        .funnel-step { padding: 8px 12px; margin-bottom: 4px; border-radius: 4px; display: table-row; }
        .footer { text-align: center; padding: 20px; color: #9ca3af; font-size: 10px; border-top: 1px solid #e5e7eb; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 6px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        th { background: #f9fafb; font-weight: 600; color: #374151; font-size: 10px; text-transform: uppercase; }
    </style>
</head>
<body>
    <div class="header">
        <h1>KeyHome — Rapport Mensuel</h1>
        <p>Généré le {{ $generated_at }}</p>
    </div>

    <div class="content">
        {{-- ACQUISITION --}}
        <div class="section">
            <div class="section-title">Acquisition</div>
            <div class="metrics-grid">
                <div class="metric-row">
                    <div class="metric-label">Visiteurs uniques (30j)</div>
                    <div class="metric-value">{{ number_format($metrics['acquisition']['unique_visitors']) }}</div>
                </div>
                <div class="metric-row highlight">
                    <div class="metric-label">Nouvelles inscriptions</div>
                    <div class="metric-value">{{ number_format($metrics['acquisition']['new_users']) }}</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Taux de conversion</div>
                    <div class="metric-value">{{ $metrics['acquisition']['conversion_rate'] }}%</div>
                </div>
            </div>
        </div>

        {{-- ACTIVATION --}}
        <div class="section">
            <div class="section-title">Activation</div>
            <div class="metrics-grid">
                <div class="metric-row">
                    <div class="metric-label">Profils complétés</div>
                    <div class="metric-value">{{ $metrics['activation']['profile_completion_rate'] }}%</div>
                </div>
                <div class="metric-row highlight">
                    <div class="metric-label">Temps moyen → 1ère action</div>
                    <div class="metric-value">{{ $metrics['activation']['avg_time_to_first_action'] }}h</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Taux 1ère publication (bailleurs)</div>
                    <div class="metric-value">{{ $metrics['activation']['first_publication_rate'] }}%</div>
                </div>
                <div class="metric-row highlight">
                    <div class="metric-label">Taux 1ère recherche (clients)</div>
                    <div class="metric-value">{{ $metrics['activation']['first_search_rate'] }}%</div>
                </div>
            </div>
        </div>

        {{-- RÉTENTION --}}
        <div class="section">
            <div class="section-title">Rétention</div>
            <div class="metrics-grid">
                <div class="metric-row">
                    <div class="metric-label">DAU / WAU / MAU</div>
                    <div class="metric-value">{{ number_format($metrics['retention']['dau']) }} / {{ number_format($metrics['retention']['wau']) }} / {{ number_format($metrics['retention']['mau']) }}</div>
                </div>
                <div class="metric-row highlight">
                    <div class="metric-label">Stickiness (DAU/MAU)</div>
                    <div class="metric-value">{{ $metrics['retention']['stickiness'] }}%</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Taux de retour 7j</div>
                    <div class="metric-value">{{ $metrics['retention']['return_rate_7d'] }}%</div>
                </div>
                <div class="metric-row highlight">
                    <div class="metric-label">Bailleurs actifs / inactifs</div>
                    <div class="metric-value">{{ $metrics['retention']['active_landlords'] }} / {{ $metrics['retention']['inactive_landlords'] }}</div>
                </div>
            </div>
        </div>

        {{-- REVENU --}}
        <div class="section">
            <div class="section-title">Revenu</div>
            <div class="metrics-grid">
                <div class="metric-row highlight">
                    <div class="metric-label">MRR (Monthly Recurring Revenue)</div>
                    <div class="metric-value">{{ number_format($metrics['revenue']['mrr'], 0, ',', ' ') }} FCFA</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">ARPU (Revenue Per User)</div>
                    <div class="metric-value">{{ number_format($metrics['revenue']['arpu'], 0, ',', ' ') }} FCFA</div>
                </div>
                <div class="metric-row highlight">
                    <div class="metric-label">Churn Rate</div>
                    <div class="metric-value">{{ $metrics['revenue']['churn_rate'] }}%</div>
                </div>
            </div>
            @if(!empty($metrics['revenue']['revenue_by_source']))
                <table style="margin-top: 10px;">
                    <thead>
                        <tr>
                            <th>Source</th>
                            <th style="text-align: right;">Montant (FCFA)</th>
                        </tr>
                    </thead>
                    <tbody>
                        @php
                            $sourceLabels = ['unlock' => 'Déblocages', 'subscription' => 'Abonnements', 'boost' => 'Boosts', 'credit' => 'Crédits'];
                        @endphp
                        @foreach($metrics['revenue']['revenue_by_source'] as $source => $amount)
                            <tr>
                                <td>{{ $sourceLabels[$source] ?? $source }}</td>
                                <td style="text-align: right; font-weight: 600;">{{ number_format($amount, 0, ',', ' ') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            @endif
        </div>

        {{-- TUNNEL DE CONVERSION --}}
        <div class="section">
            <div class="section-title">Tunnel de conversion (30 jours)</div>
            <table>
                <thead>
                    <tr>
                        <th>Étape</th>
                        <th style="text-align: right;">Nombre</th>
                        <th style="text-align: right;">Taux</th>
                        <th style="text-align: right;">Drop-off</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach($metrics['funnel']['steps'] as $step)
                        <tr>
                            <td>{{ $step['label'] }}</td>
                            <td style="text-align: right; font-weight: 600;">{{ number_format($step['count']) }}</td>
                            <td style="text-align: right;">{{ $step['rate'] }}%</td>
                            <td style="text-align: right; color: {{ $step['drop_off'] > 50 ? '#ef4444' : '#6b7280' }};">{{ $step['drop_off'] > 0 ? '-'.$step['drop_off'].'%' : '—' }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- QUALITÉ --}}
        <div class="section">
            <div class="section-title">Qualité</div>
            <div class="metrics-grid">
                <div class="metric-row">
                    <div class="metric-label">NPS (Net Promoter Score)</div>
                    <div class="metric-value">{{ $metrics['quality']['nps'] >= 0 ? '+' : '' }}{{ $metrics['quality']['nps'] }}</div>
                </div>
                <div class="metric-row highlight">
                    <div class="metric-label">Taux de signalement</div>
                    <div class="metric-value">{{ $metrics['quality']['report_rate'] }}%</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Taux de fraude</div>
                    <div class="metric-value">{{ $metrics['quality']['fraud_rate'] }}%</div>
                </div>
                <div class="metric-row highlight">
                    <div class="metric-label">Temps moyen pour louer</div>
                    <div class="metric-value">{{ $metrics['quality']['avg_time_to_rent'] }} jours</div>
                </div>
                <div class="metric-row">
                    <div class="metric-label">Taux de réponse bailleurs</div>
                    <div class="metric-value">{{ $metrics['quality']['landlord_response_rate'] }}%</div>
                </div>
            </div>
        </div>
    </div>

    <div class="footer">
        KeyHome — Rapport généré automatiquement le {{ $generated_at }}<br>
        Confidentiel — Usage interne et investisseurs uniquement
    </div>
</body>
</html>
