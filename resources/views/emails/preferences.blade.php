<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Préférences email — {{ config('app.name') }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif; background: #f8fafc; color: #1e293b; min-height: 100vh; display: flex; align-items: center; justify-content: center; padding: 1rem; }
        .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); max-width: 540px; width: 100%; padding: 2.5rem; }
        h1 { font-size: 1.5rem; font-weight: 700; margin-bottom: 0.5rem; text-align: center; }
        .subtitle { color: #64748b; text-align: center; margin-bottom: 2rem; }
        .pref-item { display: flex; align-items: center; justify-content: space-between; padding: 1rem 0; border-bottom: 1px solid #f1f5f9; }
        .pref-item:last-child { border-bottom: none; }
        .pref-label { font-weight: 500; }
        .pref-desc { font-size: 0.85rem; color: #64748b; margin-top: 0.25rem; }
        .toggle { position: relative; width: 48px; height: 26px; flex-shrink: 0; margin-left: 1rem; }
        .toggle input { opacity: 0; width: 0; height: 0; }
        .slider { position: absolute; cursor: pointer; inset: 0; background: #cbd5e1; border-radius: 26px; transition: 0.3s; }
        .slider::before { content: ""; position: absolute; height: 20px; width: 20px; left: 3px; bottom: 3px; background: #fff; border-radius: 50%; transition: 0.3s; }
        input:checked + .slider { background: #F6475F; }
        input:checked + .slider::before { transform: translateX(22px); }
        .btn-group { display: flex; gap: 1rem; margin-top: 2rem; }
        .btn { flex: 1; display: inline-block; padding: 0.75rem 1.5rem; text-align: center; text-decoration: none; font-weight: 600; border-radius: 8px; border: none; cursor: pointer; font-size: 1rem; transition: background 0.2s; }
        .btn-primary { background: #F6475F; color: #fff; }
        .btn-primary:hover { background: #D93A50; }
        .btn-secondary { background: #f1f5f9; color: #475569; }
        .btn-secondary:hover { background: #e2e8f0; }
        .success { background: #dcfce7; color: #166534; padding: 0.75rem 1rem; border-radius: 8px; text-align: center; margin-bottom: 1.5rem; }
        .footer { text-align: center; margin-top: 2rem; font-size: 0.8rem; color: #94a3b8; }
    </style>
</head>
<body>
    <div class="card">
        <h1>Préférences email</h1>
        <p class="subtitle">Choisissez les notifications que vous souhaitez recevoir.</p>

        @if(session('success'))
            <div class="success">{{ session('success') }}</div>
        @endif

        <form method="POST" action="{{ route('email.preferences.update', $preference->unsubscribe_token) }}">
            @csrf

            <div class="pref-item">
                <div>
                    <div class="pref-label">Mises à jour d'annonces</div>
                    <div class="pref-desc">Notifications d'approbation, de refus et de rapports sur vos annonces.</div>
                </div>
                <label class="toggle">
                    <input type="hidden" name="ad_updates" value="0">
                    <input type="checkbox" name="ad_updates" value="1" {{ $preference->ad_updates ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="pref-item">
                <div>
                    <div class="pref-label">Alertes de recherche</div>
                    <div class="pref-desc">Nouvelles annonces correspondant à vos critères de recherche.</div>
                </div>
                <label class="toggle">
                    <input type="hidden" name="search_alerts" value="0">
                    <input type="checkbox" name="search_alerts" value="1" {{ $preference->search_alerts ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="pref-item">
                <div>
                    <div class="pref-label">Abonnements</div>
                    <div class="pref-desc">Renouvellements, expirations et confirmations d'abonnement.</div>
                </div>
                <label class="toggle">
                    <input type="hidden" name="subscription_updates" value="0">
                    <input type="checkbox" name="subscription_updates" value="1" {{ $preference->subscription_updates ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="pref-item">
                <div>
                    <div class="pref-label">Enquêtes et sondages</div>
                    <div class="pref-desc">Notifications relatives aux enquêtes et vos réponses.</div>
                </div>
                <label class="toggle">
                    <input type="hidden" name="survey_notifications" value="0">
                    <input type="checkbox" name="survey_notifications" value="1" {{ $preference->survey_notifications ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="pref-item">
                <div>
                    <div class="pref-label">Notifications administratives</div>
                    <div class="pref-desc">Actions et alertes liées à votre compte par l'administration.</div>
                </div>
                <label class="toggle">
                    <input type="hidden" name="admin_notifications" value="0">
                    <input type="checkbox" name="admin_notifications" value="1" {{ $preference->admin_notifications ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="pref-item">
                <div>
                    <div class="pref-label">Emails de bienvenue</div>
                    <div class="pref-desc">Messages d'accueil et d'intégration lors de votre inscription.</div>
                </div>
                <label class="toggle">
                    <input type="hidden" name="welcome_emails" value="0">
                    <input type="checkbox" name="welcome_emails" value="1" {{ $preference->welcome_emails ? 'checked' : '' }}>
                    <span class="slider"></span>
                </label>
            </div>

            <div class="btn-group">
                <button type="submit" class="btn btn-primary">Enregistrer</button>
                <a href="{{ config('app.frontend_url', config('app.url')) }}" class="btn btn-secondary">Retour</a>
            </div>
        </form>

        <div class="footer">
            <p>Les emails transactionnels (vérification, mot de passe, confirmations de paiement) ne peuvent pas être désactivés.</p>
            <p style="margin-top: 0.5rem;">© {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>
