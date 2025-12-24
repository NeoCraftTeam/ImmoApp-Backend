# 💳 Guide d'Intégration FedaPay (Frontend & Mobile)

Ce guide détaille les étapes nécessaires pour intégrer le déblocage des annonces via FedaPay dans l'application KeyHome (Mobile ou Web).

---

## 🔄 Flux de Paiement (Workflow)

1.  **Demande** : Le frontend appelle l'API pour initialiser le paiement.
2.  **Interface de Paiement** : L'app ouvre une **WebView** ou un navigateur vers l'URL reçue.
3.  **Paiement** : L'utilisateur effectue son paiement (Orange Money, MTN, Carte, etc.).
4.  **Redirection** : FedaPay redirige l'utilisateur vers l'URL de `callback` du serveur.
5.  **Finalisation** : Le frontend intercepte cette redirection pour fermer la WebView et rafraîchir l'annonce.

---

## 🛠️ Endpoints API

### 1. Initialiser le paiement
*   **URL** : `POST /api/v1/payments/initialize/{ad_id}`
*   **Auth** : `Bearer Token` requis.
*   **Réponses possibles** :
    *   **200 (Success)** : `{"payment_url": "...", "message": "..."}` -> Rediriger l'utilisateur.
    *   **200 (Already Paid)** : `{"message": "Annonce déjà débloquée.", "status": "already_paid"}` -> Ne rien faire, l'annonce est déjà libre.
    *   **200 (Owner)** : `{"message": "Vous êtes le propriétaire...", "status": "owner"}` -> L'accès est gratuit pour le propriétaire.
    *   **401** : Utilisateur non connecté.
    *   **404** : Annonce introuvable.

---

## 📱 Implémentation Mobile (Flutter / React Native)

### Étape 1 : Ouverture de la WebView
Utilisez un plugin comme `webview_flutter` ou `react-native-webview`. 

### Étape 2 : Intercepter la Navigation (Crucial)
Vous devez surveiller l'URL de la WebView à chaque changement de page. 
*   **Cible** : Si l'URL contient `/api/v1/payments/callback`, cela signifie que le paiement est fini (qu'il ait réussi ou échoué).
*   **Action** : 
    1. Fermez la WebView immédiatement.
    2. Affichez un petit "Loader" pendant 2-3 secondes (le temps que le Webhook valide le paiement côté serveur).
    3. Rafraîchissez l'objet `Ad` depuis l'API pour afficher les numéros de téléphone débloqués.

---

## 🧪 Mode Test (Sandbox)
Pour tester sans payer :
1.  Ouvrez le lien `payment_url`.
2.  Sur l'interface FedaPay, choisissez n'importe quel mode.
3.  Utilisez n'importe quel numéro (ex: `66000001`).
4.  **Très important** : Un bouton bleu **"Approve"** (ou simulateur) apparaîtra. Cliquez dessus pour simuler un vrai succès.

---

## 💡 Conseils Pro
*   **Polling** : Si après la fermeture de la WebView l'annonce n'est pas encore marquée comme débloquée, faites un deuxième appel API après 2 secondes car le Webhook peut mettre un court instant à arriver.
*   **UX** : Ne laissez pas l'utilisateur sur la page de succès de FedaPay. La redirection vers l'URL de `callback` est votre signal pour reprendre le contrôle de l'application.
