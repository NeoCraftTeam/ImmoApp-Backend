# 🔐 Connexion Persistante - Applications Mobiles KeyHome

## Comment ça fonctionne

### Mécanisme actuel (Déjà en place)

Grâce à la configuration de la WebView React Native, la connexion persiste automatiquement :

1. **Cookies partagés** : `sharedCookiesEnabled={true}`
   - Les cookies de session Laravel sont sauvegardés sur le téléphone
   
2. **Stockage DOM** : `domStorageEnabled={true}`
   - Le localStorage de Filament est persisté entre les sessions
   
3. **Cache activé** : `cacheEnabled={true}`
   - Les données sont mises en cache localement
   
4. **Mode non-incognito** : `incognito={false}`
   - Les données persistent même après fermeture de l'app

### Durée de connexion

- **Session normale** : 7 jours (10080 minutes)
- **Avec "Remember Me"** : 5 ans (cookie `remember_token`)

## Configuration appliquée

### 1. Panels Filament (Agency & Bailleur)

```php
->login()
->passwordReset()  // Permet la réinitialisation de mot de passe
```

La case "Se souvenir de moi" est disponible sur la page de login Filament.

### 2. Session Laravel

**Fichier** : `.env`
```env
SESSION_LIFETIME=10080  # 7 jours en minutes
SESSION_DRIVER=database # Stockage en base de données
```

### 3. WebView React Native

**Fichiers** : `mobile/agency/App.js` & `mobile/bailleur/App.js`

```javascript
<WebView 
  sharedCookiesEnabled={true}
  thirdPartyCookiesEnabled={true}
  domStorageEnabled={true}
  cacheEnabled={true}
  incognito={false}
  // ...
/>
```

## Comportement utilisateur

### Première connexion
1. L'utilisateur ouvre l'app
2. Il se connecte avec email/mot de passe
3. Il coche "Se souvenir de moi" (optionnel mais recommandé)
4. Laravel crée une session + cookie remember_token

### Ouvertures suivantes
1. L'utilisateur ouvre l'app
2. La WebView envoie automatiquement les cookies
3. Laravel reconnaît la session
4. **L'utilisateur est automatiquement connecté** ✅

### Déconnexion
- L'utilisateur doit cliquer sur "Se déconnecter" dans l'app
- Ou la session expire après 7 jours d'inactivité
- Ou le remember_token expire après 5 ans

## Sécurité

### Mesures en place
- ✅ Cookies HTTPS only (en production)
- ✅ Cookies HttpOnly (protection XSS)
- ✅ SameSite=Lax (protection CSRF)
- ✅ Token chiffré en base de données
- ✅ Expiration automatique

### Bonnes pratiques
- Les utilisateurs peuvent se déconnecter manuellement
- Les sessions expirent automatiquement
- Les tokens sont révoqués en cas de changement de mot de passe

## Test

### Pour tester la persistance :
1. Ouvre l'app mobile
2. Connecte-toi avec un compte
3. Coche "Se souvenir de moi"
4. Ferme complètement l'app (swipe up)
5. Rouvre l'app
6. **Tu devrais être automatiquement connecté** ✅

### Pour forcer la déconnexion :
1. Va dans le profil
2. Clique sur "Se déconnecter"
3. Ou supprime les données de l'app depuis les paramètres iOS/Android

## Troubleshooting

### L'utilisateur est déconnecté à chaque ouverture

**Causes possibles** :
- Les cookies ne sont pas sauvegardés (vérifier `sharedCookiesEnabled`)
- La session a expiré (vérifier `SESSION_LIFETIME`)
- L'app est en mode incognito (vérifier `incognito={false}`)

**Solution** :
- Vérifier la configuration WebView
- Augmenter `SESSION_LIFETIME` si nécessaire
- S'assurer que l'utilisateur coche "Se souvenir de moi"

### La session expire trop vite

**Solution** :
- Augmenter `SESSION_LIFETIME` dans `.env`
- Utiliser le cookie "Remember Me" (5 ans)

## Notes importantes

- ⚠️ En développement local, restaure `.env` après les tests :
  ```bash
  mv .env.bak .env
  ```

- 📱 Sur VPS, assure-toi que `SESSION_LIFETIME=10080` est bien dans le `.env` de production

- 🔒 Le remember_token est automatiquement révoqué si l'utilisateur change son mot de passe
