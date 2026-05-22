@extends('emails.owner-layout')

@section('title', 'Renouvellement de votre abonnement — ' . config('app.name'))

@section('preheader', 'Rappel de renouvellement — assurez la continuité de vos avantages sur KeyHome.')

@section('content')
    <style>
        .renewal-badge {
            display: inline-block;
            padding: 10px 24px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 24px;
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
            background-color: #f0fdf4;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
            font-size: 14px;
            color: #166534;
            border: 1px solid #bbf7d0;
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

    <h1>Renouvellement automatique de votre abonnement</h1>

    <div style="text-align: center; margin-top: 20px;">
        <span class="renewal-badge">
            Expire le {{ $endsAt }}
        </span>
    </div>

    <p class="text">Bonjour l'équipe <strong>{{ $agencyName }}</strong>,</p>

    <p class="text">
        Votre abonnement <strong>{{ $planName }}</strong> ({{ $billingPeriod }}) arrive à échéance le <strong>{{ $endsAt }}</strong>.
        Le renouvellement automatique étant activé, nous avons préparé un lien de paiement pour faciliter votre renouvellement.
    </p>

    <div class="detail-card">
        <table>
            <tr>
                <td>Plan</td>
                <td>{{ $planName }}</td>
            </tr>
            <tr>
                <td>Période</td>
                <td>{{ ucfirst($billingPeriod) }}</td>
            </tr>
            <tr>
                <td>Date d'expiration</td>
                <td>{{ $endsAt }}</td>
            </tr>
            <tr>
                <td>Montant du renouvellement</td>
                <td class="price-highlight">{{ $planPrice }} FCFA</td>
            </tr>
        </table>
    </div>

    @include('emails.partials.button', [
        'url'   => $paymentUrl,
        'label' => 'Renouveler maintenant — ' . $planPrice . ' FCFA',
        'color' => '#0d9488',
        'width' => 300,
    ])

    <p class="text" style="font-size: 14px; color: #64748b;">En renouvelant, vous conservez :</p>
    <ul class="benefits-list">
        <li>La priorité d'affichage de vos annonces dans les résultats</li>
        <li>Les contacts clients sans interruption</li>
        <li>Votre badge agence et votre score de réputation</li>
    </ul>

    <div class="info-box">
        <strong>Période de grâce</strong><br>
        Si vous ne renouvelez pas avant le {{ $endsAt }}, vous bénéficiez d'un délai de grâce de 3 jours pendant lequel vos annonces restent boostées. Passé ce délai, le boost sera désactivé.
    </div>
@endsection
