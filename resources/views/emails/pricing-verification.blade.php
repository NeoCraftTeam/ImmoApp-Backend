<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code de vérification — Tarification</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; background-color: #f8fafc; padding: 40px 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        .header { background: linear-gradient(135deg, #F6475F 0%, #D5384E 100%); padding: 32px 20px; text-align: center; }
        .hero { padding: 40px 32px 20px; text-align: center; }
        .hero h1 { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; letter-spacing: -0.5px; }
        .content { padding: 0 32px 32px; font-size: 16px; line-height: 1.6; color: #475569; text-align: center; }
        .code-box { margin: 32px auto; padding: 20px 40px; background-color: #f8fafc; border: 2px dashed #F6475F; border-radius: 12px; display: inline-block; }
        .code { font-size: 36px; font-weight: 800; letter-spacing: 12px; color: #F6475F; font-family: 'Courier New', Courier, monospace; }
        .timer { display: inline-block; margin-top: 16px; padding: 8px 16px; background-color: #fef3c7; border-radius: 20px; font-size: 13px; font-weight: 600; color: #92400e; }
        .info-box { background-color: #fff1f2; border-radius: 8px; padding: 16px; margin-top: 24px; font-size: 14px; text-align: left; color: #9f1239; border: 1px solid #fecdd3; }
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
                <h1>Vérification de sécurité</h1>
            </div>

            <div class="content">
                <p>Bonjour <strong>{{ $user->firstname }}</strong>,</p>
                <p>Vous avez demandé à modifier la tarification de l'application KeyHome. Pour confirmer cette opération sensible, veuillez utiliser le code ci-dessous :</p>

                <div class="code-box">
                    <div class="code">{{ $code }}</div>
                </div>

                <div>
                    <span class="timer">⏱ Ce code expire dans 10 minutes</span>
                </div>

                <div class="info-box">
                    <strong>⚠️ Vous n'avez pas fait cette demande ?</strong><br>
                    Si vous n'êtes pas à l'origine de cette action, ignorez cet email. Aucune modification ne sera effectuée sans saisie du code. Nous vous recommandons de changer votre mot de passe par mesure de sécurité.
                </div>
            </div>

            <div class="footer">
                <p>&copy; {{ date('Y') }} KeyHome. Tous droits réservés.</p>
                <p>Cet email a été envoyé suite à une demande de modification de tarification depuis le panneau d'administration.</p>
            </div>
        </div>
    </div>
</body>
</html>
