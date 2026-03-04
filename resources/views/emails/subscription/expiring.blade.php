@extends('emails.layout')

@section('title', 'Votre abonnement expire bientôt — ' . config('app.name'))

@section('content')
    <style>
        .countdown-badge {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 24px;
        }

        .countdown-badge.urgent {
            background-color: #fef2f2;
            color: #991b1b;
            border: 1px solid #fecaca;
        }

        .countdown-badge.warning {
            background-color: #fffbeb;
            color: #92400e;
            border: 1px solid #fde68a;
        }

        .countdown-badge.notice {
            background-color: #eff6ff;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }

        .detail-card {
            background-color: #f8fafc;
            border-radius: 10px;
            padding: 20px 24px;
            margin: 24px 0;
            border: 1px solid #e2e8f0;
        }

        .detail-card table {
            width: 100%;
            border-collapse: collapse;
        }

        .detail-card td {
            padding: 8px 0;
            font-size: 14px;
        }

        .detail-card td:first-child {
            color: #64748b;
        }

        .detail-card td:last-child {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }

        .detail-card tr + tr td {
            border-top: 1px solid #f1f5f9;
        }

        .price-highlight {
            color: #2563eb !important;
            font-size: 16px !important;
        }

        .info-box {
            background-color: #fff7ed;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
            font-size: 14px;
            color: #9a3412;
            border: 1px solid #fed7aa;
            line-height: 1.6;
        }

        .benefits-list {
            list-style: none;
            padding: 0;
            margin: 16px 0 0;
        }

        .benefits-list li {
            padding: 6px 0;
            font-size: 14px;
            color: #374151;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .benefits-list li::before {
            content: '✓';
            color: #10b981;
            font-weight: 700;
            flex-shrink: 0;
        }
    </style>

    @php
        $badgeClass = $days <= 1 ? 'urgent' : ($days <= 3 ? 'warning' : 'notice');
        $headline = $days <= 1
            ? 'Votre abonnement expire aujourd\'hui !'
            : 'Votre abonnement expire dans ' . $days . ' jours';
    @endphp

    <h1>{{ $headline }}</h1>

    <div style="text-align: center; margin-top: 20px;">
        <span class="countdown-badge {{ $badgeClass }}">
            {{ $days <= 1 ? 'Dernier jour' : $days . ' jour(s) restant(s)' }}
        </span>
    </div>

    <p class="text">Bonjour l'équipe <strong>{{ $agencyName }}</strong>,</p>

    @if($days <= 1)
        <p class="text">Votre abonnement <strong>{{ $planName }}</strong> expire <strong>aujourd'hui</strong>. À partir de demain, vos annonces ne seront plus boostées et disparaîtront du haut des résultats de recherche. Renouvelez maintenant pour ne pas perdre votre visibilité.</p>
    @elseif($days <= 3)
        <p class="text">Votre abonnement <strong>{{ $planName }}</strong> expire le <strong>{{ $endsAt }}</strong>. Il vous reste très peu de temps — renouvelez dès maintenant pour maintenir votre avantage concurrentiel.</p>
    @else
        <p class="text">Votre abonnement <strong>{{ $planName }}</strong> expire le <strong>{{ $endsAt }}</strong>. Pensez à le renouveler pour continuer à bénéficier du boost de visibilité sur vos annonces.</p>
    @endif

    <div class="detail-card">
        <table>
            <tr>
                <td>Plan actuel</td>
                <td>{{ $planName }}</td>
            </tr>
            <tr>
                <td>Date d'expiration</td>
                <td style="color: #dc2626 !important; font-weight: 700;">{{ $endsAt }}</td>
            </tr>
            <tr>
                <td>Tarif de renouvellement</td>
                <td class="price-highlight">{{ $planPrice }} FCFA / mois</td>
            </tr>
        </table>
    </div>

    <p class="text" style="font-size: 14px; color: #64748b;">En renouvelant votre abonnement, vous conservez :</p>
    <ul class="benefits-list">
        <li>La priorité d'affichage de vos annonces dans les résultats</li>
        <li>Les contacts clients sans interruption</li>
        <li>Votre badge agence et votre score de réputation</li>
    </ul>

    <div class="btn-wrapper">
        <a href="{{ $renewalUrl }}" class="btn">Renouveler mon abonnement {{ $planName }}</a>
    </div>

    <div class="info-box">
        <strong>Que se passe-t-il après expiration ?</strong><br>
        Vos annonces restent en ligne mais perdent entièrement leur boost de visibilité. Elles apparaîtront après toutes les annonces des agences abonnées. Renouvelez maintenant pour ne pas perdre votre position.
    </div>
@endsection
