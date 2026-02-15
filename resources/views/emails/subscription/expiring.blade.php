<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Abonnement expire bientôt</title>
    <style>
        body {
            margin: 0;
            padding: 0;
            font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif;
            background-color: #f8fafc;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
        }

        .wrapper {
            width: 100%;
            background-color: #f8fafc;
            padding: 40px 0;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05);
        }

        .header {
            background: linear-gradient(135deg, #F6475F 0%, #D5384E 100%);
            padding: 32px 20px;
            text-align: center;
        }

        .hero {
            padding: 40px 32px 20px;
            text-align: center;
        }

        .hero h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 700;
            color: #0f172a;
            letter-spacing: -0.5px;
        }

        .content {
            padding: 0 32px 32px;
            font-size: 16px;
            line-height: 1.6;
            color: #475569;
            text-align: center;
        }

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
            text-align: left;
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

        .detail-card tr+tr td {
            border-top: 1px solid #f1f5f9;
        }

        .button-wrapper {
            margin: 28px 0;
            text-align: center;
        }

        .button {
            background-color: #F6475F;
            color: #ffffff !important;
            padding: 14px 32px;
            border-radius: 8px;
            text-decoration: none;
            font-weight: 600;
            display: inline-block;
            box-shadow: 0 4px 6px -1px rgba(246, 71, 95, 0.2);
        }

        .info-box {
            background-color: #fff7ed;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
            font-size: 14px;
            text-align: left;
            color: #9a3412;
            border: 1px solid #fed7aa;
        }

        .footer {
            background-color: #f1f5f9;
            padding: 24px;
            text-align: center;
            font-size: 12px;
            color: #64748b;
            border-top: 1px solid #e2e8f0;
        }

        .footer a {
            color: #F6475F;
            text-decoration: none;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                <img src="{{ asset('images/logo.png') }}" alt="KeyHome Logo" style="max-width: 150px; height: auto;">
            </div>

            <div class="hero">
                <h1>Votre abonnement expire bientôt ⏰</h1>
            </div>

            <div class="content">
                <span class="countdown-badge">{{ $days }} jour(s) restant(s)</span>

                <p>Bonjour l'équipe <strong>{{ $agencyName }}</strong>,</p>
                <p>Nous voulions vous informer que votre abonnement arrive bientôt à expiration. Pour éviter toute
                    interruption de vos services, pensez à le renouveler.</p>

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

                <div class="button-wrapper">
                    <a href="{{ config('app.url') . '/agency' }}" class="button">Renouveler mon abonnement</a>
                </div>

                <div class="info-box">
                    <strong>⚠️ Que se passe-t-il après expiration ?</strong><br>
                    Vos annonces ne seront plus boostées et les limites de votre plan ne seront plus actives. Renouvelez
                    dès maintenant pour maintenir vos avantages.
                </div>
            </div>

            <div class="footer">
                <p>&copy; {{ date('Y') }} KeyHome. Tous droits réservés.</p>
                <p>Si vous avez des questions, n'hésitez pas à répondre à cet email.</p>
            </div>
        </div>
    </div>
</body>

</html>
