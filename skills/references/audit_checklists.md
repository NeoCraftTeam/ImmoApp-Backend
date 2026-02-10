# Checklists d'Audit et de Production

## 1. Audit de Sécurité Backend (Laravel/PHP)

### Vulnérabilités SQL
- Rechercher l'utilisation de `selectRaw`, `whereRaw`, `orderByRaw`.
- Vérifier que les paramètres sont passés via des requêtes préparées (placeholders `?` ou `:name`).
- Préférer `DB::raw()` à l'intérieur de méthodes structurées.

### Authentification et Autorisation
- Vérifier la protection des routes par middleware (`auth:sanctum`, `auth:api`).
- Contrôler les politiques d'accès (`Policies`) pour les ressources sensibles.

### Webhooks et Intégrations Tierces
- Vérifier la validation des signatures de webhooks (ex: FedaPay, Stripe).
- S'assurer que les secrets de webhook ne sont pas codés en dur.

## 2. Audit Mobile (React Native / WebView)

### Sécurité WebView
- `originWhitelist` : Doit être restreint aux domaines de confiance.
- `allowFileAccess` : Doit être à `false` sauf besoin spécifique.
- `onMessage` : Valider systématiquement l'origine et le contenu des messages.

### Configuration de Production
- Vérifier que les URLs pointent vers des serveurs HTTPS.
- S'assurer que les logs de débogage sont désactivés en production.

## 3. Checklist de Mise en Production

### Infrastructure
- [ ] `APP_ENV=production`
- [ ] `APP_DEBUG=false`
- [ ] Configuration du cache (`php artisan optimize`)
- [ ] Configuration des files d'attente (Workers/Redis)
- [ ] Configuration du stockage cloud (S3)

### Maintenance
- [ ] Cron job pour `php artisan schedule:run`
- [ ] Monitoring d'erreurs (Sentry/LogRocket)
- [ ] Sauvegardes de base de données automatisées
