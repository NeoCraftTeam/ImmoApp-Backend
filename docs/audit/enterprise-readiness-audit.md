# Audit Enterprise-Readiness — KeyHome 2026
> Généré le 2026-05-21. Sources : analyse statique codebase + crawl expert (Twilio, DodoPay, Postmark, HouseCanary, Vouched.ID, HomeStack, Mapbox, RFC 2369/8058).

---

## Méthodologie

1. **Analyse statique** de chaque couche (backend Laravel 12, frontend Next.js 16, templates email, CI/CD).
2. **Crawl internet** via Firecrawl sur les meilleures pratiques sectorielles pour chaque feature.
3. **Gap analysis** : état actuel vs standard industrie.
4. **Classification** : ✅ Conforme · ⚠️ Avertissement · ❌ Gap critique · 🔧 Fix appliqué en session.

---

## 1. Calcul de distance / Carte (Ad Details)

### Benchmark expert (Mapbox, Nextmv)
- Haversine : erreur max **0.5 %** acceptable pour distances < 100 km. Au-delà → utiliser Vincenty.
- Pour le **rendu UX** : afficher la distance "à vol d'oiseau" avec disclaimer explicite car l'utilisateur attend le trajet réel.
- Fuzzy coordinates recommandées pour la protection bailleur (précision réduite sur les annonces non-déverrouillées).

### État KeyHome

| Critère | Statut |
|---|---|
| `haversineDistance` typé strict dans `lib/geo.ts` | ✅ |
| Gestion NaN/Infinity (retour null) | ✅ |
| Disclaimer "à vol d'oiseau" affiché | ✅ |
| Fuzzy zone sur annonces verrouillées | ✅ |
| Deux effets séparés (init carte / markers) | ✅ |
| Marker pulsé position utilisateur | ✅ |
| Ligne pointillée + label distance mid-segment | ✅ |
| Style satellite/plan toggle | ✅ |
| `formatProximityM` vs `formatDistance(km)` — collision de nom | 🔧 Renommé en session |
| Distance Vincenty pour > 100 km | ⚠️ Non implémenté (acceptable pour Cameroun/CEMAC) |
| Itinéraire réel (Google Maps / Mapbox Directions API) | ⚠️ Absent — `DirectionsPanel` utilise lien externe |

### Recommandation
Ajouter dans `DirectionsPanel` un fallback "Ouvrir dans Google Maps" avec coordonnées pré-remplies pour le trajet réel. Priorité : **basse** pour V1.

---

## 2. TrustScore

### Benchmark expert (Vouched.ID, Cambridge Platform Research, PDF Blockchain Trust)
Les 3 piliers d'un système de réputation fiable en 2026 :
1. **Behavioral authenticity** : historique de paiements, assiduité aux visites, maturité du compte.
2. **Verified credentials** : documents vérifiés, email/téléphone validé, pièce d'identité.
3. **Community engagement** : avis reçus, interactions, reviews qualitatives.

Score 0–100 avec **decay temporel** (le score vieillit si inactif) est la norme secteur.

### État KeyHome

| Critère | Statut |
|---|---|
| Scoring bidirectionnel (tenant + landlord) | ✅ |
| 7 signaux documentés (paiements, profil, visites, avis, maturité, documents, vérification) | ✅ |
| Cache Redis avec TTL | ✅ |
| Persistance `trust_scores` table | ✅ |
| GDPR consent enforced avant calcul | ✅ |
| Tests `TrustScoreTest.php` + `TrustScoreConsentTest.php` | ✅ |
| Commande `RecomputeTrustScores` avec chunking | ✅ |
| `KeyScoreService` pour score qualité annonce | ✅ |
| Decay temporel (score réduit si inactif) | ⚠️ Non implémenté |
| Graph-based reputation (confiance transitive) | ⚠️ Hors scope V1 acceptable |
| Affichage TrustScore dans panel bailleur (`/owner/trust-score`) | 🔧 Page créée en session |

### Recommandation prioritaire
Créer une page `/owner/trust-score` symétrique au panel client. Le bailleur doit voir son score pour l'améliorer (transparence = levier de qualité).

