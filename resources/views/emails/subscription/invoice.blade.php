<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture KeyHome — {{ $invoice->invoice_number }}</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; background-color: #f8fafc; padding: 40px 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        .header { background: linear-gradient(135deg, #F6475F 0%, #D5384E 100%); padding: 32px 20px; text-align: center; }
        .hero { padding: 40px 32px 20px; text-align: center; }
        .hero h1 { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; letter-spacing: -0.5px; }
        .content { padding: 0 32px 32px; font-size: 16px; line-height: 1.6; color: #475569; }
        .invoice-badge { display: inline-block; background-color: #f0fdf4; color: #166534; padding: 6px 16px; border-radius: 20px; font-size: 13px; font-weight: 600; margin-bottom: 24px; border: 1px solid #bbf7d0; }
        .invoice-table { width: 100%; border-collapse: collapse; margin: 24px 0; }
        .invoice-table th { text-align: left; padding: 12px 16px; background-color: #f8fafc; color: #64748b; font-size: 12px; text-transform: uppercase; letter-spacing: 0.5px; font-weight: 600; border-bottom: 2px solid #e2e8f0; }
        .invoice-table td { padding: 14px 16px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #334155; }
        .invoice-table .total-row td { font-weight: 700; font-size: 16px; color: #0f172a; border-top: 2px solid #e2e8f0; border-bottom: none; }
        .detail-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f1f5f9; }
        .detail-label { color: #64748b; font-size: 14px; }
        .detail-value { color: #0f172a; font-weight: 600; font-size: 14px; }
        .info-box { background-color: #eff6ff; border-radius: 8px; padding: 16px; margin-top: 24px; font-size: 14px; color: #1e40af; border: 1px solid #bfdbfe; }
        .footer { background-color: #f1f5f9; padding: 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
        .footer a { color: #F6475F; text-decoration: none; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('images/logo.png') }}" alt="KeyHome Logo" style="max-width: 150px; height: auto;">
            </div>

            <div class="hero">
                <h1>Facture d'abonnement</h1>
            </div>

            <div class="content" style="text-align: center;">
                <span class="invoice-badge">✅ Paiement confirmé</span>

                <p style="text-align: left;">Bonjour <strong>{{ $user->firstname }}</strong>,</p>
                <p style="text-align: left;">Merci pour votre souscription ! Voici le récapitulatif de votre facture :</p>

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
                    @if($invoice->period_start && $invoice->period_end)
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
            </div>

            <div class="footer">
                <p>&copy; {{ date('Y') }} KeyHome. Tous droits réservés.</p>
                <p>Cet email est une confirmation automatique de votre paiement d'abonnement.</p>
            </div>
        </div>
    </div>
</body>
</html>
