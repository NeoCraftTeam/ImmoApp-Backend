# Stratégie Email KeyHome — Documentation complète

> **Dernière mise à jour :** Mai 2026  
> **Provider :** Resend (`resend/resend-laravel`)  
> **Domaine vérifié :** `keyhome.app` (DKIM + SPF via Resend dashboard)  
> **Boîtes humaines :** Zoho Mail (support@, marketing@ + alias)

---

## 1. Architecture des expéditeurs

### Principe
Chaque email partant de l'application utilise **une adresse `FROM` sémantiquement cohérente** avec son contenu.
Le trait `HasSender` (`app/Mail/Concerns/HasSender.php`) centralise l'accès à ces adresses via `$this->senderFrom('type')`.

### Les 6 senders applicatifs (Resend)

| Sender | Adresse | Nom affiché | `.env` variable | Rôle |
|---|---|---|---|---|
| `noreply` | `noreply@keyhome.app` | KeyHome | `MAIL_NOREPLY_ADDRESS` | Transactionnel pur — aucune réponse attendue |
| `notifications` | `notifications@keyhome.app` | KeyHome Notifications | `MAIL_NOTIFICATIONS_ADDRESS` | Alertes système comportementales |
| `marketing` | `marketing@keyhome.app` | KeyHome | `MAIL_MARKETING_ADDRESS` | Newsletters, campagnes, promotions |
| `support` | `support@keyhome.app` | KeyHome Support | `MAIL_SUPPORT_ADDRESS` | Service client, formulaire contact |
| `bailleurs` | `bailleurs@keyhome.app` | KeyHome Bailleurs | `MAIL_BAILLEURS_ADDRESS` | Onboarding bailleurs & agences |
| `admin` | `admin@keyhome.app` | KeyHome Admin | `MAIL_ADMIN_ADDRESS` | Notifications internes, modération |

> **Défaut :** Tout Mailable sans `from:` explicite utilise `mail.from.address` = `noreply@keyhome.app`.

---

## 2. Mapping complet des 51 Mailables

### 🔵 noreply@ — Transactionnel pur
*Emails déclenchés automatiquement par une action utilisateur. Aucune réponse humaine attendue.*

| Classe | Sujet | Déclencheur |
|---|---|---|
| `WelcomeEmail` | Bienvenue sur KeyHome | Inscription client |
| `VerificationCodeMail` | Code OTP | Vérification email / connexion |
| `VerifyEmailMail` | Vérifiez votre adresse email | Post-inscription |
| `MagicLinkSignInMail` | Votre lien de connexion | Connexion sans mot de passe |
| `MagicLinkSignUpMail` | Créez votre compte | Inscription via magic link |
| `ForgotPasswordMail` | Réinitialisation de mot de passe | Demande de reset |
| `ResetPasswordMail` | Nouveau mot de passe | Confirmation reset |
| `PasswordChangedMail` | Votre mot de passe a été modifié | Alerte sécurité |
| `EmailUpdatedMail` | Votre email a été mis à jour | Alerte sécurité |
| `NewDeviceSignInMail` | Connexion depuis un nouvel appareil | Alerte sécurité |
| `NewLocationSignInMail` | Connexion depuis une nouvelle localisation | Alerte sécurité |
| `OAuthLinkAttemptMail` | Tentative de liaison OAuth | Alerte sécurité |
| `PasskeyChangedMail` | Votre passkey a été modifiée | Alerte sécurité |
| `PasskeyNotificationMail` | Notification passkey | Gestion passkeys |
| `AdSubmissionConfirmationMail` | Annonce soumise avec succès | Soumission annonce |
| `AdUnlockConfirmationMail` | Annonce débloquée | Déblocage avec crédits |
| `CreditPurchaseConfirmationMail` | Reçu achat de crédits | Paiement points |
| `RefundConfirmationMail` | Confirmation de remboursement | Remboursement traité |
| `SubscriptionInvoiceMail` | Votre facture KeyHome | Facture abonnement |
| `SubscriptionSuccessEmail` | Abonnement activé | Souscription réussie |
| `InvitationMail` | Vous êtes invité à rejoindre l'équipe | Invitation team |
| `GdprDataExportMail` | Votre export de données | Demande RGPD |
| `AccountDeletedMail` | Votre compte a été supprimé | Suppression compte |
| `PricingVerificationMail` | Vérification de tarification | Validation prix annonce |
| `SurveySubmittedMail` | Merci pour votre participation | Post-sondage |

### 🟡 notifications@ — Alertes système
*Emails déclenchés par des événements système. Plus "humains" que noreply, mais toujours automatiques.*

