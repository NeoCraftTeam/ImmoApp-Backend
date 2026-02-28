@extends('emails.layout')

@section('title', 'Coordonnées débloquées — ' . $ad->title)

@section('content')
    <style>
        .ad-card {
            background-color: #f8fafc;
            border-radius: 10px;
            padding: 20px 24px;
            margin: 24px 0;
            border: 1px solid #e2e8f0;
        }

        .ad-card h3 {
            margin: 0 0 4px 0;
            font-size: 15px;
            font-weight: 700;
            color: #0f172a;
        }

        .ad-card .ad-meta {
            font-size: 13px;
            color: #64748b;
            margin: 0;
        }

        .price-tag {
            display: inline-block;
            margin-top: 10px;
            background-color: #f0fdf4;
            color: #166534;
            border: 1px solid #bbf7d0;
            border-radius: 20px;
            padding: 4px 14px;
            font-size: 13px;
            font-weight: 600;
        }

        .receipt-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
        }

        .receipt-table {
            width: 100%;
            border-collapse: collapse;
            margin-top: 20px;
        }

        .receipt-table td {
            padding: 8px 0;
            font-size: 14px;
            border-bottom: 1px solid #f1f5f9;
        }

        .receipt-table td:first-child {
            color: #64748b;
        }

        .receipt-table td:last-child {
            text-align: right;
            font-weight: 600;
            color: #0f172a;
        }
    </style>

    <h1>Coordonnées débloquées</h1>

    <p class="text">Bonjour <strong>{{ $user->firstname }}</strong>,</p>
    <p class="text">Votre paiement a été confirmé. Vous avez maintenant accès aux coordonnées complètes du propriétaire de l'annonce suivante :</p>

    <div class="ad-card">
        <h3>{{ $ad->title }}</h3>
        <p class="ad-meta">{{ $ad->adresse ?? '' }}</p>
        @if ($ad->price)
            <span class="price-tag">
                {{ number_format((float) $ad->price, 0, ',', ' ') }} FCFA
            </span>
        @endif
    </div>

    <table class="receipt-table">
        <tr>
            <td>Référence de paiement</td>
            <td>#{{ $payment->transaction_id }}</td>
        </tr>
        <tr>
            <td>Montant payé</td>
            <td>{{ number_format((float) $payment->amount, 0, ',', ' ') }} FCFA</td>
        </tr>
        <tr>
            <td>Date</td>
            <td>{{ $payment->updated_at->format('d/m/Y à H:i') }}</td>
        </tr>
        <tr>
            <td>Mode de paiement</td>
            <td>FedaPay</td>
        </tr>
    </table>

    <div class="btn-wrapper">
        <a href="{{ config('app.frontend_url', config('app.url')) . '/ads/' . $ad->slug }}" class="btn">
            Voir l'annonce
        </a>
    </div>

    <p class="text" style="margin-top: 24px; font-size: 13px; color: #94a3b8;">
        Conservez cet email comme preuve de paiement. En cas de problème, contactez notre support en mentionnant la référence <strong>#{{ $payment->transaction_id }}</strong>.
    </p>
@endsection
