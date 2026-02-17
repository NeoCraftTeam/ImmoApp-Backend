<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Annonce non approuvée</title>
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
            background-color: #fef2f2;
            color: #991b1b;
            padding: 8px 20px;
            border-radius: 20px;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 24px;
            border: 1px solid #fecaca;
        }

        .reason-box {
            background-color: #fff7ed;
            border-radius: 10px;
            padding: 20px 24px;
            margin: 24px 0;
            text-align: left;
            border: 1px solid #fed7aa;
        }

        .reason-box h3 {
            margin: 0 0 8px 0;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: #9a3412;
            font-weight: 600;
        }

        .reason-box p {
            margin: 0;
            color: #9a3412;
            font-size: 14px;
            line-height: 1.6;
        }

        .info-box {
            background-color: #eff6ff;
            border-radius: 8px;
            padding: 16px;
            margin-top: 24px;
            font-size: 14px;
            text-align: left;
            color: #1e40af;
            border: 1px solid #bfdbfe;
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
                <h1>Annonce non approuvée</h1>
            </div>

            <div class="content">
                <span class="status-badge">✕ Non publiée</span>

                <p>Bonjour <strong>{{ $authorName }}</strong>,</p>
                <p>Nous vous informons que votre annonce <strong>« {{ $adTitle }} »</strong> n'a malheureusement
                    <strong>pas été approuvée</strong> par notre équipe de modération.</p>

                @if($reason)
                    <div class="reason-box">
                        <h3>⚠️ Motif du refus</h3>
                        <p>{{ $reason }}</p>
                    </div>
                @endif

                <p>Vous pouvez soumettre une nouvelle annonce en tenant compte de ces remarques.</p>

                <div class="info-box">
                    <strong>💡 Besoin d'aide ?</strong><br>
                    Si vous pensez qu'il s'agit d'une erreur, n'hésitez pas à nous contacter en répondant directement à
                    cet email. Notre équipe se fera un plaisir de vous aider.
                </div>
            </div>

            <div class="footer">
                <p>&copy; {{ date('Y') }} KeyHome. Tous droits réservés.</p>
                <p>Cet email vous informe du statut de votre soumission d'annonce.</p>
            </div>
        </div>
    </div>
</body>

</html>
