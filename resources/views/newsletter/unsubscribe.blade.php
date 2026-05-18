<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>{{ $success ? 'Désinscription confirmée' : 'Lien invalide' }} — {{ config('app.name') }}</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px 16px;
        }

        .card {
            background: #ffffff;
            border-radius: 16px;
            border: 1px solid #e2e8f0;
            padding: 48px 40px;
            max-width: 480px;
            width: 100%;
            text-align: center;
            box-shadow: 0 4px 24px rgba(0,0,0,0.06);
        }

        .icon {
            width: 64px;
            height: 64px;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            font-size: 28px;
            margin-bottom: 24px;
        }

        .icon-success { background-color: #f0fdf4; }
        .icon-error   { background-color: #fff1f2; }

        .logo {
            display: block;
            margin: 0 auto 32px auto;
            height: 32px;
            width: auto;
        }

        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            margin-bottom: 12px;
        }

        p {
            font-size: 15px;
            color: #475569;
            line-height: 1.6;
            margin-bottom: 10px;
        }

        .email-badge {
            display: inline-block;
            background: #f1f5f9;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 4px 12px;
            font-size: 13px;
            font-weight: 600;
            color: #334155;
            font-family: 'SFMono-Regular', Consolas, monospace;
            margin: 8px 0 16px 0;
        }

        .accent-bar {
            height: 4px;
            background: #f43f5e;
            border-radius: 16px 16px 0 0;
            margin: -48px -40px 48px -40px;
        }

        .btn {
            display: inline-block;
            margin-top: 24px;
            background-color: #f43f5e;
            color: #ffffff;
            text-decoration: none;
            font-size: 14px;
            font-weight: 700;
            padding: 12px 28px;
            border-radius: 8px;
        }

        .footer-note {
            margin-top: 28px;
            font-size: 12px;
            color: #94a3b8;
        }

        .footer-note a {
            color: #f43f5e;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="card">
        <div class="accent-bar"></div>

        <img src="{{ rtrim(config('app.mail_asset_base_url', config('app.url')), '/') . '/images/keyhomelogo_email.png' }}"
             alt="{{ config('app.name') }}"
             class="logo" />

        @if($success)
            <div class="icon icon-success">✓</div>
            <h1>Désinscription confirmée</h1>
            <p>Votre désinscription de la newsletter <strong>{{ config('app.name') }}</strong> a bien été prise en compte.</p>
            @if($email)
                <div class="email-badge">{{ $email }}</div>
            @endif
            <p>Vous ne recevrez plus aucun e-mail de notre part lié à la newsletter. Les e-mails transactionnels liés à votre compte (sécurité, réservations, etc.) continueront d'être envoyés normalement.</p>
        @else
            <div class="icon icon-error">✕</div>
            <h1>Lien invalide ou expiré</h1>
            <p>Ce lien de désinscription est introuvable ou a déjà été utilisé. Si vous êtes toujours abonné(e) et souhaitez vous désabonner, contactez-nous directement.</p>
        @endif

        <a href="{{ config('app.frontend_url', config('app.url')) }}" class="btn">
            Retour sur {{ config('app.name') }}
        </a>

        <p class="footer-note">
            Un problème ? Contactez-nous à
            <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
        </p>
    </div>
</body>
</html>