| Classe | Sujet | Déclencheur |
|---|---|---|
| `AdApprovedMail` | Votre annonce a été approuvée | Modération admin |
| `AdDeclinedMail` | Votre annonce a été refusée | Modération admin |
| `SearchAlertMatchMail` | Nouvelle annonce selon vos critères | Alerte de recherche |
| `SearchAlertDigestMail` | Résumé de vos alertes de recherche | Digest alertes |
| `AbandonedSearchMail` | Vous avez des annonces non consultées | Re-engagement |
| `AppointmentReminderMail` | Rappel de votre rendez-vous | Avant visite |
| `PostViewingFeedbackMail` | Comment s'est passée votre visite ? | Post-visite |
| `SubscriptionExpiringEmail` | Votre abonnement expire bientôt | J-7 expiration |
| `SubscriptionRenewalReminderMail` | Renouvellement de votre abonnement | Avant renouvellement |
| `FailedPaymentRetryMail` | Problème avec votre paiement | Échec paiement |
| `WeeklyDigestMail` | Votre résumé hebdomadaire KeyHome | Digest automatique |
| `InactivityReminderMail` | Vous nous manquez ! | X jours sans connexion |
| `FirstAdCelebrationMail` | Bravo pour votre 1ère annonce ! | Milestone bailleur |
| `FirstAdUnlockCongratulationsMail` | Vous avez débloqué votre 1ère annonce | Milestone client |

### 🟢 marketing@ — Campagnes & lifecycle
*Emails de masse ou séquences marketing. Désabonnement facile obligatoire.*

| Classe | Sujet | Déclencheur |
|---|---|---|
| `NewsletterBroadcastMail` | (sujet de la campagne) | Envoi manuel newsletter |
| `NewsletterConfirmationMail` | Votre abonnement newsletter est confirmé | Inscription newsletter |

### 🔴 support@ — Service client
*Emails impliquant une interaction humaine côté équipe KeyHome.*

| Classe | Sujet | Déclencheur |
|---|---|---|
| `SupportContactMail` | [Contact] {sujet} — {nom} | Formulaire de contact public |
| `AdReportReceivedMail` | Votre signalement a été reçu | Signalement annonce |

### 🟠 bailleurs@ — Onboarding bailleurs & agences
*Canal dédié pour les propriétaires et agences. Donne un sentiment de priorité.*

| Classe | Sujet | Déclencheur |
|---|---|---|
| `BailleurWelcomeEmail` | Bienvenue sur KeyHome - Espace Bailleur | Inscription bailleur |
| `AgencyWelcomeEmail` | Bienvenue sur KeyHome - Espace Agence | Inscription agence |

### ⚫ admin@ — Notifications internes
*Jamais communiqué publiquement. Usage équipe uniquement.*

| Classe | Sujet | Déclencheur |
|---|---|---|
| `AdminWelcomeEmail` | Bienvenue sur le panneau d'administration | Création compte admin |
| `AdminActionNotifyMail` | KeyHome — Alerte : {action} | Action admin notifiée |
| `AdminActionPerformedMail` | KeyHome — Confirmation de votre action | Confirmation action admin |
| `NewAdSubmissionMail` | Nouvelle Annonce : {titre} | Nouvelle annonce à modérer |
| `NewAdReportMail` | Nouveau signalement d'annonce à traiter | Signalement reçu |
| `SurveyAdminNotificationMail` | Nouveau sondage reçu | Réponse sondage |

---

## 3. Boîtes email Zoho Mail (humaines)

### Boîtes actives ✅
| Adresse | Usage | Audience |
|---|---|---|
| `support@keyhome.app` | Assistance utilisateurs, réclamations | Tous utilisateurs |
| `marketing@keyhome.app` | Campagnes, promotions, newsletters | Abonnés |

### À créer maintenant 🔴
| Adresse | Usage | Notes |
|---|---|---|
| `hello@keyhome.app` | Premier contact, partenariats, presse | Alias → support@ |
| `bailleurs@keyhome.app` | Onboarding bailleurs, questions KYC | Alias ou boîte dédiée |
| `security@keyhome.app` | Bug bounty, vulnérabilités | Alias → tech interne |
| `abuse@keyhome.app` | Signalement contenu frauduleux | **Obligatoire légalement** |

### Avant le lancement officiel 🟡
| Adresse | Usage | Notes |
|---|---|---|
| `notifications@keyhome.app` | Alias expéditeur (Resend uniquement) | Pas besoin de boîte humaine |
| `noreply@keyhome.app` | Alias expéditeur (Resend uniquement) | Pas besoin de boîte humaine |
| `facturation@keyhome.app` | Questions paiements, remboursements | Alias → support@ |
| `legal@keyhome.app` | Litiges, RGPD, injonctions | Alias → fondateur |
| `partenaires@keyhome.app` | B2B, agences, intégrateurs | Alias → support@ |
| `contact@keyhome.app` | Formulaire de contact général | Alias → support@ |

