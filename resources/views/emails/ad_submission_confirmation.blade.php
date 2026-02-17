<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonce reçue</title>
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

        .status-badge {
            display: inline-block;
            background-color: #fefce8;
            color: #854d0e;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
            border: 1px solid #fde68a;
        }

        .steps {
            background-color: #f8fafc;
            border-radius: 10px;
            padding: 24px;
            margin: 24px 0;
            text-align: left;
            border: 1px solid #e2e8f0;
        }

        .step {
            display: flex;
            align-items: flex-start;
            margin-bottom: 16px;
        }

        .step:last-child {
            margin-bottom: 0;
        }

        .step-number {
            background-color: #F6475F;
            color: #ffffff;
            width: 28px;
            min-width: 28px;
            height: 28px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 13px;
            font-weight: 700;
            margin-right: 12px;
            line-height: 28px;
            text-align: center;
        }

        .step-text {
            color: #475569;
            font-size: 14px;
            line-height: 1.5;
        }

        .step-text strong {
            color: #0f172a;
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
                <h1>Annonce reçue 📋</h1>
            </div>

            <div class="content">
                <span class="status-badge">⏳ En attente de validation</span>

                <p>Bonjour <strong>{{ $authorName }}</strong>,</p>
                <p>Nous avons bien reçu votre annonce <strong>« {{ $adTitle }} »</strong>. Elle est actuellement en
                    cours de vérification par notre équipe.</p>

                <div class="steps">
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; vertical-align: top; width: 40px;">
                                <div
                                    style="background-color: #22c55e; color: #ffffff; width: 28px; height: 28px; border-radius: 50%; text-align: center; line-height: 28px; font-size: 13px; font-weight: 700;">
                                    ✓</div>
                            </td>
                            <td style="padding: 8px 0; vertical-align: top; color: #475569; font-size: 14px;">
                                <strong style="color: #0f172a;">Soumission</strong><br>Votre annonce a été envoyée avec
                                succès
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; vertical-align: top; width: 40px;">
                                <div
                                    style="background-color: #F6475F; color: #ffffff; width: 28px; height: 28px; border-radius: 50%; text-align: center; line-height: 28px; font-size: 13px; font-weight: 700;">
                                    2</div>
                            </td>
                            <td style="padding: 8px 0; vertical-align: top; color: #475569; font-size: 14px;">
                                <strong style="color: #0f172a;">Vérification</strong><br>Notre équipe examine votre
                                annonce
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; vertical-align: top; width: 40px;">
                                <div
                                    style="background-color: #e2e8f0; color: #94a3b8; width: 28px; height: 28px; border-radius: 50%; text-align: center; line-height: 28px; font-size: 13px; font-weight: 700;">
                                    3</div>
                            </td>
                            <td style="padding: 8px 0; vertical-align: top; color: #94a3b8; font-size: 14px;">
                                <strong>Publication</strong><br>Vous serez notifié dès que votre annonce sera en ligne
                            </td>
                        </tr>
                    </table>
                </div>

                <p>Merci de votre confiance !</p>
            </div>

            <div class="footer">
                <p>&copy; {{ date('Y') }} KeyHome. Tous droits réservés.</p>
                <p>Cet email confirme la réception de votre annonce.</p>
            </div>
        </div>
    </div>
</body>

</html>
