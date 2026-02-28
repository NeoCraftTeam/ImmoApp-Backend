@extends('emails.layout')

@section('title', 'Facture ' . config('app.name') . ' — ' . $invoice->invoice_number)

@section('content')
    <style>
        .invoice-badge {
            display: inline-block;
            background-color: #f0fdf4;
            color: #166534;
            padding: 6px 16px;
            border-radius: 20px;
            font-size: 13px;
            font-weight: 600;
            margin-bottom: 24px;
            border: 1px solid #bbf7d0;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 24px 0;
        }

        .invoice-table th {
            text-align: left;
            padding: 12px 16px;
            background-color: #f8fafc;
            color: #64748b;
            font-size: 12px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            font-weight: 600;
            border-bottom: 2px solid #e2e8f0;
        }

        .invoice-table td {
            padding: 14px 16px;
            border-bottom: 1px solid #f1f5f9;
            font-size: 14px;
            color: #334155;
        }

        .invoice-table .total-row td {
            font-weight: 700;
            font-size: 16px;
            color: #0f172a;
            border-top: 2px solid #e2e8f0;
            border-bottom: none;
        }

        .info-box {
            background-color: #eff6ff;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
            font-size: 14px;
            color: #1e40af;
            border: 1px solid #bfdbfe;
        }
    </style>

    <h1>Facture d'abonnement</h1>

    <div style="text-align: center; margin-top: 20px;">
        <span class="invoice-badge">✅ Paiement confirmé</span>
    </div>

    <p class="text">Bonjour <strong>{{ $user->firstname }}</strong>,</p>
    <p class="text">Merci pour votre souscription ! Voici le récapitulatif de votre facture :</p>

    <table class="invoice-table">
        <thead>
            <tr>
                <th>Description</th>
                <th style="text-align: right;">Montant</th>
            </tr>
        </thead>
        <tbody>
            <tr>
                <td>
                    <strong>Plan {{ $invoice->plan_name }}</strong><br>
                    <span style="color: #64748b; font-size: 13px;">
                        Période : {{ $invoice->billing_period === 'yearly' ? 'Annuel' : 'Mensuel' }}
                    </span>
                </td>
                <td style="text-align: right;">{{ $invoice->formatted_amount }}</td>
            </tr>
            <tr class="total-row">
                <td>Total</td>
                <td style="text-align: right;">{{ $invoice->formatted_amount }}</td>
            </tr>
        </tbody>
    </table>

    <table style="width: 100%; font-size: 14px; margin-top: 16px;">
        <tr>
            <td style="padding: 8px 0; color: #64748b;">N° Facture</td>
            <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $invoice->invoice_number }}</td>
        </tr>
        <tr>
            <td style="padding: 8px 0; color: #64748b;">Date d'émission</td>
            <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $invoice->issued_at->format('d/m/Y') }}</td>
        </tr>
        @if ($invoice->period_start && $invoice->period_end)
            <tr>
                <td style="padding: 8px 0; color: #64748b;">Période couverte</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600;">
                    {{ $invoice->period_start->format('d/m/Y') }} — {{ $invoice->period_end->format('d/m/Y') }}
                </td>
            </tr>
        @endif
        <tr>
            <td style="padding: 8px 0; color: #64748b;">Agence</td>
            <td style="padding: 8px 0; text-align: right; font-weight: 600;">{{ $invoice->agency->name ?? '—' }}</td>
        </tr>
    </table>

    <div class="info-box">
        <strong>ℹ️ Informations</strong><br>
        Cette facture fait office de reçu de paiement. Elle est conservée dans votre espace de gestion d'abonnement.
        Pour toute question, contactez notre support.
    </div>
@endsection
