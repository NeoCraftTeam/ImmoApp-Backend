<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Désabonnement — {{ config('app.name') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 480px; width: 100%; padding: 2.5rem; text-align: center; }
        .icon { font-size: 3rem; margin-bottom: 1rem; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; }
        p { color: #64748b; line-height: 1.6; margin-bottom: 1.5rem; }
        .btn { display: inline-block; padding: 0.75rem 2rem; background: #F6475F; color: #fff; text-decoration: none; border-radius: 8px; font-weight: 600; transition: background 0.2s; }
        .btn:hover { background: #D93A50; }
        .footer { margin-top: 2rem; font-size: 0.8rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="card">
        <div class="icon">✅</div>
        <h1>Désabonnement confirmé</h1>
        @if($category === 'all')
            <p>Vous avez été désabonné de toutes les notifications email de {{ config('app.name') }}. Vous continuerez à recevoir les emails transactionnels essentiels (vérification, réinitialisation de mot de passe, etc.).</p>
        @else
            <p>Vos préférences email ont été mises à jour. La catégorie <strong>{{ str_replace('_', ' ', $category) }}</strong> a été désactivée.</p>
        @endif
        <a href="{{ config('app.frontend_url', config('app.url')) }}" class="btn">Retour à {{ config('app.name') }}</a>
        <div class="footer">
            <p>© {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