### Après le lancement 🟢
| Adresse | Usage | Notes |
|---|---|---|
| `presse@keyhome.app` | Journalistes, interviews | Alias → fondateur |
| `ambassadeurs@keyhome.app` | Influenceurs, programme référence | Alias → marketing@ |
| `recrutement@keyhome.app` | CVs, stages, freelances | Alias → fondateur |
| `admin@keyhome.app` | Cloudflare, GitLab, Sentry alerts | Jamais public |
| `tech@keyhome.app` | Intégrations techniques, webhooks | Alias → dev interne |
| `comptabilite@keyhome.app` | Déclarations fiscales, banques | Alias → fondateur |

### Configuration Zoho (alias)
```
hello@        → support@
contact@      → support@
bailleurs@    → support@ (ou boîte dédiée si volume important)
facturation@  → support@
partenaires@  → support@
abuse@        → support@
security@     → fondateur
legal@        → fondateur
presse@       → fondateur
admin@        → fondateur
```
> ✅ Avantage : une seule interface (support@), réponse possible "depuis" n'importe quelle adresse.

---

## 4. Configuration Resend

### Variables `.env` production
```env
MAIL_MAILER=resend
RESEND_KEY=re_xxxxxxxxxxxxxxxxxxxx

MAIL_FROM_ADDRESS=noreply@keyhome.app
MAIL_FROM_NAME="KeyHome"

MAIL_NOREPLY_ADDRESS=noreply@keyhome.app
MAIL_NOREPLY_NAME="KeyHome"
MAIL_NOTIFICATIONS_ADDRESS=notifications@keyhome.app
MAIL_NOTIFICATIONS_NAME="KeyHome Notifications"
MAIL_MARKETING_ADDRESS=marketing@keyhome.app
MAIL_MARKETING_NAME="KeyHome"
MAIL_SUPPORT_ADDRESS=support@keyhome.app
MAIL_SUPPORT_NAME="KeyHome Support"
MAIL_BAILLEURS_ADDRESS=bailleurs@keyhome.app
MAIL_BAILLEURS_NAME="KeyHome Bailleurs"
MAIL_ADMIN_ADDRESS=admin@keyhome.app
MAIL_ADMIN_NAME="KeyHome Admin"
```

### DNS à configurer sur Resend
> Resend dashboard → Domains → Add domain → `keyhome.app`

Resend génère automatiquement les enregistrements DNS à ajouter chez ton registrar :

| Type | Nom | Valeur |
|---|---|---|
| `TXT` | `resend._domainkey.keyhome.app` | Clé DKIM fournie par Resend |
| `TXT` | `keyhome.app` | `v=spf1 include:_spf.resend.com ~all` |
| `MX` | `bounce.keyhome.app` | `feedback-smtp.us-east-1.amazonses.com` |

> ⚠️ Si tu utilises déjà SPF pour Zoho Mail, combine les deux :  
> `v=spf1 include:zoho.eu include:_spf.resend.com ~all`

---

## 5. Utilisation dans le code

### Ajouter HasSender à un Mailable
```php
use App\Mail\Concerns\HasSender;

class MonMail extends Mailable implements ShouldQueue
{
    use HasSender, Queueable, SerializesModels;

    public function envelope(): Envelope
    {
        return new Envelope(
            from: $this->senderFrom('notifications'), // ou noreply|marketing|support|bailleurs|admin
            subject: '...',
        );
    }
}
```

### Règle de décision rapide
```
L'utilisateur a déclenché l'action lui-même ?
  └─ Oui → noreply@ (confirmation, reçu, OTP, sécurité)

C'est une alerte système comportementale ?
  └─ Oui → notifications@ (annonce approuvée, alerte recherche, rappel visite)

C'est une campagne / newsletter / promo ?
  └─ Oui → marketing@

Un humain de l'équipe support doit pouvoir répondre ?
  └─ Oui → support@

C'est pour un bailleur ou une agence en onboarding ?
  └─ Oui → bailleurs@

C'est pour l'équipe admin/modération interne ?
  └─ Oui → admin@
```

---

## 6. Diagnostic

```bash
# Vérifier la configuration mail
php artisan mail:diagnose

# Test d'envoi (dev)
php artisan mail:diagnose --send-to=ton@email.com
```

---

## 7. Priorités de déploiement

### 🔴 Critique (avant tout envoi en production)
1. Vérifier le domaine `keyhome.app` sur Resend (DKIM + SPF)
2. Ajouter `RESEND_KEY` dans le `.env` production
3. Créer `abuse@keyhome.app` (obligation légale — hébergeur de contenu utilisateur)
4. Créer `security@keyhome.app` (standard industrie tech)

### 🟡 Avant le lancement officiel
5. Créer `hello@keyhome.app` + `bailleurs@keyhome.app` (aliases Zoho)
6. Configurer `facturation@` et `legal@` pour les questions paiements/RGPD
7. Valider les templates email dans les 6 langues (fr, en…)

### 🟢 Après le lancement
8. Créer les aliases `presse@`, `ambassadeurs@`, `recrutement@`
9. Brancher `abuse@` sur un système de ticketing (Freshdesk, Crisp…)
10. Mettre en place un monitoring Resend (webhooks → Slack sur bounces/spam)
