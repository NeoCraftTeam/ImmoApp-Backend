<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email Vérifié - KeyHome</title>
    <style>
        body { margin: 0; padding: 0; font-family: 'Helvetica Neue', Helvetica, Arial, sans-serif; background-color: #f8fafc; color: #1e293b; -webkit-font-smoothing: antialiased; }
        .wrapper { width: 100%; height: 100vh; display: flex; align-items: center; justify-content: center; background-color: #f8fafc; }
        .container { max-width: 500px; width: 100%; background-color: #ffffff; border-radius: 12px; overflow: hidden; box-shadow: 0 10px 15px -3px rgba(0, 0, 0, 0.1), 0 4px 6px -2px rgba(0, 0, 0, 0.05); text-align: center; }
        .header { background: linear-gradient(135deg, #F6475F 0%, #D5384EFF 100%); padding: 30px 20px; }
        .logo { max-width: 120px; height: auto; }
        .content { padding: 40px 32px; }
        h1 { margin: 0 0 16px; font-size: 24px; font-weight: 700; color: #0f172a; }
        p { margin: 0 0 24px; font-size: 16px; line-height: 1.6; color: #475569; }
        .button { background-color: #F6475F; color: #ffffff !important; padding: 12px 32px; border-radius: 8px; text-decoration: none; font-weight: 600; display: inline-block; transition: background-color 0.2s; }
        .button:hover { background-color: #D5384EFF; }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">
            <div>
                 <img src="{{ asset('images/logo.png') }}" alt="KeyHome Logo" class="logo">
            </div>
            <div class="content">
                <h1>Email Vérifié !</h1>
                <p>Votre adresse email a été confirmée avec succès. Vous pouvez maintenant fermer cette page ou retourner à l'application.</p>
                <!-- Optional: Frontend link if known -->
                <!-- <a href="/" class="button">Retour à l'accueil</a> -->
            </div>
        </div>
    </div>
</body>
</html>
