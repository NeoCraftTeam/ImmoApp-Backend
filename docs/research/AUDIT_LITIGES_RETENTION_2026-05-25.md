# KeyHome — Audit Litiges & Rétention Utilisateur
**Date :** 25 mai 2026 | **Depth :** standard | **Stack :** Laravel 12 + Next.js 15 + PostgreSQL + Redis + FCM + Reverb

---

## Executive Summary

1. **KeyHome a une base `AdReport` (signalement d'annonce) mais aucun système de litige structuré** entre locataire et bailleur — absence critique pour un marché immobilier où 60 % des litiges portent sur dépôt de garantie, réparations, et résiliation.
2. **Le workflow ODR (Online Dispute Resolution) réduit le temps de résolution de mois à 3–7 jours** et augmente la confiance en la plateforme — directement corrélé au trust-score déjà implémenté.
3. **La rétention D30 est le KPI prioritaire** pour KeyHome : les utilisateurs africains ont un coût d'acquisition élevé via mobile money / pub sociale ; un utilisateur retenu × 30 jours vaut 5× un nouvel inscrit.
4. **WhatsApp + FCM push sont les deux canaux de rétention dominants en Afrique subsaharienne** — email seul est insuffisant pour Cameroun/CEMAC.
5. **Les quick wins rétention sont déjà partiellement en place** (`SearchAlert`, `PushSubscription`, `PointTransaction`) mais manquent de logique d'engagement automatisée (streaks, re-engagement D7/D30, campagnes lifecycle).

---

## MODULE 1 — GESTION DES LITIGES

### 1.1 État actuel KeyHome

> **Mise à jour 25 mai 2026 — commit `6f88767`** : module litiges entièrement livré.

| Composant | Présent | Notes |
|-----------|---------|-------|
| `AdReport` (signalement annonce) | ✅ | Status: PENDING → REVIEWING → RESOLVED, admin_notes, resolved_by |
| `Review` (avis locataire sur annonce) | ✅ | owner_response, owner_responded_at |
| `Refund` (remboursement paiement) | ✅ | Lié au Payment |
| `LeaseContract` + signatures | ✅ | PDF DomPDF, signatures Reverb |
| **`Dispute` (litige bailleur-locataire)** | ✅ | `disputes` table, `DisputeStatus` state machine (7 états), `DisputeService`, `DisputePolicy` IDOR-proof |
| Workflow ODR (médiation en ligne) | ✅ | open → under_review → mediation → resolved_*/rejected, transitions admin via Filament |
| Messagerie de litige | ✅ | `dispute_messages` + `POST /disputes/{id}/messages`, `is_internal` admin-only |
| Upload de preuves | ✅ | `dispute_evidences` + `POST /disputes/{id}/evidences`, MIME + 10 MB validés |
| Notifications (database) | ✅ | `DisputeOpenedNotification`, `DisputeMessageReceivedNotification`, `DisputeStatusChangedNotification` |
| SLA stocké (`sla_deadline +7j`) | ✅ | Colonne `sla_deadline` settée à création |
| Historique timeline litige | ✅ | `LogsActivity` Spatie sur `Dispute` (status, admin_id, resolution_note, resolved_at) |
| Panel admin Filament | ✅ | `Annonces → Litiges` avec badge open count + 6 transition actions |
| **Notifications FCM push** | ⚠️ | Database faite; canal FCM via `PushSubscription` à câbler (`via()` retourne `['database']` aujourd'hui) |
| **Job d'escalade SLA dépassé** | ❌ | Pas de scheduled job qui notifie admin quand `sla_deadline < now()` et statut encore `OPEN` |
| **Clause médiation contrat (LeaseContract)** | ❌ | Pas de section opt-in dans le contrat PDF |

### 1.2 Bonnes pratiques expertes trouvées

| Pratique | Source | Priorité |
|----------|--------|----------|
| State machine : `open → under_review → mediation → resolved / rejected` | Spatie Model States v2 | 🔴 HIGH |
| Délai de réponse SLA 3–7 jours (ODR benchmark 2025) | lawsuit.com ODR guide | 🔴 HIGH |
| Parties prenantes : initiateur + défendeur + admin médiateur | ODR best practices | 🔴 HIGH |
| Preuves uploadées (photos, messages, docs) liées au litige | Airbnb, Booking.com pattern | 🔴 HIGH |
| Notification FCM + email à chaque changement de statut | Airship push guide | 🟡 MEDIUM |
| Accord de résolution documenté et signable | ODR workflow 5 étapes | 🟡 MEDIUM |
| Litige lié au LeaseContract ou au Payment | Rental marketplace best practice | 🟡 MEDIUM |
| Données privées : litige hors index Meilisearch, hors log public | OWASP ASVS 5.0 | 🔴 HIGH |
| Clause de médiation obligatoire avant tribunal (opt-in bailleur) | Loi OHADA + ODR guide | 🟢 LOW |

### 1.3 Machine d'états recommandée

```
OPEN (soumis par locataire ou bailleur)
  │
  ▼
UNDER_REVIEW (admin accepte le dossier — SLA 24h)
  │         │
  ▼         ▼
MEDIATION   REJECTED (hors scope, spam, doublon)
  │
  ├──► RESOLVED_FOR_TENANT   (admin tranche en faveur du locataire)
  ├──► RESOLVED_FOR_LANDLORD  (admin tranche en faveur du bailleur)
  └──► RESOLVED_AMICABLY      (accord mutuel documenté)
```

### 1.4 Schéma de base de données recommandé

```sql
CREATE TABLE disputes (
  id             UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  reference      VARCHAR(20) UNIQUE NOT NULL,  -- ex: KH-LITIGE-2026-00042
  type           ENUM('deposit','repair','lease_termination','payment','other'),
  status         ENUM('open','under_review','mediation','resolved_tenant',
                      'resolved_landlord','resolved_amicably','rejected'),
  initiator_id   UUID REFERENCES users(id),
  respondent_id  UUID REFERENCES users(id),
  admin_id       UUID REFERENCES users(id) NULLABLE,
  ad_id          UUID REFERENCES ads(id) NULLABLE,
  lease_id       UUID REFERENCES lease_contracts(id) NULLABLE,
  payment_id     UUID REFERENCES payments(id) NULLABLE,
  title          VARCHAR(255) NOT NULL,
  description    TEXT NOT NULL,
  amount_claimed BIGINT NULLABLE,          -- en FCFA
  resolution_note TEXT NULLABLE,
  resolved_at    TIMESTAMP NULLABLE,
  sla_deadline   TIMESTAMP NOT NULL,       -- auto: created_at + 7 jours
  created_at     TIMESTAMP DEFAULT NOW(),
  updated_at     TIMESTAMP DEFAULT NOW()
);

CREATE TABLE dispute_messages (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  dispute_id  UUID REFERENCES disputes(id) ON DELETE CASCADE,
  sender_id   UUID REFERENCES users(id),
  body        TEXT NOT NULL,
  is_internal BOOLEAN DEFAULT FALSE,  -- messages admin non visibles aux parties
  created_at  TIMESTAMP DEFAULT NOW()
);

CREATE TABLE dispute_evidences (
  id          UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  dispute_id  UUID REFERENCES disputes(id) ON DELETE CASCADE,
  uploader_id UUID REFERENCES users(id),
  type        ENUM('photo','document','screenshot','contract'),
  path        VARCHAR(500) NOT NULL,   -- Cloudflare R2
  created_at  TIMESTAMP DEFAULT NOW()
);
```

### 1.5 API REST recommandée

```
POST   /api/v1/disputes                    — créer un litige
GET    /api/v1/disputes                    — liste (auth user, filtrée par rôle)
GET    /api/v1/disputes/{id}               — détail + messages + preuves
POST   /api/v1/disputes/{id}/messages      — ajouter un message
POST   /api/v1/disputes/{id}/evidences     — upload preuves (multipart)
PATCH  /api/v1/disputes/{id}/status        — admin seulement : changer statut
POST   /api/v1/disputes/{id}/resolve       — admin : résolution + note

Admin Filament panel :
GET  /admin/disputes                       — tableau de bord litiges
GET  /admin/disputes/{id}                  — dossier complet + actions
```

### 1.6 Gap audit sécurité litiges

```
✅ Auth Sanctum + rôle → ok si ajouté
❌ IDOR : vérifier que seul initiateur/défendeur/admin accède au litige
❌ Upload preuves : validation MIME réelle (pas seulement extension)
❌ Rate limit POST /disputes : éviter flood de faux litiges
❌ Données litiges : ne jamais indexer dans Meilisearch
✅ Activitylog Spatie déjà en place → à étendre au modèle Dispute
❌ Notification timing-safe : ne pas révéler l'email de l'autre partie
```

### 1.7 Implémentation Laravel recommandée

```php
// Utiliser Spatie Laravel Model States (déjà utilisable car Spatie est en place)
// composer require spatie/laravel-model-states

class DisputeStatus extends State
{
    public static function config(): StateConfig
    {
        return parent::config()
            ->default(Open::class)
            ->allowTransition(Open::class, UnderReview::class)
            ->allowTransition(Open::class, Rejected::class)
            ->allowTransition(UnderReview::class, Mediation::class)
            ->allowTransition(UnderReview::class, Rejected::class)
            ->allowTransition(Mediation::class, ResolvedTenant::class)
            ->allowTransition(Mediation::class, ResolvedLandlord::class)
            ->allowTransition(Mediation::class, ResolvedAmicably::class);
    }
}
```

---

## MODULE 2 — RÉTENTION UTILISATEUR

### 2.1 État actuel KeyHome

| Composant | Présent | Notes |
|-----------|---------|-------|
| `PushSubscription` (FCM) | ✅ | Web push implémenté |
| `NotificationPreference` | ✅ | Préférences par canal |
| `SearchAlert` + `SearchAlertMatch` | ✅ | Alertes nouvelles annonces |
| `NewsletterSubscriber` + `NewsletterCampaign` | ✅ | Email marketing |
| `PointTransaction` + `point_balance` | ✅ | Crédits gamifiés |
| `TrustScore` | ✅ | Score de confiance |
| **Re-engagement D7/D30 automatisé** | ❌ | Absent |
| **Streaks (connexion/visite quotidienne)** | ❌ | Absent |
| **Badges / achievements** | ❌ | Absent |
| **WhatsApp re-engagement** | ❌ | Absent — critique Afrique |
| **Cohort analytics D1/D7/D30** | ❌ | Absent |
| **Lifecycle emails (onboarding, inactive)** | ❌ | Partiels |
| **"Nouvelles annonces près de chez vous"** | ⚠️ | SearchAlert existe mais non géolocalisé |
| **In-app messaging contextuel** | ❌ | Absent (notifications génériques) |
| **A/B testing engagement** | ❌ | Absent |

### 2.2 Métriques cibles proptech Afrique 2025

| Métrique | Benchmark mondial | Cible KeyHome |
|----------|------------------|---------------|
| D1 retention | 25–35 % | ≥ 30 % |
| D7 retention | 10–15 % | ≥ 12 % |
| D30 retention | 5–8 % | ≥ 7 % |
| Push opt-in | 49 % iOS / 64 % Android | ≥ 55 % |
| Push open rate | 3–10 % | ≥ 6 % |
| Newsletter open | 20–25 % | ≥ 28 % (FR Afrique) |

> **Insight Afrique :** les push notifications hebdomadaires augmentent la rétention D90 de **2× sur iOS et 6× sur Android** (WebEngage 2025). WhatsApp est le canal dominant en Afrique subsaharienne (taux d'ouverture > 90 %).

### 2.3 Plan de rétention recommandé — 4 axes

#### AXE 1 — Onboarding optimisé (impact D1)

**Gap :** pas de flow d'onboarding guidé côté client.

**Actions :**
- Checklist de profil avec barre de progression (déjà présent côté owner, à étendre au client)
- Welcome push immédiat après inscription : « Bienvenue ! 5 annonces correspondent à votre secteur »
- Crédit de bienvenue déjà implémenté (`welcome_bonus_points`) → afficher explicitement à l'onboarding
- Progressive disclosure : ne pas demander tous les filtres dès le début

#### AXE 2 — Re-engagement automatisé (impact D7/D30)

**Gap :** aucun système de détection d'inactivité.

**Actions :**

```php
// Job : ReEngagementJob (schedule: daily)
// Segments :
//   - D7 inactif  → "On a de nouvelles annonces pour vous !"
//   - D14 inactif → crédit bonus (+2 crédits) si retour
//   - D30 inactif → "Vous manquez X annonces dans votre quartier"
//   - D60 inactif → WhatsApp (si numéro vérifié) + email "Nous vous avons gardé votre place"

class ReEngagementNotification extends Notification
{
    use Queueable;

    public function via(User $user): array
    {
        // Priorité canal : push FCM > WhatsApp > email
        return match(true) {
            $user->hasFcmToken() => ['database', 'broadcast'],
            $user->phone !== null => ['vonage'], // WhatsApp Business API
            default               => ['mail'],
        };
    }
}
```

#### AXE 3 — Gamification étendue (impact D7 → D30)

**Gap :** crédits seuls, pas de badges/streaks.

**Actions prioritaires :**

| Badge / Achievement | Déclencheur | Récompense |
|---------------------|-------------|-----------|
| 🏠 Explorateur | 10 annonces consultées | +1 crédit |
| 🔍 Chercheur actif | SearchAlert créée | Badge visible profil |
| ⭐ Voisin de confiance | 3 avis laissés | +2 crédits |
| 📋 Bailleur certifié | 5 annonces publiées + doc vérifiés | Badge profil |
| 🔥 Streak 7 jours | 7 connexions consécutives | +3 crédits |
| 💎 Client VIP | 30 jours actif | Accès fonctions premium |

```php
// Service GamificationService
class GamificationService
{
    public function checkAndAward(User $user, string $event): void
    {
        $badges = BadgeRule::forEvent($event)->get();
        foreach ($badges as $badge) {
            if ($badge->isMet($user) && !$user->hasBadge($badge)) {
                $user->badges()->attach($badge);
                $user->awardPoints($badge->reward_points, "Badge: {$badge->name}");
                event(new BadgeEarned($user, $badge));
            }
        }
    }
}
```

#### AXE 4 — Notifications intelligentes (impact D7 → D30)

**Gap :** SearchAlert existe mais pas de notifications géolocalisées ni personnalisées.

**Actions :**

```php
// Nouveaux types de notification à implémenter
enum NotificationType: string
{
    case NEW_AD_NEAR_YOU          = 'new_ad_near_you';        // rayon 5km GPS
    case PRICE_DROP               = 'price_drop';             // annonce suivie baisse de prix
    case AD_VIEW_COUNT            = 'ad_view_count';          // bailleur: ton annonce a X vues
    case LEASE_EXPIRY_REMINDER    = 'lease_expiry_reminder';  // J-30, J-7 avant fin bail
    case UNREAD_MESSAGES          = 'unread_messages';        // messagerie idle > 2h
    case TRUST_SCORE_IMPROVED     = 'trust_score_improved';   // TrustScore monte
    case POINTS_EXPIRING          = 'points_expiring';        // crédits vont expirer
    case DISPUTE_STATUS_CHANGED   = 'dispute_status_changed'; // litige mis à jour
    case RE_ENGAGEMENT_D7         = 're_engagement_d7';       // inactif 7j
    case RE_ENGAGEMENT_D30        = 're_engagement_d30';      // inactif 30j
}
```

**Règles push (Airship/FCM best practices 2025) :**
- ≤ 2 push/semaine pour les utilisateurs inactifs (éviter opt-out)
- Personnalisation : prénom + ville + type de bien préféré dans le message
- Deep-link direct vers l'annonce concernée (pas juste la home)
- Heure optimale Cameroun : **18h–20h WAT** (retour bureau)

### 2.4 WhatsApp Re-engagement (spécifique Afrique)

**Pourquoi :** taux d'ouverture WhatsApp > 90 % vs 6 % push. En Afrique subsaharienne, WhatsApp est le SMS.

**Stack recommandé :**
- **Vonage Messages API** ou **Infobip** (couverture Cameroun, Orange/MTN)
- Templates pré-approuvés Meta : « Bonjour {prénom}, 3 nouvelles annonces à {ville} vous attendent »
- Opt-in explicite à l'inscription (RGPD CEMAC + règles Meta)
- Limite : 1 message WhatsApp / 7 jours pour inactifs (règles Meta Business)

```php
// config/services.php
'vonage' => [
    'key'    => env('VONAGE_API_KEY'),
    'secret' => env('VONAGE_API_SECRET'),
    'whatsapp_from' => env('VONAGE_WHATSAPP_FROM'), // numéro WhatsApp Business
],

// Notification channel Laravel
class VonageWhatsAppChannel
{
    public function send(User $user, Notification $notification): void
    {
        $message = $notification->toWhatsApp($user);
        Http::post('https://messages.nexmo.com/v1/messages', [
            'from'    => ['type' => 'whatsapp', 'number' => config('services.vonage.whatsapp_from')],
            'to'      => ['type' => 'whatsapp', 'number' => $user->phone],
            'message' => ['content' => ['type' => 'template', 'template' => $message]],
        ]);
    }
}
```

---

## Gap Analysis Matrix

### Litiges

| # | Gap | Sévérité | Effort estimé |
|---|-----|----------|---------------|
| L1 | Modèle `Dispute` absent | 🔴 Critique | 2j |
| L2 | State machine (Spatie) | 🔴 Critique | 4h |
| L3 | Messages de litige | 🔴 Critique | 1j |
| L4 | Upload preuves R2 | 🟡 Moyen | 4h |
| L5 | Panel admin Filament | 🟡 Moyen | 1j |
| L6 | Notifications FCM litige | 🟡 Moyen | 4h |
| L7 | SLA + escalade auto | 🟢 Bas | 4h |
| L8 | Clause médiation contrat | 🟢 Bas | 2h |

### Rétention

| # | Gap | Sévérité | Effort estimé |
|---|-----|----------|---------------|
| R1 | Re-engagement D7/D30 job | 🔴 Critique | 1j |
| R2 | Badges / achievements | 🟡 Moyen | 2j |
| R3 | Streaks quotidiens | 🟡 Moyen | 4h |
| R4 | Push géolocalisé (rayon) | 🟡 Moyen | 4h |
| R5 | WhatsApp Vonage channel | 🟡 Moyen | 1j |
| R6 | Price-drop notification | 🟡 Moyen | 4h |
| R7 | Cohort analytics D1/D7/D30 | 🟢 Bas | 2j |
| R8 | A/B testing push | 🟢 Bas | 3j |
| R9 | Points expiry notification | 🟢 Bas | 2h |

---

## Plan d'Action Prioritaire

| # | Action | Module | Sévérité | Effort | Owner |
|---|--------|--------|----------|--------|-------|
| 1 | Migration + Modèle `Dispute` + State machine Spatie | litiges | 🔴 Critique | 2j | backend |
| 2 | CRUD API `/disputes` + policy IDOR | litiges | 🔴 Critique | 1j | backend |
| 3 | Job `ReEngagementJob` D7/D14/D30 | rétention | 🔴 Critique | 1j | backend |
| 4 | `DisputeMessages` + `DisputeEvidences` + R2 upload | litiges | 🔴 Critique | 1j | backend |
| 5 | Panel Filament litiges (liste + dossier + actions) | litiges | 🟡 Moyen | 1j | backend |
| 6 | Notifications FCM litige (status change, new message) | litiges | 🟡 Moyen | 4h | backend |
| 7 | Badges + `BadgeRule` + `GamificationService` | rétention | 🟡 Moyen | 2j | backend+frontend |
| 8 | Streaks + points quotidiens | rétention | 🟡 Moyen | 4h | backend |
| 9 | Push géolocalisé "nouvelles annonces près de chez vous" | rétention | 🟡 Moyen | 4h | backend |
| 10 | Vonage WhatsApp channel (re-engagement Afrique) | rétention | 🟡 Moyen | 1j | backend |
| 11 | Notification price-drop (annonce suivie) | rétention | 🟡 Moyen | 4h | backend |
| 12 | Frontend litige : formulaire + timeline + chat | litiges | 🟡 Moyen | 2j | frontend |
| 13 | Frontend rétention : badges profil + streak widget | rétention | 🟢 Bas | 1j | frontend |
| 14 | Cohort analytics dashboard | rétention | 🟢 Bas | 2j | data |

---

## Interoperabilité

### Spatie Model States (litiges)
| Dimension | Status | Notes |
|-----------|--------|-------|
| Déjà utilisé | ✅ | Spatie Activitylog en place |
| Compatibilité Laravel 12 | ✅ | v2.x stable |
| `composer require spatie/laravel-model-states` | ✅ | Pas de conflit |

### Vonage (WhatsApp re-engagement)
| Dimension | Status | Notes |
|-----------|--------|-------|
| Couverture Cameroun | ✅ | MTN + Orange CM supportés |
| Laravel Notification Channel | ✅ | Package officiel `laravel/vonage-notification-channel` |
| Prix | ⚠️ | ~0.05$/message WhatsApp — budgéter |
| Opt-in Meta obligatoire | ⚠️ | Templates pré-approuvés Meta |
| Alternative | — | Infobip, Africa's Talking (moins cher, CEMAC natif) |

### FCM (push re-engagement)
| Dimension | Status | Notes |
|-----------|--------|-------|
| Déjà implémenté | ✅ | `PushSubscription` + `NotificationPreference` |
| Deep links | ⚠️ | À vérifier : les push actuels deep-linkent-ils les annonces ? |
| Scheduled push | ❌ | Pas de planification horaire (18h–20h WAT) |

---

## Sources

1. [Online Dispute Resolution for Landlord-Tenant Disputes (2026)](https://lawsuit.com/blogs/professional-mediation-insights/online-dispute-resolution-for-landlord-tenant-dispute/)
2. [Spatie Laravel Model States v2](https://spatie.be/docs/laravel-model-states/v2/01-introduction)
3. [Wezom — User Retention Mobile Apps 2025](https://wezom.com/blog/user-retention-in-mobile-apps-2025-strategies-for-long-term-success)
4. [Airship — 20+ Push Notification Strategies for Customer Retention (2024)](https://www.airship.com/blog/push-notification-strategy-customer-retention/)
5. [LowCode Agency — Rental Marketplace Dispute Resolution Flow (2026)](https://www.lowcode.agency/blog/flutterflow-rental-marketplace-app)
6. [WebEngage — Push Notifications to Deal With App Churn (2025)](https://webengage.com/blog/use-push-notifications-deal-app-churn/)
7. [Braze — WhatsApp Customer Engagement Strategy](https://www.braze.com/resources/articles/customer-engagement-strategy-for-whatsapp-what-works-and-what-doesnt)
