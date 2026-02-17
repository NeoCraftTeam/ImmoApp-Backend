<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonce approuvée</title>
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
            background-color: #f0fdf4;
            color: #166534;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
            border: 1px solid #bbf7d0;
        }

        .recap-card {
            background-color: #f8fafc;
            border-radius: 10px;
            padding: 20px 24px;
            margin: 24px 0;
            text-align: left;
            border: 1px solid #e2e8f0;
        }

        .recap-card h3 {
            margin: 0 0 12px 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
        }

        .recap-row {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #f1f5f9;
        }

        .recap-row:last-child {
            border-bottom: none;
        }

        .recap-label {
            color: #64748b;
            font-size: 14px;
        }

        .recap-value {
            color: #0f172a;
            font-weight: 600;
            font-size: 14px;
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
                <h1>Annonce approuvée ✅</h1>
            </div>

            <div class="content">
                <span class="status-badge">✓ Publiée et visible</span>

                <p>Bonjour <strong>{{ $authorName }}</strong>,</p>
                <p>Excellente nouvelle ! Votre annonce a été <strong>validée</strong> par notre équipe de modération et
                    est désormais <strong>visible par tous les utilisateurs</strong> de la plateforme.</p>

                <div class="recap-card">
                    <h3>Récapitulatif</h3>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 8px 0; color: #64748b; font-size: 14px;">Titre</td>
                            <td
                                style="padding: 8px 0; text-align: right; font-weight: 600; color: #0f172a; font-size: 14px;">
                                {{ $adTitle }}</td>
                        </tr>
                        <tr>
                            <td style="padding: 8px 0; color: #64748b; font-size: 14px; border-top: 1px solid #f1f5f9;">
                                Prix</td>
                            <td
                                style="padding: 8px 0; text-align: right; font-weight: 600; color: #F6475F; font-size: 14px; border-top: 1px solid #f1f5f9;">
                                {{ $adPrice }}</td>
                        </tr>
                    </table>
                </div>

                <p style="margin-top: 24px;">Merci de votre confiance et bonne publication !</p>
            </div>

            <div class="footer">
                <p>&copy; {{ date('Y') }} KeyHome. Tous droits réservés.</p>
                <p>Cet email confirme l'approbation de votre annonce sur la plateforme.</p>
            </div>
        </div>
    </div>
</body>

</html>
