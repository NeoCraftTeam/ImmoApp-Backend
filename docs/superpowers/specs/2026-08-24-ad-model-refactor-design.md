# Spec — Refactor `Ad.php` (vague 1 : slim du god model)

- **Date** : 2026-08-24
- **Statut** : approuvé — approche **C (hybride)**
- **Périmètre** : `app/Models/Ad.php` uniquement.
- **Contraintes dures** : maintenabilité pure — **ZÉRO changement de comportement**, **ZÉRO migration DB**, **API publique préservée** sauf appelants mis à jour simultanément. Tout couvert par tests. Gate `./tests/quality.sh` 6/6 avant commit+push.

## Contexte

`Ad.php` = 977 lignes. ~370 lignes sont de la logique métier qui viole le SRP : boost/ranking/sponsoring, favoris/déverrouillage/gating d'accès, disponibilité, présentation. Les destinations existent **déjà** dans le repo (`AdBoostService`, `AdFeedRankingService`, enum `SponsorshipTier` qui possède `fromFlags()/multiplier()`, `app/Models/Concerns/`, `AdResource`). Le travail est donc une **relocalisation dans les patterns maison**, pas une invention.

## Principes

- **DRY** — réutiliser les services/enums existants ; aucune logique dupliquée (ex. `isCurrentlyAvailable` qui double le scope `currentlyAvailable`).
- **SOLID** — la logique métier quitte le modèle ; `Ad` redevient données + relations + scopes + persistance/recherche.
- **YAGNI** — pas de custom Eloquent Builder / ValueObject / Cast (patterns absents du repo → étrangers). Pas de polymorphie (hors périmètre ; vague ultérieure si un module réel arrive).

## Cible — ce qui bouge

| Bloc actuel (Ad.php) | Destination | Nature |
|---|---|---|
| Scout : `toSearchableArray`, `shouldBeSearchable`, `makeAllSearchableUsing`, `isFurnishedForSearch` (~85 l) | `app/Models/Concerns/AdSearchable.php` *(nouveau)* | déplacement, API stable |
| Audience : `isFavoritedBy`, `loadFavoritedIds`, `isUnlockedFor`, `getAccessibleImages`, `recentViewCount` (~120 l) | `app/Models/Concerns/InteractsWithAudience.php` *(nouveau)* | déplacement, API stable |
| Boost : `boost`, `unboost`, `isBoosted` | `AdBoostService` *(existe)* | logique → service |
| Ranking : `computeRelevanceScore`, `computeRankingScore`, `recordImpression` | `AdFeedRankingService` *(existe)* | logique → service |
| Tier : `sponsorshipTier`, `rankingMultiplier`, `syncSponsorshipTier`, memo `setAttribute` | enum `SponsorshipTier` + accessors fins | math → enum |
| `getPublisherName` | `AdResource` *(existe)* | présentation → resource |
| `isCurrentlyAvailable` / `setAvailability` | dédupliqué avec le scope `currentlyAvailable` | DRY |

## Ce qui RESTE sur `Ad` (cœur du modèle)

Traits (`HasFactory`, `HasUuids`, `SoftDeletes`, `Searchable`, `InteractsWithMedia`, `LogsActivity`, `HasSchedules`, `HasPropertyAttributes`, `HasVisibility` + nouveaux concerns), config (`$fillable/$hidden/$appends/$casts/$appends`), `boot()` (creating/updating + slug), les 12 relations, les scopes `#[Scope]` (`visible`, `publiclyListed`, `currentlyAvailable`, `withAttributes`, `withTour`, `boosted`, `orderByBoost`, `orderBySponsorship`), `registerMediaCollections/Conversions`, `getActivitylogOptions`.

## Décision « délégué fin vs strip » (par méthode)

Arbitrée par la **carte d'appelants** établie avant tout déplacement :

- **Fan-out élevé / usage externe large** → méthode **fine déléguant** au service/enum : API stable, le corps part (gain de lignes), comportement identique.
- **Fan-out faible / interne** → **strip** + appelants basculés vers le service, avec tests mis à jour dans le même commit.

## Filet de sécurité (refactor sous tests)

1. La suite Pest verte (6/6) sert de caractérisation de base.
2. Combler les trous de couverture **avant** de déplacer, par groupe (Scout, audience, boost, ranking, tier, publisher name).
3. Relancer les tests ciblés après **chaque** extraction ; `./tests/quality.sh` 6/6 à la fin.
4. Aucune signature publique cassée sans mise à jour simultanée des appelants + tests.

## Séquence

0. **Cartographier les appelants** (subagents) + inspecter l'API des services/enum/Resource cibles.
1. Tests de caractérisation manquants.
2. Scout → `Concerns/AdSearchable`.
3. Audience → `Concerns/InteractsWithAudience`.
4. Boost → `AdBoostService`.
5. Ranking → `AdFeedRankingService`.
6. Tier → enum `SponsorshipTier` (+ accessors fins).
7. `getPublisherName` → `AdResource` ; dédup disponibilité.
8. `pint --dirty` + gate 6/6 + commit(s)+push.

## Hors périmètre (vagues suivantes)

`User.php`, controllers (Subscription/User/SocialAuth/Payment/Ad…), autres services (Stripe/Payment/Ai…), Filament (`SharedAdResource`…), front (`AdDetailClient.tsx`…). Polymorphie/modules → différé (YAGNI). **README** re-documenté en fin de chantier.
