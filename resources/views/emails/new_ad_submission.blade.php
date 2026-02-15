<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Nouvelle annonce à valider</title>
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

        .detail-card {
            background-color: #f8fafc;
            border-radius: 10px;
            padding: 20px 24px;
            margin: 16px 0;
            border: 1px solid #e2e8f0;
        }

        .detail-card h3 {
            margin: 0 0 12px 0;
            font-size: 13px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #64748b;
            font-weight: 600;
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
                <h1>Nouvelle annonce à valider 🔔</h1>
            </div>

            <div class="content" style="text-align: center;">
                <span class="status-badge">⏳ En attente de validation</span>

                <p style="text-align: left;">Bonjour,</p>
                <p style="text-align: left;">Une nouvelle annonce vient d'être soumise et nécessite votre validation.
                </p>

                <div class="detail-card">
                    <h3>👤 Annonceur</h3>
                    <table>
                        <tr>
                            <td>Nom</td>
                            <td>{{ $authorName }}</td>
                        </tr>
                        <tr>
                            <td>Email</td>
                            <td>{{ $authorEmail }}</td>
                        </tr>
                        <tr>
                            <td>Rôle</td>
                            <td>{{ $authorRole }}</td>
                        </tr>
                        <tr>
                            <td>Type</td>
                            <td>{{ $authorType }}</td>
                        </tr>
                    </table>
                </div>

                <div class="detail-card">
                    <h3>🏠 Annonce</h3>
                    <table>
                        <tr>
                            <td>Titre</td>
                            <td>{{ $adTitle }}</td>
                        </tr>
                        <tr>
                            <td>Prix</td>
                            <td style="color: #F6475F !important;">{{ $adPrice }}</td>
                        </tr>
                        <tr>
                            <td>Type</td>
                            <td>{{ $adType }}</td>
                        </tr>
                        <tr>
                            <td>Quartier</td>
                            <td>{{ $adQuarter }}</td>
                        </tr>
                    </table>
                </div>

                <div class="button-wrapper">
                    <a href="{{ $url }}" class="button">Voir l'annonce dans le panneau admin</a>
                </div>
            </div>

            <div class="footer">
                <p>&copy; {{ date('Y') }} KeyHome. Tous droits réservés.</p>
                <p>Cet email est une notification automatique destinée aux administrateurs.</p>
            </div>
        </div>
    </div>
</body>

</html>
