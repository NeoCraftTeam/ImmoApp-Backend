# 📊 Guide d'Implémentation UTM Tracking — KeyHome

> **Version :** 1.0 | **Date :** 22 mars 2026\
> **Auteur :** Antigravity — Senior Software Architect\
> **Public :** Équipe technique KeyHome\
> **Objectif :** Traquer la provenance de chaque utilisateur inscrit pour mesurer le ROI de chaque canal marketing (TikTok, Facebook, Google SEO, LinkedIn, Twitter/X, etc.)

---

## Table des Matières

1. [Principe Général](#1-principe-général)
2. [Architecture du Système](#2-architecture-du-système)
3. [Étape 1 — Migration BDD (champs UTM sur users)](#3-étape-1--migration-bdd)
4. [Étape 2 — Frontend : Capture & Persistance UTM](#4-étape-2--frontend-capture--persistance-utm)
5. [Étape 3 — Backend : Réception UTM à l'inscription](#5-étape-3--backend-réception-utm-à-linscription)
6. [Étape 4 — Attribution automatique (SiteVisit → User)](#6-étape-4--attribution-automatique)
7. [Étape 5 — Ressource Filament Admin](#7-étape-5--ressource-filament-admin)
8. [Étape 6 — Dashboard Widgets](#8-étape-6--dashboard-widgets)
9. [Étape 7 — Génération des Liens UTM](#9-étape-7--génération-des-liens-utm)
10. [Étape 8 — Enrichir SiteVisit (utm_content, utm_term)](#10-étape-8--enrichir-sitevisit)
11. [Tests](#11-tests)
12. [Checklist de Lancement](#12-checklist-de-lancement)

---

## 1. Principe Général

### Le Problème Actuel

```
Visiteur arrive via pub TikTok → SiteVisit(utm_source=tiktok) ✅ OK
Visiteur s'inscrit 5 min après → User créé SANS aucun lien UTM ❌ PERDU
Admin regarde le dashboard → "D'où viennent mes clients payants ?" → 🤷 Impossible à répondre
```

### La Solution

```
Visiteur arrive via pub TikTok
  → Frontend capture utm_source/medium/campaign/content/term dans sessionStorage
  → SiteVisit créé avec session_id ✅

Visiteur s'inscrit
  → Frontend envoie les UTM stockés dans la requête d'inscription
  → Backend sauvegarde sur le User : acquisition_source, utm_source, utm_medium, etc.
  → Backend lie les SiteVisit.session_id au nouveau user_id ✅

Admin ouvre le panel
  → Ressource "Canaux d'acquisition" ✅
  → Widget "Répartition par source" avec graphiques ✅
  → Export CSV pour les campagnes marketing ✅
```

### Les 5 Paramètres UTM Standards

| Paramètre | Rôle | Exemple |
|-----------|------|---------|
| `utm_source` | **D'où** vient le trafic | `tiktok`, `facebook`, `google`, `linkedin`, `newsletter` |
| `utm_medium` | **Comment** (type de canal) | `cpc`, `social`, `organic`, `email`, `referral` |
| `utm_campaign` | **Quelle campagne** | `launch_march_2026`, `ramadan_promo`, `black_friday` |
| `utm_content` | **Quelle variante** (A/B test) | `video_1`, `banner_blue`, `cta_red` |
| `utm_term` | **Quel mot-clé** (SEO payant) | `appartement+douala`, `location+yaounde` |

---

## 2. Architecture du Système

```
┌─────────────────────────────────────────────────────────────────┐
│  CANAUX MARKETING                                                │
│  TikTok · Facebook · Google Ads · LinkedIn · X/Twitter · SEO    │
│  Newsletter · Referral · Direct                                  │
│  ↓ (liens avec paramètres UTM)                                  │
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  NEXT.JS FRONTEND                                                │
│                                                                   │
│  1. UtmCaptureProvider (layout.tsx racine)                       │
│     → Lit les params UTM de l'URL                                │
│     → Stocke dans sessionStorage + cookie (30j)                  │
│     → Envoie POST /api/v1/track/visit                            │
│                                                                   │
│  2. RegisterForm / ClerkExchange / OAuthCallback                 │
│     → Récupère les UTM de sessionStorage                         │
│     → Les envoie avec la requête d'inscription                   │
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  LARAVEL BACKEND                                                 │
│                                                                   │
│  3. AuthController::registerUser()                               │
│     → Reçoit utm_source, utm_medium, etc.                       │
│     → Sauvegarde sur User (6 champs UTM)                        │
│     → Lie les SiteVisits du même session_id                      │
│                                                                   │
│  4. Observer (UserObserver)                                      │
│     → Si User.utm_source est null ET SiteVisit existe            │
│     → Rétro-attribution depuis la première SiteVisit             │
│                                                                   │
│  5. AdminMetricsService                                          │
│     → Métriques acquisition par source                           │
│     → Revenue par canal                                          │
│     → CAC (Coût d'Acquisition Client) par canal                  │
└──────────────────────────┬──────────────────────────────────────┘
                           ▼
┌─────────────────────────────────────────────────────────────────┐
│  FILAMENT ADMIN PANEL                                            │
│                                                                   │
│  6. UserAcquisitionResource                                      │
│     → Table : tous les utilisateurs avec leur source             │
│     → Filtres : par source, medium, campaign, date               │
│     → Export CSV/Excel                                            │
│                                                                   │
│  7. AcquisitionDashboardWidget                                   │
│     → Pie chart : répartition par source                         │
│     → Bar chart : inscriptions par canal / mois                  │
│     → KPI : conversion par canal, revenue par canal              │
└─────────────────────────────────────────────────────────────────┘
```

---

## 3. Étape 1 — Migration BDD

### Fichier : `database/migrations/YYYY_MM_DD_HHMMSS_add_utm_fields_to_users_table.php`

```php
<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            // Canal d'acquisition classifié (ex: paid, organic, social, direct, email, referral)
            $table->string('acquisition_source', 30)->nullable()->after('registration_ip')
                ->comment('Classified channel: paid, organic, social, direct, email, referral');

            // Les 5 paramètres UTM standards
            $table->string('utm_source', 100)->nullable()->after('acquisition_source')
                ->comment('Traffic source: tiktok, facebook, google, linkedin, newsletter...');
            $table->string('utm_medium', 100)->nullable()->after('utm_source')
                ->comment('Marketing medium: cpc, social, organic, email, referral...');
            $table->string('utm_campaign', 255)->nullable()->after('utm_medium')
                ->comment('Campaign name: launch_march_2026, ramadan_promo...');
            $table->string('utm_content', 255)->nullable()->after('utm_campaign')
                ->comment('Ad variant: video_1, banner_blue, cta_red...');
            $table->string('utm_term', 255)->nullable()->after('utm_content')
                ->comment('Search keyword: appartement+douala...');

            // Referrer domain au moment de l'inscription
            $table->string('referrer_domain', 255)->nullable()->after('utm_term')
                ->comment('Referrer domain at registration time');

            // Index pour les requêtes analytics
            $table->index('acquisition_source');
            $table->index(['utm_source', 'created_at']);
            $table->index(['utm_campaign', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table): void {
            $table->dropIndex(['acquisition_source']);
            $table->dropIndex(['utm_source', 'created_at']);
            $table->dropIndex(['utm_campaign', 'created_at']);
            $table->dropColumn([
                'acquisition_source',
                'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
                'referrer_domain',
            ]);
        });
    }
};
```

### Migration SiteVisit — Ajouter `utm_content` et `utm_term`

```php
// database/migrations/YYYY_MM_DD_HHMMSS_add_utm_content_term_to_site_visits_table.php

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('site_visits', function (Blueprint $table): void {
            $table->string('utm_content', 255)->nullable()->after('utm_campaign');
            $table->string('utm_term', 255)->nullable()->after('utm_content');
            $table->index('user_id');
            $table->index(['utm_source', 'visited_at']);
        });
    }

    public function down(): void
    {
        Schema::table('site_visits', function (Blueprint $table): void {
            $table->dropIndex(['user_id']);
            $table->dropIndex(['utm_source', 'visited_at']);
            $table->dropColumn(['utm_content', 'utm_term']);
        });
    }
};
```

---

## 4. Étape 2 — Frontend : Capture & Persistance UTM

### Fichier : `keyhome-frontend-next/src/lib/utm.ts`

```typescript
// ─── UTM Parameter Capture & Persistence ───────────────────────────────────

const UTM_PARAMS = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'] as const;
const UTM_STORAGE_KEY = 'kh_utm_data';
const UTM_SESSION_KEY = 'kh_utm_session';
const UTM_COOKIE_DAYS = 30;

export interface UtmData {
  utm_source?: string;
  utm_medium?: string;
  utm_campaign?: string;
  utm_content?: string;
  utm_term?: string;
  referrer_domain?: string;
  captured_at?: string;
}

/**
 * Extract UTM parameters from the current URL.
 * Called once on every page load.
 */
export function captureUtmFromUrl(): UtmData | null {
  if (typeof window === 'undefined') return null;

  const params = new URLSearchParams(window.location.search);
  const utmData: UtmData = {};
  let hasUtm = false;

  for (const param of UTM_PARAMS) {
    const value = params.get(param);
    if (value) {
      utmData[param] = decodeURIComponent(value).slice(0, 255);
      hasUtm = true;
    }
  }

  if (!hasUtm) return null;

  // Capture referrer domain
  if (document.referrer) {
    try {
      utmData.referrer_domain = new URL(document.referrer).hostname;
    } catch {
      // Invalid referrer URL, ignore
    }
  }

  utmData.captured_at = new Date().toISOString();
  return utmData;
}

/**
 * Persist UTM data to sessionStorage (short term) and cookie (long term).
 * First-touch attribution: only save if not already present.
 */
export function persistUtm(utmData: UtmData): void {
  // SessionStorage: available for the current session (registration)
  const existing = sessionStorage.getItem(UTM_STORAGE_KEY);
  if (!existing) {
    sessionStorage.setItem(UTM_STORAGE_KEY, JSON.stringify(utmData));
  }

  // Cookie: survives browser close for 30 days (first-touch)
  const existingCookie = getCookie(UTM_STORAGE_KEY);
  if (!existingCookie) {
    setCookie(UTM_STORAGE_KEY, JSON.stringify(utmData), UTM_COOKIE_DAYS);
  }
}

/**
 * Retrieve stored UTM data (sessionStorage first, then cookie fallback).
 */
export function getStoredUtm(): UtmData | null {
  if (typeof window === 'undefined') return null;

  // Try sessionStorage first (most recent)
  const sessionData = sessionStorage.getItem(UTM_STORAGE_KEY);
  if (sessionData) {
    try { return JSON.parse(sessionData); } catch { /* ignore */ }
  }

  // Fallback to cookie (first-touch, survives session)
  const cookieData = getCookie(UTM_STORAGE_KEY);
  if (cookieData) {
    try { return JSON.parse(decodeURIComponent(cookieData)); } catch { /* ignore */ }
  }

  return null;
}

/**
 * Clear UTM data after successful registration (to avoid re-attribution).
 */
export function clearUtm(): void {
  sessionStorage.removeItem(UTM_STORAGE_KEY);
  deleteCookie(UTM_STORAGE_KEY);
}

/**
 * Generate or retrieve a unique session ID for visit tracking.
 */
export function getSessionId(): string {
  let sessionId = sessionStorage.getItem(UTM_SESSION_KEY);
  if (!sessionId) {
    sessionId = crypto.randomUUID();
    sessionStorage.setItem(UTM_SESSION_KEY, sessionId);
  }
  return sessionId;
}

// ─── Cookie helpers ─────────────────────────────────────────────────────────

function setCookie(name: string, value: string, days: number): void {
  const expires = new Date(Date.now() + days * 864e5).toUTCString();
  document.cookie = `${name}=${encodeURIComponent(value)};expires=${expires};path=/;SameSite=Lax`;
}

function getCookie(name: string): string | null {
  const match = document.cookie.match(new RegExp(`(?:^|; )${name}=([^;]*)`));
  return match ? match[1] : null;
}

function deleteCookie(name: string): void {
  document.cookie = `${name}=;expires=Thu, 01 Jan 1970 00:00:00 GMT;path=/`;
}
```

### Fichier : `keyhome-frontend-next/src/providers/UtmCaptureProvider.tsx`

```tsx
'use client';

import { useEffect, useRef } from 'react';
import { captureUtmFromUrl, persistUtm, getSessionId, getStoredUtm } from '@/lib/utm';
import { trackingService } from '@/services/tracking.service';

/**
 * Provider that captures UTM parameters on page load and sends visit tracking.
 * Place this in the root layout so it runs on every page.
 */
export default function UtmCaptureProvider({ children }: { children: React.ReactNode }) {
  const tracked = useRef(false);

  useEffect(() => {
    if (tracked.current) return;
    tracked.current = true;

    // 1. Capture UTM from URL if present
    const utmData = captureUtmFromUrl();
    if (utmData) {
      persistUtm(utmData);
    }

    // 2. Send visit tracking to backend
    const storedUtm = getStoredUtm();
    trackingService.trackVisit({
      session_id: getSessionId(),
      utm_source: storedUtm?.utm_source,
      utm_medium: storedUtm?.utm_medium,
      utm_campaign: storedUtm?.utm_campaign,
      utm_content: storedUtm?.utm_content,
      utm_term: storedUtm?.utm_term,
    }).catch(() => { /* silent fail — tracking should never block UX */ });
  }, []);

  return <>{children}</>;
}
```

### Intégration dans le Root Layout

```tsx
// keyhome-frontend-next/src/app/layout.tsx
import UtmCaptureProvider from '@/providers/UtmCaptureProvider';

export default function RootLayout({ children }: { children: React.ReactNode }) {
  return (
    <html lang="fr">
      <body>
        <Providers>
          <UtmCaptureProvider>  {/* ← Ajouter ici */}
            {children}
          </UtmCaptureProvider>
        </Providers>
      </body>
    </html>
  );
}
```

---

## 5. Étape 3 — Backend : Réception UTM à l'inscription

### Modifier `RegisterRequest.php` — Ajouter les champs UTM

```php
// Dans les rules() de RegisterRequest
'utm_source'   => 'nullable|string|max:100',
'utm_medium'   => 'nullable|string|max:100',
'utm_campaign' => 'nullable|string|max:255',
'utm_content'  => 'nullable|string|max:255',
'utm_term'     => 'nullable|string|max:255',
'session_id'   => 'nullable|string|max:64',
```

### Modifier `AuthController::registerUser()` — Sauvegarder les UTM

```php
// Dans la méthode registerUser(), après $user->forceFill([...])
// et AVANT $user->save() :

// ── UTM Attribution ──────────────────────────────────────────────
$utmSource   = $request->input('utm_source');
$utmMedium   = $request->input('utm_medium');
$utmCampaign = $request->input('utm_campaign');
$utmContent  = $request->input('utm_content');
$utmTerm     = $request->input('utm_term');
$sessionId   = $request->input('session_id');

// Si le frontend envoie les UTM → attribution directe
if ($utmSource || $utmMedium) {
    $user->utm_source   = $utmSource;
    $user->utm_medium   = $utmMedium;
    $user->utm_campaign = $utmCampaign;
    $user->utm_content  = $utmContent;
    $user->utm_term     = $utmTerm;
    $user->acquisition_source = $this->classifyAcquisitionSource($utmSource, $utmMedium);
} elseif ($sessionId) {
    // Fallback : retrouver la SiteVisit la plus ancienne de cette session
    $firstVisit = \App\Models\SiteVisit::where('session_id', $sessionId)
        ->orderBy('visited_at')
        ->first();

    if ($firstVisit) {
        $user->utm_source   = $firstVisit->utm_source;
        $user->utm_medium   = $firstVisit->utm_medium;
        $user->utm_campaign = $firstVisit->utm_campaign;
        $user->utm_content  = $firstVisit->utm_content ?? null;
        $user->utm_term     = $firstVisit->utm_term ?? null;
        $user->acquisition_source = $firstVisit->source;
        $user->referrer_domain    = $firstVisit->referrer_domain;
    }
}

// Referrer domain direct
if (!$user->referrer_domain && $request->header('Referer')) {
    $user->referrer_domain = parse_url($request->header('Referer'), PHP_URL_HOST);
}

$user->save();

// Lier toutes les SiteVisits de cette session au nouvel utilisateur
if ($sessionId) {
    \App\Models\SiteVisit::where('session_id', $sessionId)
        ->whereNull('user_id')
        ->update(['user_id' => $user->id]);
}
```

### Méthode helper de classification

```php
// Dans AuthController (ou dans un trait/service)
private function classifyAcquisitionSource(?string $utmSource, ?string $utmMedium): string
{
    if (!$utmSource && !$utmMedium) {
        return 'direct';
    }

    return match (true) {
        in_array($utmMedium, ['cpc', 'ppc', 'paid', 'paid_social', 'display'], true) => 'paid',
        in_array($utmMedium, ['social', 'social-media', 'social_organic'], true) => 'social',
        in_array($utmMedium, ['email', 'newsletter', 'email-automation'], true) => 'email',
        in_array($utmMedium, ['organic', 'seo'], true) => 'organic',
        in_array($utmSource, ['tiktok', 'facebook', 'instagram', 'linkedin', 'twitter', 'x'], true) => 'social',
        in_array($utmSource, ['google', 'bing', 'yahoo'], true) => 'organic',
        in_array($utmSource, ['newsletter', 'mailchimp', 'sendinblue'], true) => 'email',
        default => 'referral',
    };
}
```

### Modifier le Frontend — Envoyer les UTM à l'inscription

```typescript
// Dans le composant RegisterForm ou la fonction register() de AuthProvider :
import { getStoredUtm, getSessionId, clearUtm } from '@/lib/utm';

// Avant d'envoyer la requête d'inscription :
const utmData = getStoredUtm();
const sessionId = getSessionId();

const registrationPayload = {
  firstname, lastname, email, password, confirm_password, phone_number, city_id,
  // UTM data
  ...(utmData && {
    utm_source: utmData.utm_source,
    utm_medium: utmData.utm_medium,
    utm_campaign: utmData.utm_campaign,
    utm_content: utmData.utm_content,
    utm_term: utmData.utm_term,
  }),
  session_id: sessionId,
};

const response = await authService.registerCustomer(registrationPayload);

// Après inscription réussie — nettoyer les UTM
clearUtm();
```

### Même logique pour Clerk Exchange

```php
// Dans AuthController::clerkExchange(), quand un nouvel utilisateur est créé :
// Ajouter la même logique d'attribution UTM
```

---

## 6. Étape 4 — Attribution Automatique

### Fichier : `app/Observers/UserObserver.php` — Rétro-attribution

```php
/**
 * Quand un utilisateur vient de s'inscrire mais n'a pas de source,
 * essayer de retrouver sa première visite via l'IP.
 */
public function created(User $user): void
{
    if ($user->acquisition_source) {
        return; // Déjà attribué
    }

    // Retrouver via IP hash (fallback si session_id non envoyé)
    $ipHash = hash('sha256', $user->registration_ip ?? 'unknown');
    $firstVisit = SiteVisit::where('ip_hash', $ipHash)
        ->where('visited_at', '>=', now()->subHours(24))
        ->orderBy('visited_at')
        ->first();

    if ($firstVisit) {
        $user->forceFill([
            'acquisition_source' => $firstVisit->source ?? 'direct',
            'utm_source'         => $firstVisit->utm_source,
            'utm_medium'         => $firstVisit->utm_medium,
            'utm_campaign'       => $firstVisit->utm_campaign,
            'referrer_domain'    => $firstVisit->referrer_domain,
        ])->saveQuietly();

        // Lier la visite
        $firstVisit->update(['user_id' => $user->id]);
    } else {
        $user->forceFill(['acquisition_source' => 'direct'])->saveQuietly();
    }
}
```

---

## 7. Étape 5 — Ressource Filament Admin

### Fichier : `app/Filament/Admin/Resources/UserAcquisition/UserAcquisitionResource.php`

```php
<?php

declare(strict_types=1);

namespace App\Filament\Admin\Resources\UserAcquisition;

use App\Models\User;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Columns\BadgeColumn;

class UserAcquisitionResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $navigationIcon = 'heroicon-o-signal';
    protected static ?string $navigationLabel = 'Canaux d\'acquisition';
    protected static ?string $navigationGroup = 'Analytics';
    protected static ?int $navigationSort = 1;
    protected static ?string $slug = 'acquisition';

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('firstname')
                    ->label('Utilisateur')
                    ->formatStateUsing(fn (User $record) => $record->firstname . ' ' . $record->lastname)
                    ->searchable(['firstname', 'lastname', 'email']),

                TextColumn::make('email')
                    ->label('Email')
                    ->toggleable(isToggledHiddenByDefault: true),

                BadgeColumn::make('acquisition_source')
                    ->label('Canal')
                    ->colors([
                        'success' => 'organic',
                        'info' => 'social',
                        'warning' => 'paid',
                        'primary' => 'direct',
                        'secondary' => 'email',
                        'gray' => 'referral',
                    ])
                    ->icons([
                        'heroicon-o-magnifying-glass' => 'organic',
                        'heroicon-o-share' => 'social',
                        'heroicon-o-currency-dollar' => 'paid',
                        'heroicon-o-arrow-right-circle' => 'direct',
                        'heroicon-o-envelope' => 'email',
                        'heroicon-o-link' => 'referral',
                    ]),

                TextColumn::make('utm_source')
                    ->label('Source')
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (?string $state) => match ($state) {
                        'tiktok' => 'danger',
                        'facebook', 'instagram' => 'info',
                        'google' => 'success',
                        'linkedin' => 'primary',
                        'twitter', 'x' => 'gray',
                        'newsletter' => 'warning',
                        default => 'gray',
                    }),

                TextColumn::make('utm_medium')
                    ->label('Medium')
                    ->placeholder('—')
                    ->toggleable(),

                TextColumn::make('utm_campaign')
                    ->label('Campagne')
                    ->placeholder('—')
                    ->limit(30)
                    ->tooltip(fn (?string $state) => $state)
                    ->toggleable(),

                TextColumn::make('referrer_domain')
                    ->label('Referrer')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),

                TextColumn::make('role')
                    ->label('Rôle')
                    ->badge(),

                TextColumn::make('created_at')
                    ->label('Inscription')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('acquisition_source')
                    ->label('Canal')
                    ->options([
                        'organic' => '🔍 Organic (SEO)',
                        'social' => '📱 Social',
                        'paid' => '💰 Paid Ads',
                        'direct' => '🔗 Direct',
                        'email' => '📧 Email',
                        'referral' => '🔄 Referral',
                    ]),

                SelectFilter::make('utm_source')
                    ->label('Source')
                    ->options(fn () => User::whereNotNull('utm_source')
                        ->distinct()
                        ->pluck('utm_source', 'utm_source')
                        ->toArray()
                    ),

                SelectFilter::make('utm_campaign')
                    ->label('Campagne')
                    ->options(fn () => User::whereNotNull('utm_campaign')
                        ->distinct()
                        ->pluck('utm_campaign', 'utm_campaign')
                        ->toArray()
                    ),

                Tables\Filters\Filter::make('created_at')
                    ->form([
                        \Filament\Forms\Components\DatePicker::make('from')->label('Du'),
                        \Filament\Forms\Components\DatePicker::make('until')->label('Au'),
                    ])
                    ->query(function ($query, array $data) {
                        return $query
                            ->when($data['from'], fn ($q, $d) => $q->whereDate('created_at', '>=', $d))
                            ->when($data['until'], fn ($q, $d) => $q->whereDate('created_at', '<=', $d));
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\ExportBulkAction::make()
                    ->label('Exporter CSV'),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => \App\Filament\Admin\Resources\UserAcquisition\Pages\ListUserAcquisition::route('/'),
        ];
    }
}
```

---

## 8. Étape 6 — Dashboard Widgets

### Widget : Répartition par Canal

```php
// app/Filament/Admin/Widgets/AcquisitionPieChart.php

<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;

class AcquisitionPieChart extends ChartWidget
{
    protected static ?string $heading = 'Utilisateurs par Canal d\'Acquisition';
    protected static ?int $sort = 2;
    protected int|string|array $columnSpan = 'half';

    protected function getType(): string
    {
        return 'doughnut';
    }

    protected function getData(): array
    {
        $data = User::whereNotNull('acquisition_source')
            ->selectRaw('acquisition_source, COUNT(*) as count')
            ->groupBy('acquisition_source')
            ->pluck('count', 'acquisition_source');

        $labels = [
            'organic' => '🔍 SEO / Organic',
            'social' => '📱 Social Media',
            'paid' => '💰 Publicité Payante',
            'direct' => '🔗 Accès Direct',
            'email' => '📧 Email / Newsletter',
            'referral' => '🔄 Referral',
        ];

        $colors = [
            'organic' => '#22c55e',
            'social' => '#3b82f6',
            'paid' => '#f59e0b',
            'direct' => '#8b5cf6',
            'email' => '#ef4444',
            'referral' => '#6b7280',
        ];

        return [
            'labels' => $data->keys()->map(fn ($k) => $labels[$k] ?? $k)->toArray(),
            'datasets' => [[
                'data' => $data->values()->toArray(),
                'backgroundColor' => $data->keys()->map(fn ($k) => $colors[$k] ?? '#cbd5e1')->toArray(),
            ]],
        ];
    }
}
```

### Widget : Inscriptions par Source / Mois

```php
// app/Filament/Admin/Widgets/AcquisitionTrendChart.php

<?php

declare(strict_types=1);

namespace App\Filament\Admin\Widgets;

use App\Models\User;
use Filament\Widgets\ChartWidget;
use Illuminate\Support\Facades\DB;

class AcquisitionTrendChart extends ChartWidget
{
    protected static ?string $heading = 'Inscriptions par Canal (6 derniers mois)';
    protected static ?int $sort = 3;
    protected int|string|array $columnSpan = 'full';

    protected function getType(): string
    {
        return 'bar';
    }

    protected function getData(): array
    {
        $sources = ['organic', 'social', 'paid', 'direct', 'email', 'referral'];
        $colors = [
            'organic' => '#22c55e', 'social' => '#3b82f6', 'paid' => '#f59e0b',
            'direct' => '#8b5cf6', 'email' => '#ef4444', 'referral' => '#6b7280',
        ];

        $months = [];
        for ($i = 5; $i >= 0; $i--) {
            $months[] = now()->subMonths($i)->format('Y-m');
        }

        $labels = array_map(fn ($m) => \Carbon\Carbon::createFromFormat('Y-m', $m)->translatedFormat('M Y'), $months);

        $datasets = [];
        foreach ($sources as $source) {
            $data = [];
            foreach ($months as $month) {
                $data[] = User::where('acquisition_source', $source)
                    ->whereRaw("TO_CHAR(created_at, 'YYYY-MM') = ?", [$month])
                    ->count();
            }
            $datasets[] = [
                'label' => ucfirst($source),
                'data' => $data,
                'backgroundColor' => $colors[$source],
            ];
        }

        return ['labels' => $labels, 'datasets' => $datasets];
    }
}
```

---

## 9. Étape 7 — Génération des Liens UTM

### Convention de Nommage

| Plateforme | `utm_source` | `utm_medium` | Exemple `utm_campaign` |
|-----------|------------|------------|----------------------|
| **TikTok Ads** | `tiktok` | `paid_social` | `tiktok_march_2026` |
| **TikTok organique** | `tiktok` | `social` | `bio_link` |
| **Facebook Ads** | `facebook` | `paid_social` | `fb_lookalike_douala` |
| **Facebook organique** | `facebook` | `social` | `page_post` |
| **Instagram Ads** | `instagram` | `paid_social` | `ig_story_promo` |
| **Instagram bio** | `instagram` | `social` | `bio_link` |
| **Google Ads (Search)** | `google` | `cpc` | `search_appartement_yaounde` |
| **Google Ads (Display)** | `google` | `display` | `display_campaign_q1` |
| **Google SEO** | *(aucun — détecté automatiquement via referrer)* | — | — |
| **LinkedIn Ads** | `linkedin` | `paid_social` | `li_campaign_pro` |
| **LinkedIn organique** | `linkedin` | `social` | `post_article` |
| **Twitter/X Ads** | `twitter` | `paid_social` | `x_promo_launch` |
| **Twitter/X organique** | `twitter` | `social` | `tweet_link` |
| **Newsletter** | `newsletter` | `email` | `weekly_digest_w12` |
| **Email promo** | `keyhome` | `email` | `promo_ramadan_2026` |
| **Influenceur** | `influencer_[nom]` | `referral` | `collab_[nom]_mars` |
| **QR Code (flyer)** | `qrcode` | `offline` | `flyer_douala_mars` |
| **SMS** | `sms` | `sms` | `sms_promo_paques` |

### Exemples de Liens Complets

```
# TikTok Ads — Campagne mars 2026
https://keyhome.cm/?utm_source=tiktok&utm_medium=paid_social&utm_campaign=tiktok_mars_2026&utm_content=video_appartement_luxe&utm_term=location_douala

# Facebook Ads — Campagne lookalike Douala
https://keyhome.cm/?utm_source=facebook&utm_medium=paid_social&utm_campaign=fb_lookalike_douala&utm_content=carousel_3photos

# Newsletter hebdomadaire
https://keyhome.cm/?utm_source=newsletter&utm_medium=email&utm_campaign=weekly_digest_w12

# Google Ads — Search
https://keyhome.cm/?utm_source=google&utm_medium=cpc&utm_campaign=search_appartement_yaounde&utm_term=appartement+yaounde

# Influenceur Camerounais
https://keyhome.cm/?utm_source=influencer_kamga&utm_medium=referral&utm_campaign=collab_kamga_mars_2026

# QR Code sur flyer
https://keyhome.cm/?utm_source=qrcode&utm_medium=offline&utm_campaign=flyer_douala_mars_2026

# Post LinkedIn organique
https://keyhome.cm/?utm_source=linkedin&utm_medium=social&utm_campaign=post_article_immobilier
```

### Outil de Génération (Artisan Command)

```php
// app/Console/Commands/GenerateUtmLink.php

<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;

class GenerateUtmLink extends Command
{
    protected $signature = 'utm:generate
        {--source= : Traffic source (tiktok, facebook, google...)}
        {--medium= : Medium (cpc, social, email...)}
        {--campaign= : Campaign name}
        {--content= : Ad variant (optional)}
        {--term= : Search keyword (optional)}
        {--path=/ : URL path}';

    protected $description = 'Generate a UTM-tagged URL for marketing campaigns';

    public function handle(): int
    {
        $baseUrl = config('app.frontend_url', config('app.url'));
        $path = $this->option('path');

        $params = array_filter([
            'utm_source' => $this->option('source'),
            'utm_medium' => $this->option('medium'),
            'utm_campaign' => $this->option('campaign'),
            'utm_content' => $this->option('content'),
            'utm_term' => $this->option('term'),
        ]);

        if (empty($params['utm_source'])) {
            $this->error('--source est obligatoire.');
            return 1;
        }

        $url = rtrim($baseUrl, '/') . '/' . ltrim($path, '/') . '?' . http_build_query($params);

        $this->newLine();
        $this->info('🔗 Lien UTM généré :');
        $this->line($url);
        $this->newLine();

        return 0;
    }
}
```

**Usage :**
```bash
php artisan utm:generate --source=tiktok --medium=paid_social --campaign=tiktok_mars_2026 --content=video_luxe
```

---

## 10. Étape 8 — Enrichir SiteVisit

### Mettre à jour le `VisitTrackingController`

```php
// Ajouter dans les rules de validation :
'utm_content'  => 'nullable|string|max:255',
'utm_term'     => 'nullable|string|max:255',

// Ajouter dans SiteVisit::create() :
'utm_content'  => $validated['utm_content'] ?? null,
'utm_term'     => $validated['utm_term'] ?? null,
```

### Mettre à jour le Model `SiteVisit`

```php
protected $fillable = [
    'session_id', 'source', 'referrer_domain',
    'utm_source', 'utm_medium', 'utm_campaign',
    'utm_content', 'utm_term',  // ← ajouter
    'user_id', 'ip_hash', 'device_type', 'visited_at',
];
```

### Mettre à jour le Model `User`

```php
// Ajouter dans $fillable :
'acquisition_source',
'utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term',
'referrer_domain',

// Ajouter dans $hidden :
'utm_content', 'utm_term', // pas besoin de les exposer via l'API
```

---

## 11. Tests

### Test PHPest : Attribution UTM à l'inscription

```php
// tests/Feature/UtmAttributionTest.php

it('captures UTM data during customer registration', function () {
    $response = $this->postJson('/api/v1/auth/registerCustomer', [
        'firstname' => 'Test',
        'lastname' => 'User',
        'email' => 'utm-test@example.com',
        'password' => 'Password123!',
        'confirm_password' => 'Password123!',
        'phone_number' => '699000001',
        'city_id' => City::first()->id,
        'utm_source' => 'tiktok',
        'utm_medium' => 'paid_social',
        'utm_campaign' => 'test_campaign',
        'utm_content' => 'video_1',
    ]);

    $response->assertStatus(201);

    $user = User::where('email', 'utm-test@example.com')->first();
    expect($user->utm_source)->toBe('tiktok');
    expect($user->utm_medium)->toBe('paid_social');
    expect($user->utm_campaign)->toBe('test_campaign');
    expect($user->utm_content)->toBe('video_1');
    expect($user->acquisition_source)->toBe('paid');
});

it('falls back to SiteVisit data when no UTM in registration', function () {
    SiteVisit::create([
        'session_id' => 'test-session-123',
        'source' => 'social',
        'utm_source' => 'facebook',
        'utm_medium' => 'social',
        'utm_campaign' => 'fb_organic',
        'ip_hash' => hash('sha256', '127.0.0.1'),
        'visited_at' => now(),
    ]);

    $response = $this->postJson('/api/v1/auth/registerCustomer', [
        'firstname' => 'Test',
        'lastname' => 'Fallback',
        'email' => 'utm-fallback@example.com',
        'password' => 'Password123!',
        'confirm_password' => 'Password123!',
        'phone_number' => '699000002',
        'city_id' => City::first()->id,
        'session_id' => 'test-session-123',
    ]);

    $response->assertStatus(201);

    $user = User::where('email', 'utm-fallback@example.com')->first();
    expect($user->utm_source)->toBe('facebook');
    expect($user->acquisition_source)->toBe('social');
});

it('classifies acquisition sources correctly', function () {
    $cases = [
        ['cpc', null, 'paid'],
        ['social', null, 'social'],
        ['email', null, 'email'],
        [null, 'tiktok', 'social'],
        [null, 'google', 'organic'],
        [null, 'newsletter', 'email'],
        [null, null, 'direct'],
    ];

    foreach ($cases as [$medium, $source, $expected]) {
        $controller = new \App\Http\Controllers\Api\V1\AuthController();
        $method = new ReflectionMethod($controller, 'classifyAcquisitionSource');
        $method->setAccessible(true);
        expect($method->invoke($controller, $source, $medium))->toBe($expected);
    }
});
```

---

## 12. Checklist de Lancement

```
PRÉ-DÉPLOIEMENT
[ ] Migration users (8 champs UTM)
[ ] Migration site_visits (utm_content, utm_term, indexes)
[ ] Model User : $fillable + $hidden mis à jour
[ ] Model SiteVisit : $fillable mis à jour
[ ] RegisterRequest : rules UTM ajoutées
[ ] AuthController::registerUser() : logique attribution
[ ] AuthController::clerkExchange() : même logique pour Clerk
[ ] SocialAuthController : même logique pour OAuth
[ ] VisitTrackingController : utm_content + utm_term
[ ] UserObserver : rétro-attribution
[ ] Filament UserAcquisitionResource créé
[ ] Filament AcquisitionPieChart widget
[ ] Filament AcquisitionTrendChart widget
[ ] Frontend : lib/utm.ts créé
[ ] Frontend : UtmCaptureProvider créé
[ ] Frontend : RegisterForm envoie les UTM
[ ] Frontend : layout.tsx racine wrappé
[ ] Commande artisan utm:generate

TESTS
[ ] Test attribution UTM directe
[ ] Test fallback SiteVisit
[ ] Test classification des sources
[ ] Test Filament resource visible

POST-DÉPLOIEMENT
[ ] Vérifier les liens UTM sur chaque plateforme
[ ] Créer les premiers liens UTM pour chaque canal
[ ] Configurer les pubs TikTok/Facebook avec les bons UTM
[ ] Monitorer le dashboard Filament après 48h
[ ] Documenter la convention de nommage pour l'équipe marketing
```

---

*Documentation générée par Antigravity — 22 mars 2026*