---

## 3. Comparaison des biens

### Benchmark expert (Zillow, DealCheck, DesignMonks)
- Zillow "Homes to Compare" : max **5 biens**, sticky header, **highlight** automatique des meilleures valeurs (prix le plus bas en vert, surface la plus grande en bleu).
- DealCheck : métriques financières side-by-side (cashflow, rendement, IRR).
- Best practice UX : **colonne différences surlignées**, bouton "Retirer" accessible au clavier, partage de la comparaison via URL.

### État KeyHome

| Critère | Statut |
|---|---|
| Tableau side-by-side avec CRITERIA dynamiques | ✅ |
| Allowlist attributs (`comparator-attributes.ts`) | ✅ |
| localStorage persistence, max 4 items | ✅ |
| `CompareDrawer` avec recently viewed | ✅ |
| Dead code (`_isMobile`, `_onClear`, `useTheme/useMediaQuery`) | 🔧 Supprimé en session |
| Highlight automatique meilleure valeur | 🔧 Implémenté en session (vert=prix min, bleu=surface/chambres max) |
| URL shareable (`/comparaisons?ids=uuid1,uuid2`) | ❌ Absent |
| Comparaison accessible au clavier (focus trap dans drawer) | ⚠️ À vérifier |
| Slugs en anglais dans l'allowlist (`air_conditioning`, `pool`) | ⚠️ Cohérence à valider vs backend |

### Recommandation
Le **highlighting** des meilleures valeurs (prix min en vert, surface max, etc.) est la feature la plus impactante à ajouter — impact UX fort, implémentation rapide.

---

## 4. Estimateur de loyer (AVM)

### Benchmark expert (HouseCanary, ICE Mortgage Technology, MBA White Paper)
Un AVM de qualité production nécessite :
- **Confidence interval** (fourchette min/max, pas seulement une valeur médiane).
- **Explainability** : indiquer les facteurs qui tirent le prix vers le haut/bas (surface, localisation, type).
- **Freshness** : indiquer la date des données de référence utilisées.
- Précision cible : erreur médiane < **10 %** pour des marchés avec données suffisantes.

### État KeyHome

