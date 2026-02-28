@extends('emails.layout')

@section('title', 'Votre abonnement expire bientôt — ' . config('app.name'))

@section('content')
    <style>
        .countdown-badge {
            display: inline-block;
            background-color: #fef2f2;
            color: #991b1b;
            padding: 10px 24px;
            border-radius: 20px;
            font-size: 16px;
            font-weight: 700;
            margin-bottom: 24px;
            border: 1px solid #fecaca;
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

        .info-box {
            background-color: #fff7ed;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
            font-size: 14px;
            color: #9a3412;
            border: 1px solid #fed7aa;
        }
    </style>

    <h1>Votre abonnement expire bientôt ⏰</h1>

    <div style="text-align: center; margin-top: 20px;">
        <span class="countdown-badge">{{ $days }} jour(s) restant(s)</span>
    </div>

    <p class="text">Bonjour l'équipe <strong>{{ $agencyName }}</strong>,</p>
    <p class="text">Nous voulions vous informer que votre abonnement arrive bientôt à expiration. Pour éviter toute interruption de vos services, pensez à le renouveler.</p>

    <div class="detail-card">
        <table>
            <tr>
                <td>Plan actuel</td>
                <td>{{ $planName }}</td>
            </tr>
            <tr>
                <td>Date d'expiration</td>
                <td style="color: #dc2626 !important;">{{ $endsAt }}</td>
            </tr>
        </table>
    </div>

    <div class="btn-wrapper">
        <a href="{{ config('app.url') . '/agency' }}" class="btn">Renouveler mon abonnement</a>
    </div>

    <div class="info-box">
        <strong>⚠️ Que se passe-t-il après expiration ?</strong><br>
        Vos annonces ne seront plus boostées et les limites de votre plan ne seront plus actives. Renouvelez dès maintenant pour maintenir vos avantages.
    </div>
@endsection
