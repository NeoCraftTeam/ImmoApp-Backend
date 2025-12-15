<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bienvenue sur KeyHome !</title>
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

        .header { background: linear-gradient(135deg, #F6475F 0%, #D5384EFF 100%); padding: 40px 20px; text-align: center; }
        .logo { font-size: 28px; font-weight: 800; color: #ffffff; text-decoration: none; letter-spacing: -0.5px; display: inline-block; }
        .hero { padding: 40px 32px 20px; text-align: center; }
        .hero h1 { margin: 0; font-size: 24px; font-weight: 700; color: #0f172a; letter-spacing: -0.5px; }
        .content { padding: 0 32px 32px; font-size: 16px; line-height: 1.6; color: #475569; text-align: center; }
        .features { background-color: #f1f5f9; padding: 24px; border-radius: 8px; margin: 24px 0; text-align: left; }
        .feature-item { display: flex; align-items: flex-start; margin-bottom: 16px; }
        .feature-item:last-child { margin-bottom: 0; }
        .feature-icon { color: #F6475F; margin-right: 12px; font-size: 18px; line-height: 1.6; font-weight: bold; }
        .button-wrapper { margin: 32px 0; }
        .button { background-color: #F6475F; color: #ffffff !important; padding: 14px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; box-shadow: 0 4px 6px -1px rgba(246, 71, 95, 0.2); transition: background-color 0.2s; }
        .button:hover { background-color: #D5384EFF; }
        .footer { background-color: #f1f5f9; padding: 24px; text-align: center; font-size: 12px; color: #64748b; border-top: 1px solid #e2e8f0; }
        .social-links { margin-top: 12px; }
        .social-links a { color: #64748b; margin: 0 8px; text-decoration: none; }
    </style>
</head>
<body>
<div class="wrapper">
    <div class="container">
        <div class="header">
            <div class="logo">KeyHome</div>
        </div>

        <div class="hero">
            <h1>Félicitations, vous êtes membre !</h1>
        </div>

        <div class="content">
            <p>Bonjour <strong>{{ $user->firstname }}</strong>,</p>
            <p>Votre compte est maintenant vérifié. Vous faites officiellement partie de la communauté KeyHome !</p>

            <p>Nous avons conçu cette plateforme pour vous simplifier la vie immobilière. Voici ce que vous pouvez faire
                dès maintenant :</p>

            <div class="features">
                <div class="feature-item">
                    <span class="feature-icon">></span>
                    <div><strong>Rechercher intelligemment</strong><br>Utilisez nos filtres avancés et la recherche par
                        carte.
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">></span>
                    <div><strong>Créer des alertes</strong><br>Soyez notifié dès qu'un bien correspondant à vos critères
                        est publié.
                    </div>
                </div>
                <div class="feature-item">
                    <span class="feature-icon">></span>
                    <div><strong>Gérer vos favoris</strong><br>Sauvegardez les annonces qui vous plaisent pour les
                        retrouver plus tard.
                    </div>
                </div>
            </div>

            <div class="button-wrapper">
                <!-- URL vers le dashboard ou une page "Tour" du frontend -->
                <a href="{{ config('app.email_verify_callback', 'http://localhost:3000') }}/tour" class="button">Faire
                    le tour du propriétaire</a>
            </div>

            <p>Si vous avez la moindre question, notre équipe support est à votre disposition.</p>
        </div>

        <div class="footer">
            <p>&copy; {{ date('Y') }} ImmoApp. Tous droits réservés.</p>
            <div class="social-links">
                <a href="#">Twitter</a> • <a href="#">Facebook</a> • <a href="#">Instagram</a>
            </div>
        </div>
    </div>
</div>
</body>
</html>
