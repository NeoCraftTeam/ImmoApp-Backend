<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vérification de votre email</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; background-color: #f8fafc; padding: 40px 0; }
        .container { max-width: 600px; margin: 0 auto; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); }
        .header { background: linear-gradient(135deg, #F6475F 0%, #D5384EFF 100%); padding: 40px 20px; text-align: center; }
        .logo { font-size: 28px; font-weight: 800; color: #ffffff; text-decoration: none; letter-spacing: -0.5px; display: inline-block; }
        .hero { padding: 40px 32px 20px; text-align: center; }
        .hero h1 { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; letter-spacing: -0.5px; }
        .content { padding: 0 32px 32px; font-size: 16px; line-height: 1.6; color: #475569; text-align: center; }
        .button-wrapper { margin: 32px 0; }
        .button { background-color: #F6475F; color: #ffffff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; box-shadow: 0 4px 6px -1px rgba(246, 71, 95, 0.2); transition: background-color 0.2s; }
        .button:hover { background-color: #D5384EFF; }
        .footer { background-color: #f1f5f9; padding: 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
        .footer a { color: #F6475F; text-decoration: none; }
        .info-box { background-color: #fff1f2; border-radius: 8px; padding: 16px; margin-top: 24px; font-size: 14px; text-align: left; color: #9f1239; border: 1px solid #fecdd3; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div class="header">
                 <!-- Remplacez par votre URL de logo vraie si disponible -->
                 <div class="logo">KeyHome</div>
            </div>
            
            <div class="hero">
                <h1>Bienvenue dans l'aventure !</h1>
            </div>

            <div class="content">
                <p>Bonjour <strong>{{ $user->firstname }}</strong>,</p>
                <p>Nous sommes ravis de vous compter parmi nous. KeyHome est la solution idéale pour gérer vos biens et trouver les meilleures opportunités immobilières.</p>
                <p>Pour sécuriser votre compte et débloquer toutes les fonctionnalités de la plateforme, merci de confirmer votre adresse email.</p>

                <div class="button-wrapper">
                    <a href="{{ $url }}" class="button">Confirmer mon compte</a>
                </div>

                <div class="info-box">
                    <strong>Pourquoi confirmer ?</strong><br>
                    Cela nous permet de vous envoyer des alertes importantes et de garantir que personne d'autre n'utilise votre adresse email.
                </div>
            </div>

            <div class="footer">
                <p>&copy; {{ date('Y') }} ImmoApp. Tous droits réservés.</p>
                <p>Vous avez reçu cet email car vous vous êtes inscrit sur notre plateforme.<br>Si ce n'est pas vous, <a href="#">ignorez cet email</a>.</p>
            </div>
        </div>
    </div>
</body>
</html>