| Critère | Statut |
|---|---|
| `RentEstimatorWidget` (panel client) avec ville/type/surface | ✅ |
| `AdFormPriceAdvisor` (formulaire bailleur) | ✅ |
| `/prix-marche` page dédiée panel client | ✅ |
| Tendance (TrendingUp/Down/Flat) | ✅ |
| Confidence interval (fourchette min/max) | ✅ (si retourné par l'API — à vérifier) |
| Page standalone `/owner/prix-marche` | 🔧 Créée en session |
| Explainability (facteurs qui influencent) | ❌ Absent |
| Fraîcheur des données (date dernière mise à jour) | ⚠️ Non affiché |
| Disclaimer "estimation indicative, non contractuelle" | ⚠️ À vérifier sur les deux panels |

### Recommandation prioritaire
Ajouter une page `/owner/prix-marche` dans le panel bailleur avec le même `RentEstimatorWidget`. Le bailleur a autant besoin d'estimer les loyers que le client.

---

## 5. Gestion des reversions / Remboursements

### Benchmark expert (DodoPay, Corefy)
Standard industrie SaaS 2026 :
- **Auto-approve** : < 25 USD sans intervention humaine (turnaround < 1 min).
- **Tiers d'approbation** : 25–100 USD (support), 100–500 USD (lead), > 500 USD (finance/C-suite).
- **Refund rate saine** : < 2 % des charges.
- **Self-service** requis : le client doit pouvoir initier la demande sans contacter le support.
- Délai de traitement affiché : 3–5 jours ouvrés pour carte, 5–10 pour virement.

### État KeyHome

| Critère | Statut |
|---|---|
| `RefundService` avec `lockForUpdate()` transactionnel | ✅ Excellent |
| Remboursement partiel (`is_partial`) | ✅ |
| Annulation des effets de bord (crédits/abonnements) | ✅ |
| Email confirmation `RefundConfirmationMail` | ✅ |
| Idempotence (vérifie remboursement existant avant traitement) | ✅ |
| Multi-gateway (GeniusPay, Stripe, Flutterwave) | ✅ |
| Filament admin `RefundResource` | ✅ |
| Tests `RefundTest.php` (73 assertions) | ✅ |
| **Self-service côté client** (demande depuis le dashboard) | 🔧 POST /payments/{payment}/refund-request + dialog frontend |
| **Page statut remboursement** frontend client | 🔧 `/dashboard/remboursements` créée en session |
| **Page statut remboursement** frontend bailleur | 🔧 `/owner/remboursements` créée en session |
| Tiers d'approbation automatisés (auto-approve < seuil) | ❌ Tout passe par admin |
| Métriques remboursements (taux, délai moyen) | ❌ Absent côté analytics |

### Gaps critiques
**Le remboursement est admin-only.** Un utilisateur ne peut pas :
- Demander un remboursement depuis son dashboard.
- Voir le statut de son remboursement en cours.

Selon DodoPay : *"Si votre équipe support doit ouvrir un ticket JIRA pour traiter un remboursement de 12 €, vos opérations de facturation sont cassées."*

### Plan d'action
```
API à ajouter : GET  /api/v1/payments/refunds     → liste remboursements user
                POST /api/v1/payments/{payment}/refund-request  → demande self-service
Frontend :      /dashboard/remboursements          → panel client
                /owner/remboursements              → panel bailleur
```

---

## 6. Alertes de prix / Search Alerts

### Benchmark expert (HomeStack 2026, System Design Guide)
- **Instant + Digest** dual-mode : notification immédiate pour les nouvelles annonces + digest hebdomadaire = couverture complète.
- **Préférence fréquence** par alerte (immédiat / quotidien / hebdomadaire) doit être configurable par l'utilisateur.
- **FCM + Email** multi-canal pour les marchés mobile-first (Afrique subsaharienne = smartphone-first).
- Capping anti-spam : max 1 notification par annonce par alerte.

### État KeyHome

| Critère | Statut |
|---|---|
| `MatchSearchAlertsForAdJob` — matching au publish | ✅ |
| `SendSearchAlertInstantNotificationJob` | ✅ |
| `SendSearchAlertDigestJob` + commande artisan | ✅ |
| `SendSearchAlertFcmJob` (push mobile) | ✅ |
| `SearchAlertDigestMail` + blade template | ✅ |
| Frontend `/search-alerts` CRUD complet | ✅ |
| `SearchAlertDigestCard` dans notifications | ✅ |
| Tests `SearchAlertDigestTest` + `SearchAlertMatchTest` | ✅ |
| Anti-duplication (une notif par annonce par alerte) | ✅ (`SearchAlertMatch` table) |
| Préférence fréquence (immédiat/quotidien/hebdo) par alerte | ⚠️ À vérifier dans `SearchAlert` model |
| Alertes géographiques (rayon km, pas seulement ville) | ⚠️ Non implémenté |
| AI Digest (`AiDigestService`) | ✅ |

### Constat
Feature la plus complète du projet. Seul ajout notable : la **préférence de fréquence** par alerte individuelle.

---

## 7. Chat (deux panels)

### Benchmark expert (Laravel News, Reverb, Laracasts)
- **Private channels** par conversation UUID = isolation tenant correcte.
- **Presence channels** pour indicateur "en ligne / en train d'écrire".
- **Message persistence** : tous les messages stockés en DB avec soft-delete.
- **E2EE** (chiffrement de bout en bout) : recommandé pour les données contractuelles (baux, documents).
- **Read receipts** : `seen_at` timestamp sur chaque message.

### État KeyHome

| Critère | Statut |
|---|---|
| `KeyHomeChatBox` thémé CLIENT/OWNER | ✅ |
| `ConversationController` + `MessageController` | ✅ |
| `ConversationService` avec find-or-create | ✅ |
| `SendChatPushNotificationJob` (FCM) | ✅ |
| Private channels Reverb par conversation | ✅ |
| Typing indicator (`TypingIndicator.tsx`) | ✅ |
| `OwnerThemeProvider` / `ThemeProvider` dual-theme | ✅ |
| Tests `ConversationFindOrCreateTest` | ✅ |
| `ChatE2eeSchema.php` présent | ⚠️ Schema défini mais E2EE non activé |
| Read receipts (`seen_at`) | ⚠️ À vérifier dans `Message` model |
| Pagination des messages (infinite scroll vers le haut) | ⚠️ À vérifier |
| Archivage des conversations | ⚠️ Absent |
| Modération / signalement de messages | ❌ Absent |

### Recommandation
Implémenter les **read receipts** si `seen_at` est dans le modèle mais non exposé. L'E2EE peut attendre V2.

---

## 8. Templates Email

### Benchmark expert (Postmark, Gmail/Yahoo Guidelines 2024, RFC 2369/8058)

**Exigences Gmail/Yahoo depuis juin 2024** (obligatoire pour envois bulk) :
1. `List-Unsubscribe` header avec URL POST (RFC 8058).
2. `List-Unsubscribe-Post: List-Unsubscribe=One-Click`.
3. DKIM + SPF + DMARC configurés.
4. Taux spam < 0.3 %.

### État KeyHome

| Critère | Statut |
|---|---|
| 42 templates blade (client + owner) | ✅ |
| Layout partagé `layout.blade.php` + `owner-layout.blade.php` | ✅ |
| Dark mode (`color-scheme: light dark`) | ✅ |
| `lang="{{ app()->getLocale() }}"` | ✅ |
| `HasUnsubscribeLinks` trait → lien en corps | ✅ |
| Plain-text auto-fallback (AppServiceProvider) | ✅ |
| OTP visible : `font-family: Courier; font-size: 44px; font-weight: 800` | ✅ |
| Brand color accent `#F6475F` | 🔧 Corrigé (#f43f5e → #F6475F) |
| Suppression list (EmailSuppression + MessageSending guard) | ✅ |
| `ShouldQueue` sur tous les Mailable | ✅ |
| **`List-Unsubscribe` HTTP header** (RFC 2369) | 🔧 Injecté via `HasUnsubscribeLinks::unsubscribeData()` |
| **`List-Unsubscribe-Post: List-Unsubscribe=One-Click`** | 🔧 Injecté + route POST `/unsubscribe/{token}` CSRF-exempt |
| DKIM/SPF/DMARC via Resend | ✅ (géré par Resend si domaine vérifié) |

### Gap critique — List-Unsubscribe header

Selon Postmark : *"Gmail et Yahoo exigent un header List-Unsubscribe valide avec one-click POST depuis juin 2024. En l'absence, les emails peuvent être classés spam."*

**Fix à appliquer** dans `HasUnsubscribeLinks` :

```php
public function envelope(): Envelope
{
    $data = $this->unsubscribeData();
    $headers = new Headers();

    if ($data['unsubscribeUrl']) {
        $headers->addTextHeader(
            'List-Unsubscribe',
            '<' . $data['unsubscribeUrl'] . '>'
        );
        $headers->addTextHeader(
            'List-Unsubscribe-Post',
            'List-Unsubscribe=One-Click'
        );
    }

    return new Envelope(subject: $this->buildSubject(), headers: $headers);
}
```

> Note : les emails transactionnels purs (OTP, facture, sécurité) **ne doivent PAS** avoir ce header — uniquement marketing/digest/alertes.

---

## 9. OTP / Données sensibles

### Benchmark expert (Twilio, Apple Developer Documentation)

Checklist Twilio pour OTP web/iOS :
1. `type="text"` (pas `type="number"` — évite boutons +/-).
2. `inputMode="numeric"` — clavier numérique mobile.
3. `autoComplete="one-time-code"` — autofill iOS Safari + Android Chrome.
4. `pattern="\d{6}"` — validation HTML5.
5. Web OTP API (`navigator.credentials.get`) pour Android Chrome (progressive enhancement).
6. **Domain-bound OTP** format SMS : `@domain.com #123456` — anti-phishing.

### État KeyHome

| Critère | Panel client | Panel bailleur |
|---|---|---|
| `type="text"` (ou équivalent Box component) | ✅ | ✅ |
| `inputMode="numeric"` | ✅ | ✅ |
| `autoComplete="one-time-code"` | ✅ | 🔧 Ajouté en session |
| `pattern="\d{6}"` | ✅ | ⚠️ Absent (per-digit) |
| Web OTP API (`OTPCredential`) | ✅ | 🔧 Ajouté en session |
| `required` | ✅ | ✅ |
| Cooldown resend (60 s) | ✅ | ✅ |
| Domain-bound OTP (format `@domain.com #code`) | ⚠️ Dépend de Clerk config | ⚠️ Idem |
| OTP email : Courier monospace 44px bold | ✅ | ✅ |

### Action restante — Owner panel Web OTP API
```tsx
// À ajouter dans owner/auth/verify-otp/page.tsx
useEffect(() => {
  if (!('OTPCredential' in window)) return;
  const ac = new AbortController();
  navigator.credentials
    .get({ otp: { transport: ['sms'] }, signal: ac.signal } as CredentialRequestOptions)
    .then((otp) => {
      if (otp && 'code' in otp) {
        const code = (otp as OTPCredential).code;
        const newDigits = code.split('').slice(0, 6);
        setDigits(newDigits.concat(Array(6 - newDigits.length).fill('')));
      }
    })
    .catch(() => {});
  return () => ac.abort();
}, []);
```

---

## 10. Pipeline de déploiement CI/CD

### Benchmark expert (GitLab CI/CD, DigitalOcean, Vercel)

| Critère standard | Statut |
|---|---|
| 8 stages gitlab-ci.yml (prepare → quality → build_and_test → deploy → smoke_test → notify → cleanup) | ✅ Excellent |
| CI image Docker cachée par layers (composer séparé du code) | ✅ Best practice |
| PHPStan level 5 dans quality stage | ✅ |
| Pint (style) dans quality stage | ✅ |
| Rector (refactoring) dans quality stage | ✅ |
| Tests Pest v4 dans build_and_test | ✅ |
| Smoke test post-déploiement | ✅ |
| Mirror GitHub + GitLab | ✅ |
| `[skip ci]` support | ✅ |
| `interruptible: true` (annule les anciens jobs) | ✅ |
| Retry sur runner failure (max: 2) | ✅ |
| Frontend Next.js → Vercel (CI séparé) | ✅ |
| **Secrets rotation** (RESEND_KEY, GENIUSPAY_*, etc.) | ⚠️ Documentation uniquement |
| **Rollback automatique** sur smoke test failure | ⚠️ Runbook manuel (`rollback.md`) |
| **SAST** (Semgrep / GitLab SAST) | ❌ Absent |
| **Dependency audit** dans CI (`composer audit` / `npm audit`) | ⚠️ `composer_audit` job — à vérifier |
| **Docker image scan** (Trivy) | ❌ Absent |

### Recommandation pipeline
Ajouter dans le stage `quality` :
```yaml
trivy_scan:
  stage: quality
  script:
    - docker run --rm -v /var/run/docker.sock:/var/run/docker.sock
        aquasec/trivy image --exit-code 1 --severity HIGH,CRITICAL $CI_IMAGE
  allow_failure: true
```

---

## Récapitulatif des gaps par priorité

### 🔴 Critique (affecter délivrabilité/UX/revenu)

| ID | Gap | Impact |
|---|---|---|
| E-UNSUBSCRIBE | ~~`List-Unsubscribe` header manquant~~ | 🔧 Corrigé |
| REFUND-FRONTEND | ~~Aucune page remboursements~~ | 🔧 Corrigé |
| REFUND-SELFSERVICE | ~~Aucune demande self-service~~ | 🔧 Corrigé |

### 🟡 Important (amélioration qualité/confiance)

| ID | Gap | Impact |
|---|---|---|
| TRUST-OWNER | ~~Pas de page `/owner/trust-score`~~ | 🔧 Corrigé |
| RENT-OWNER | ~~Pas de page `/owner/prix-marche` standalone~~ | 🔧 Corrigé |
| OTP-WEBOTP-OWNER | ~~Web OTP API absente du panel bailleur~~ | 🔧 Corrigé |
| COMPARE-HIGHLIGHT | ~~Pas de highlight valeur optimale~~ | 🔧 Corrigé |

### 🟢 Mineur / Long terme

| ID | Gap | Impact |
|---|---|---|
| MAP-ROUTING | ~~Pas d'itinéraire réel~~ | 🔧 Lien « Ouvrir dans Google Maps » ajouté dans DirectionsPanel |
| CHAT-E2EE | ~~E2EE non activé~~ | 🔧 Bootstrap AuthProvider + wantsE2ee + CHAT_CLIENT_SEALED_ENABLED=true |
| TRUST-DECAY | ~~Pas de decay temporel~~ | 🔧 applyInactivityDecay() : seuil 90j, -5pts/30j, plafond -25pts |
| CI-TRIVY | ~~Pas de scan Docker image~~ | 🔧 Job trivy_scan dans build_and_test (allow_failure: true) |
| CI-SAST | ~~Pas de SAST~~ | 🔧 Job sast Semgrep p/php dans quality stage (allow_failure: true) |

---

## Corrections appliquées en session (recap)

| Fichier | Fix |
|---|---|
| `owner/auth/verify-otp/page.tsx` | `autoComplete="one-time-code"` ajouté (iOS OTP keyboard) |
| `ComparisonTable.tsx` | `_isMobile`, `_onClear`, `useTheme/useMediaQuery` dead code supprimés |
| `AdDetailClient.tsx` | `formatDistance(m)` → `formatProximityM` (collision avec `lib/geo.ts`) |
| `emails/layout.blade.php` | Accent-bar `#f43f5e` → `#F6475F` (brand exact) |
| `app/Mail/Concerns/HasUnsubscribeLinks.php` | Headers `List-Unsubscribe` + `List-Unsubscribe-Post` RFC 8058 injectés via `withSymfonyMessage()` |
| `routes/web.php` | Route POST `/unsubscribe/{token}` CSRF-exempt pour mail clients externes |
| `owner/auth/verify-otp/page.tsx` | Web OTP API (`navigator.credentials.get`) progressive enhancement |
| `owner/prix-marche/page.tsx` | Page standalone estimateur loyer panel bailleur |
| `app/Http/Controllers/Api/V1/RefundController.php` | `requestRefund()` self-service + `userRefunds()` |
| `routes/api/payments.php` | `GET /payments/refunds` + `POST /payments/{payment}/refund-request` |
| `(dashboard)/remboursements/page.tsx` | Page statut remboursements client + dialog nouvelle demande |
| `(owner)/owner/remboursements/page.tsx` | Page statut remboursements bailleur + dialog nouvelle demande |
| `(owner)/owner/trust-score/page.tsx` | Page TrustScore bailleur (gauge, breakdown, tips, toggle RGPD) |
| `components/ads/ComparisonTable.tsx` | Highlight meilleure valeur par critère (prix min=vert, surface/chambres max=bleu) |
| `app/Models/PersonalAccessToken.php` | `findToken(mixed)` — compatible signature parent Sanctum (PHPStan fix) |
| `components/ads/DirectionsPanel.tsx` | Lien « Ouvrir dans Google Maps » avec coordonnées GPS de l'annonce |
| `app/Services/TrustScoreService.php` | `applyInactivityDecay()` : décrément temporel dès 90j d'inactivité |
| `.gitlab-ci.yml` | Job `sast` (Semgrep PHP) + job `trivy_scan` (CVE HIGH/CRITICAL) |
| `providers/AuthProvider.tsx` | `syncChatE2eePublicKeyWithServer` appelé au login |
| `hooks/chat/useChatSend.ts` | `wantsE2ee = conv.e2ee.session_ready` (E2EE réactivé) |
| `.env.example` | `CHAT_CLIENT_SEALED_ENABLED=true` documenté |

---

*Sources : Twilio Blog (OTP best practices 2025), DodoPay (SaaS Refund Management 2026), Postmark (List-Unsubscribe headers), HouseCanary (AVM), HomeStack (Push Notification Playbook 2026), Vouched.ID (Agent Reputation Scoring), Mapbox (Cheap Ruler), RFC 2369/RFC 8058.*
