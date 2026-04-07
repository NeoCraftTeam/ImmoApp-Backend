# SEO TODO LIST — KeyHome (keyhome.app)

> Liste complète des correctifs à appliquer, classés par priorité.
> Dérivée de l'audit SEO du 1er mars 2026.
>
> **Légende** : P0 (Critique) · P1 (Important, < 30j) · P2 (Croissance, < 90j) · P3 (Nice-to-have)

## STATUT D'IMPLÉMENTATION (mis à jour le 1er mars 2026)

| # | TODO | Statut | Fichier(s) modifié(s) |
| --- | ---------------------------------------------- | ----------- | ------------------------------------------------------------ |
| 1 | SSR landing page + middleware | Fait | `src/middleware.ts` (créé), `src/app/page.tsx` (refactorisé) |
| 2 | Ads hors auth-gate + JSON-LD RealEstateListing | Fait | `src/app/ads/` (créé, public), `(dashboard)/ads/` (supprimé) |
| 3 | Search publiquement accessible | Fait | `src/app/search/` (déplacé hors de dashboard) |
| 4 | Retirer AggregateRating fictif | Fait | `src/components/seo/JsonLd.tsx` |
| 5 | Créer og-cover.png | Fait | `public/images/og-cover.png` (1200×630 généré) |
| 6 | Sitemap dynamique + villes + blog | Fait | `src/app/sitemap.ts` (async, API ads, villes, blog) |
| 7 | Meta description raccourcie | Fait | `src/app/layout.tsx` (≤155 chars) |
| 8 | Preconnect hints | Fait | `src/app/layout.tsx` (mapbox, clerk) |
| 9 | Google Search Console | ⏳ Manuel | Ajouter `verification.google` dans metadata |
| 10 | Google Analytics 4 (CSP ready) | CSP prêt | `next.config.ts` (GA4 domains ajoutés au CSP) |
| 11 | Core Web Vitals mobile (Three.js) | Fait | `HeroSection.tsx` (mobile fallback, loading placeholder) |
| 12 | Conversion images WebP | Fait | `04Final.webp`, `maison-blanche.webp` (créés) |
| 13 | Lien 404 corrigé | Fait | `src/app/not-found.tsx` (`/home` → `/`) |
| 14 | Pages de ville programmatiques | Fait | `src/app/immobilier/[ville]/page.tsx` (9 villes SSR) |
| 15 | Section blog (scaffold) | Fait | `src/app/blog/` (index, [slug], posts.ts, layout) |
| 16 | Schema RealEstateListing | Fait | `src/app/ads/[id]/[slug]/page.tsx` (JSON-LD SSR) |
| 17 | Maillage interne (footer + chips) | Fait | `LandingFooter.tsx` (villes), `HeroSection.tsx` (city chips) |
| 18 | hreflang anglais | ⏳ Manuel | Nécessite version anglaise du site |
| 19 | Google Business Profiles | ⏳ Manuel | Création manuelle sur Google |
| 20 | Link building / annuaires | ⏳ Manuel | Soumissions manuelles |
| 21 | robots.ts dynamique | Fait | `src/app/robots.ts` (créé), `public/robots.txt` (supprimé) |
| 22 | Web Vitals monitoring | Fait | `src/components/seo/WebVitals.tsx` + intégré dans layout |
| 23 | Pages type de bien | ⏳ Futur | `/appartements`, `/maisons`, etc. |
| 24 | Pages de comparaison | ⏳ Futur | `/comparaison/...` |
| 25 | ItemList schema search | ⏳ Futur | JSON-LD sur /search |
| 26 | HSTS Preload List | ⏳ Manuel | Soumission sur hstspreload.org |
| 27 | City chips links | Fait | `HeroSection.tsx` (`/register` → `/search?city=X`) |

**Résultat : 20/27 TODOs implémentés automatiquement. 7 restants nécessitent une action manuelle ou une phase future.**

---

## P0 — CRITIQUE (À faire cette semaine)

### TODO 1 : Rendre la landing page SSR (Server Component)

> **Fichier** : `src/app/page.tsx`
> **Problème** : `'use client'` rend toute la landing invisible pour Googlebot (HTML vide).
> **Impact** : Le contenu H1, les textes marketing, les CTA — rien n'est dans le HTML initial.

- [ ] Créer `src/middleware.ts` avec Clerk pour rediriger les utilisateurs authentifiés vers `/home` **côté serveur** (avant le rendu)

 ```ts
 // src/middleware.ts
 import { clerkMiddleware, createRouteMatcher } from '@clerk/nextjs/server';
 import { NextResponse } from 'next/server';

 const isPublicRoute = createRouteMatcher([
 '/',
 '/login(.*)',
 '/register(.*)',
 '/search(.*)',
 '/ads(.*)',
 '/immobilier(.*)',
 '/conditions(.*)',
 '/confidentialite(.*)',
 '/blog(.*)',
 ]);

 export default clerkMiddleware(async (auth, req) => {
 const { userId } = await auth();
 // Authenticated users on landing → redirect to /home
 if (userId && req.nextUrl.pathname === '/') {
 return NextResponse.redirect(new URL('/home', req.url));
 }
 // Protected routes → require auth
 if (!isPublicRoute(req)) {
 await auth.protect();
 }
 });

 export const config = {
 matcher: [
 '/((?!_next|[^?]*\\.(?:html?|css|js|jpe?g|png|gif|svg|ico|webp|avif|woff2?|ttf|map|json|txt|xml|robots\\.txt|sitemap\\.xml)).*)',
 ],
 };
 ```

- [ ] Retirer `'use client'` de `src/app/page.tsx`
- [ ] Réécrire `page.tsx` comme Server Component :
 ```tsx
 import LandingPage from '@/components/landing/LandingPage';
 export default function RootPage() {
 return <LandingPage />;
 }
 ```
- [ ] Refactoriser `LandingPage.tsx` et ses sous-composants pour séparer le contenu statique (SSR) des parties interactives (`'use client'`) :
 - `HeroSection` : Extraire le texte H1, la description et les liens comme HTML statique SSR. Garder `ThreeCanvas` et les animations `framer-motion` en `'use client'`
 - `FeaturesSection`, `HowItWorksSection`, `CTASection` : Le texte doit être statique côté serveur
 - `TestimonialsSection` : Peut rester client si interactif (carousel)
 - `LandingThemeContext` + `ThreeCanvas` : Restent `'use client'`
- [ ] Vérifier après déploiement : `curl -s https://keyhome.app | grep "maison idéale"` doit retourner du contenu

---

### TODO 2 : Sortir les pages annonces du layout authentifié

> **Fichier** : `src/app/(dashboard)/ads/[id]/[slug]/page.tsx` + `AdDetailClient.tsx`
> **Problème** : Le layout `(dashboard)` redirige Googlebot vers `/login` → annonces jamais indexées.

- [ ] Déplacer le dossier `ads/` en dehors du groupe `(dashboard)` :
 ```
 src/app/ads/[id]/[slug]/page.tsx ← route publique
 src/app/ads/[id]/[slug]/AdDetailClient.tsx
 ```
- [ ] Créer un layout public pour `/ads` :
 ```tsx
 // src/app/ads/layout.tsx
 import type { Metadata } from 'next';
 export const metadata: Metadata = {
 robots: { index: true, follow: true },
 };
 export default function AdsLayout({
 children,
 }: {
 children: React.ReactNode;
 }) {
 return children; // Pas d'auth check
 }
 ```
- [ ] Modifier `AdDetailClient.tsx` pour un rendu hybride :
 - Afficher publiquement : titre, description, photos, prix, localisation, quartier, surface, attributs
 - Verrouiller derrière auth : coordonnées du propriétaire (téléphone, WhatsApp, email)
 - Afficher un CTA « Inscrivez-vous pour contacter le propriétaire » si non connecté
- [ ] Ajouter du SSR au contenu principal de l'annonce (extraire les données critiques dans le Server Component `page.tsx` et les passer en props au client)
- [ ] Ajouter un schema `RealEstateListing` / `Product` JSON-LD dynamique sur chaque page d'annonce :
 ```tsx
 // Dans page.tsx (server component), après fetch de l'annonce
 const adSchema = {
 '@context': 'https://schema.org',
 '@type': 'RealEstateListing',
 name: ad.title,
 description: ad.description,
 url: `https://keyhome.app/ads/${ad.id}/${ad.slug}`,
 image: ad.images?.map((img) => img.url),
 address: {
 '@type': 'PostalAddress',
 addressLocality: city,
 addressCountry: country,
 },
 offers: { '@type': 'Offer', price: ad.price, priceCurrency: 'XAF' },
 };
 ```
- [ ] Vérifier : `curl -s https://keyhome.app/ads/1/example | grep "<title>"` doit retourner le titre de l'annonce

---

### TODO 3 : Rendre /search publiquement accessible

> **Fichier** : `src/app/(dashboard)/search/page.tsx`
> **Problème** : `/search` est auth-gated → impossible pour Google d'indexer les résultats de recherche.

- [ ] Déplacer `search/` en dehors du groupe `(dashboard)` :
 ```
 src/app/search/page.tsx
 src/app/search/layout.tsx ← garder le metadata existant
 ```
- [ ] Adapter `SearchContent` pour fonctionner sans auth :
 - Les résultats de recherche (liste d'annonces) doivent être visibles publiquement
 - Les fonctionnalités auth-only (favoris, déblocage contact) affichent un CTA « Connectez-vous »
 - La carte Mapbox fonctionne sans auth
- [ ] Ajouter un Navbar public (ou conditionnel) pour les pages publiques
- [ ] Conserver le layout `search/layout.tsx` existant (metadata déjà bien configuré )

---

### TODO 4 : Retirer l'AggregateRating fictif du JSON-LD

> **Fichier** : `src/components/seo/JsonLd.tsx` (lignes 146-153)
> **Problème** : Les valeurs `ratingValue: 4.8`, `reviewCount: 850`, `ratingCount: 1200` sont potentiellement fictives → risque de pénalité manuelle Google.

- [ ] Retirer l'objet `aggregateRating` du `softwareApplicationSchema` :
 ```tsx
 // SUPPRIMER ces lignes (146-153) :
 aggregateRating: {
 '@type': 'AggregateRating',
 ratingValue: '4.8',
 reviewCount: '850',
 ratingCount: '1200',
 bestRating: '5',
 worstRating: '1',
 },
 ```
- [ ] Alternative (si des vrais avis existent) : Connecter les données à l'API backend `/reviews/stats` pour afficher des chiffres réels
- [ ] Vérifier après : Google Rich Results Test → `https://search.google.com/test/rich-results?url=https://keyhome.app`

---

### TODO 5 : Créer l'image OG Cover manquante

> **Fichier manquant** : `public/images/og-cover.png`
> **Problème** : Référencée dans `layout.tsx` (OpenGraph + Twitter), `JsonLd.tsx` (Organization, SoftwareApplication) mais **le fichier n'existe pas** → image cassée sur les réseaux sociaux.

- [ ] Créer `/public/images/og-cover.png` (1200×630px) avec :
 - Le logo KeyHome
 - Le slogan « Immobilier en Afrique sans arnaque »
 - Les couleurs de marque (#F6475F, fond sombre)
 - Format PNG (comme déclaré dans les metadata)
- [ ] Vérifier avec : `https://developers.facebook.com/tools/debug/?q=https://keyhome.app`
- [ ] Vérifier avec : `https://cards-dev.twitter.com/validator`

---

## P1 — IMPORTANT (Dans les 30 jours)

### TODO 6 : Implémenter un sitemap dynamique

> **Fichier** : `src/app/sitemap.ts`
> **Problème** : Seulement 8 URLs statiques. Aucune page d'annonce. Inclut des pages auth-gated.

- [ ] Convertir en fonction `async` pour fetcher les annonces depuis l'API
- [ ] Retirer `/login`, `/register`, `/home`, `/nearby` du sitemap (faible valeur SEO ou auth-gated)
- [ ] Ajouter toutes les pages `/ads/[id]/[slug]` depuis l'API
- [ ] Ajouter les futures pages `/immobilier/[ville]`
- [ ] Code cible :

 ```ts
 import type { MetadataRoute } from 'next';

 const API_URL =
 process.env.NEXT_PUBLIC_API_URL || 'http://localhost:8000/api/v1';

 export default async function sitemap(): Promise<MetadataRoute.Sitemap> {
 const baseUrl = 'https://keyhome.app';
 const now = new Date().toISOString();

 const staticPages: MetadataRoute.Sitemap = [
 {
 url: baseUrl,
 lastModified: now,
 changeFrequency: 'daily',
 priority: 1.0,
 },
 {
 url: `${baseUrl}/search`,
 lastModified: now,
 changeFrequency: 'daily',
 priority: 0.9,
 },
 {
 url: `${baseUrl}/conditions`,
 lastModified: now,
 changeFrequency: 'yearly',
 priority: 0.3,
 },
 {
 url: `${baseUrl}/confidentialite`,
 lastModified: now,
 changeFrequency: 'yearly',
 priority: 0.3,
 },
 ];

 // Fetch all ads
 let adPages: MetadataRoute.Sitemap = [];
 try {
 const res = await fetch(`${API_URL}/ads?per_page=5000&status=available`, {
 next: { revalidate: 3600 },
 });
 if (res.ok) {
 const json = await res.json();
 const ads = json.data ?? [];
 adPages = ads.map(
 (ad: { id: string; slug: string; updated_at: string }) => ({
 url: `${baseUrl}/ads/${ad.id}/${ad.slug}`,
 lastModified: ad.updated_at || now,
 changeFrequency: 'weekly' as const,
 priority: 0.7,
 })
 );
 }
 } catch {
 /* fail silently — static pages are always included */
 }

 return [...staticPages, ...adPages];
 }
 ```

- [ ] Soumettre le nouveau sitemap dans Google Search Console

---

### TODO 7 : Raccourcir la meta description de l'accueil

> **Fichier** : `src/app/layout.tsx` (lignes 24-27)
> **Problème** : La description fait ~270 caractères → tronquée dans les SERP.

- [ ] Raccourcir à ≤ 155 caractères :

 ```
 Avant (270 chars) :
 "Fini les arnaques et les intermédiaires. KeyHome est la plateforme immobilière #1 en Afrique avec des annonces vérifiées manuellement. Trouvez votre maison, appartement, terrain ou villa à Douala, Abidjan, Cotonou, Lomé et dans toute l'Afrique. Inscription gratuite, paiement sécurisé par Mobile Money, contact direct avec les propriétaires."

 Après (~155 chars) :
 "KeyHome : annonces immobilières vérifiées en Afrique. Maisons, appartements, terrains à Douala, Abidjan, Cotonou. Inscription gratuite, 0 arnaque."
 ```

---

### TODO 8 : Ajouter des preconnect hints dans le layout

> **Fichier** : `src/app/layout.tsx`
> **Problème** : Pas de `<link rel="preconnect">` pour les domaines tiers critiques → latence au premier chargement.

- [ ] Ajouter dans le `<head>` de `layout.tsx` :
 ```tsx
 <link rel="preconnect" href="https://api.mapbox.com" />
 <link rel="preconnect" href="https://clerk.neocraft.dev" />
 <link rel="dns-prefetch" href="https://api.mapbox.com" />
 <link rel="dns-prefetch" href="https://clerk.neocraft.dev" />
 ```

---

### TODO 9 : Configurer Google Search Console

> **Problème** : Pas de GSC configuré → aucune visibilité sur l'indexation et les performances.

- [ ] Créer un compte Google Search Console → `https://search.google.com/search-console/`
- [ ] Ajouter la propriété `keyhome.app` (vérification DNS ou balise HTML)
- [ ] Si vérification par balise HTML, ajouter dans `layout.tsx` :
 ```tsx
 export const metadata: Metadata = {
 // ...existing code...
 verification: {
 google: 'VOTRE_CODE_VERIFICATION',
 },
 };
 ```
- [ ] Soumettre le sitemap `https://keyhome.app/sitemap.xml`
- [ ] Demander l'indexation des pages critiques : `/`, `/search`, `/conditions`, `/confidentialite`

---

### TODO 10 : Configurer Google Analytics 4

> **Problème** : Seul Vercel Analytics est présent. Pas de GA4 → données SEO détaillées manquantes.

- [ ] Créer une propriété GA4 → `https://analytics.google.com/`
- [ ] Installer `@next/third-parties` :
 ```bash
 pnpm add @next/third-parties
 ```
- [ ] Ajouter dans `layout.tsx` :
 ```tsx
 import { GoogleAnalytics } from '@next/third-parties/google';
 // Dans le body, à côté de <Analytics /> :
 <GoogleAnalytics gaId="G-XXXXXXXXXX" />;
 ```
- [ ] Mettre à jour le CSP dans `next.config.ts` pour autoriser Google Analytics :
 - Ajouter `https://www.googletagmanager.com` et `https://www.google-analytics.com` dans `script-src` et `connect-src`
- [ ] Configurer les conversions dans GA4 :
 - Événement `unlock_contact` (déblocage coordonnées)
 - Événement `sign_up` (inscription)
 - Événement `search` (recherche effectuée)
- [ ] Lier GSC à GA4 : Admin → Product Links → Search Console Linking

---

### TODO 11 : Optimiser les Core Web Vitals mobile

> **Problème** : LCP, INP et CLS probablement mauvais sur mobile (Three.js, MUI lourd, loading spinner).

- [ ] **Three.js fallback mobile** : Dans `HeroSection.tsx`, ajouter un fallback image statique sur mobile au lieu du canvas 3D :
 ```tsx
 const isMobile = typeof window !== 'undefined' && window.innerWidth < 768;
 // ...
 {
 !isMobile && <ThreeCanvas />;
 }
 {
 isMobile && (
 <div
 style={
 {
 /* gradient CSS fallback */
 }
 }
 />
 );
 }
 ```
- [ ] **Remplacer `CircularProgress` par un skeleton** : Le spinner dans `page.tsx` et `(dashboard)/layout.tsx` qui se transforme en contenu provoque un CLS
- [ ] **Optimiser imports MUI** : Vérifier si les imports barrel sont bien tree-shakés. Sinon, migrer vers :
 ```tsx
 import Button from '@mui/material/Button';
 // au lieu de
 import { Button } from '@mui/material';
 ```
- [ ] **Ajouter `loading` au dynamic import de ThreeCanvas** :
 ```tsx
 const ThreeCanvas = dynamic(() => import('./ThreeCanvas'), {
 ssr: false,
 loading: () => (
 <div
 style={{ position: 'absolute', inset: 0, background: 'transparent' }}
 />
 ),
 });
 ```
- [ ] Tester avec PageSpeed Insights : `https://pagespeed.web.dev/`

---

### TODO 12 : Convertir les images non optimisées

> **Fichiers** : `public/images/04Final.jpg`, `public/images/Maison Blanche.png`
> **Problème** : Formats lourds (JPG/PNG) au lieu de WebP.

- [ ] Convertir `04Final.jpg` → `04Final.webp`
- [ ] Convertir `Maison Blanche.png` → `maison-blanche.webp` (renommer aussi pour URL SEO-friendly, pas d'espaces)
- [ ] Mettre à jour toutes les références dans le code
- [ ] S'assurer que toutes les images ont un attribut `alt` descriptif

---

### TODO 13 : Corriger les liens de la page 404

> **Fichier** : `src/app/not-found.tsx`
> **Problème** : Le bouton « Accueil » pointe vers `/home` (auth-gated) → boucle de redirection pour les utilisateurs non connectés.

- [ ] Changer le lien « Accueil » de `/home` à `/` :
 ```tsx
 <Button component={Link} href="/" ...>
 Accueil
 </Button>
 ```
- [ ] Le lien « Rechercher » pointe vers `/search` → OK après le dé-auth-gating (TODO 3)

---

## P2 — CROISSANCE (Dans les 90 jours)

### TODO 14 : Créer les pages de ville programmatiques

> **Nouvelles routes** : `src/app/immobilier/[ville]/page.tsx`
> **Objectif** : Capturer le trafic « immobilier [ville] », « appartement à louer [ville] ».

- [ ] Créer la structure de route :
 ```
 src/app/immobilier/[ville]/
 page.tsx ← Server Component avec fetch API
 layout.tsx ← metadata dynamique par ville
 ```
- [ ] Implémenter `generateStaticParams()` pour pré-rendre les villes principales :
 ```ts
 export function generateStaticParams() {
 return [
 { ville: 'douala' },
 { ville: 'abidjan' },
 { ville: 'cotonou' },
 { ville: 'lome' },
 { ville: 'yaounde' },
 { ville: 'accra' },
 { ville: 'dakar' },
 { ville: 'bamako' },
 ];
 }
 ```
- [ ] Contenu de chaque page ville :
 - Nombre d'annonces disponibles (depuis l'API)
 - Prix moyen par type de bien
 - Top annonces récentes (cards avec lien vers `/ads/[id]/[slug]`)
 - Carte de la ville (Mapbox)
 - Texte SEO descriptif (guide quartiers, infos pratiques)
 - CTA « Voir toutes les annonces à [Ville] »
- [ ] Metadata dynamique avec `generateMetadata()` :
 ```ts
 title: `Immobilier à ${ville} — Location & Vente | KeyHome`;
 description: `Trouvez votre logement à ${ville}. ${count} annonces vérifiées...`;
 canonical: `https://keyhome.app/immobilier/${ville}`;
 ```
- [ ] Ajouter les pages villes dans le sitemap (TODO 6)

---

### TODO 15 : Créer la section blog

> **Nouvelle route** : `src/app/blog/`
> **Objectif** : Contenu informationnel pour capturer le trafic long-tail.

- [ ] Choisir une stack blog : MDX (fichiers locaux) ou CMS headless (Sanity, Strapi, Contentful)
- [ ] Créer la structure :
 ```
 src/app/blog/
 page.tsx ← Liste des articles
 [slug]/page.tsx ← Article individuel
 layout.tsx ← Metadata + sidebar
 ```
- [ ] Rédiger les 3 premiers articles (cf. calendrier du rapport) :
 1. « Comment éviter les arnaques immobilières au Cameroun : Guide 2026 »
 2. « Prix des loyers à Douala en 2026 : quartier par quartier »
 3. « Location appartement à Abidjan : le guide complet du locataire »
- [ ] Schema `Article` / `BlogPosting` JSON-LD pour chaque article
- [ ] Maillage interne : liens vers `/search`, `/immobilier/[ville]`, `/ads/[id]/[slug]`

---

### TODO 16 : Ajouter le schema RealEstateListing sur les pages d'annonces

> **Fichier** : `src/app/ads/[id]/[slug]/page.tsx` (après déplacement TODO 2)
> **Objectif** : Rich snippets pour les annonces dans les SERP.

- [ ] Dans `generateMetadata()` ou via un composant JSON-LD dédié, ajouter :
 ```json
 {
 "@context": "https://schema.org",
 "@type": "RealEstateListing",
 "name": "Appartement 3 chambres à Douala Bonamoussadi",
 "url": "https://keyhome.app/ads/123/appartement-3-chambres-douala",
 "description": "...",
 "image": ["https://..."],
 "datePosted": "2026-02-15",
 "address": {
 "@type": "PostalAddress",
 "addressLocality": "Douala",
 "addressRegion": "Littoral",
 "addressCountry": "CM"
 },
 "offers": {
 "@type": "Offer",
 "price": "150000",
 "priceCurrency": "XAF",
 "availability": "https://schema.org/InStock"
 }
 }
 ```
- [ ] Valider avec : `https://search.google.com/test/rich-results`

---

### TODO 17 : Implémenter le maillage interne

> **Fichiers multiples** : Landing, Search, Ads, Blog, Villes
> **Objectif** : Distribuer le link juice et faciliter le crawl.

- [ ] **Landing page** : Ajouter des liens vers les 4-6 villes principales dans la section « Populaires » (déjà des chips, mais ils pointent vers `/register` → changer pour `/immobilier/[ville]` ou `/search?city=[ville]`)
- [ ] **Page d'annonce** : Ajouter un lien « Voir toutes les annonces à [Ville] » → `/immobilier/[ville]`
- [ ] **Page de recherche** : Ajouter un sidebar/footer avec liens vers les villes populaires
- [ ] **Blog** : Chaque article doit contenir 3-5 liens internes vers des pages de recherche ou de ville
- [ ] **Footer global** : Ajouter des liens vers les villes principales et les pages de type de bien
- [ ] Implémenter un composant `<Breadcrumb />` visuel (le schema existe déjà) sur toutes les pages

---

### TODO 18 : Ajouter le support hreflang pour l'anglais

> **Fichier** : `src/app/layout.tsx`
> **Problème** : Seul `fr-FR` est déclaré. Le Ghana (Accra, Kumasi) est anglophone.

- [ ] Ajouter `en` dans `alternates.languages` :
 ```tsx
 alternates: {
 canonical: 'https://keyhome.app',
 languages: {
 'fr-FR': 'https://keyhome.app',
 'en': 'https://keyhome.app/en', // quand la version anglaise existera
 },
 },
 ```
- [ ] (Long terme) Implémenter l'internationalisation avec `next-intl` ou le routing i18n natif de Next.js
- [ ] Prioriser la traduction des pages de ville pour Accra/Kumasi

---

### TODO 19 : Créer les profils Google Business

> **Objectif** : Visibilité locale dans Google Maps et le Knowledge Panel.

- [ ] Créer « KeyHome Cameroun » sur Google Business Profile
- [ ] Créer « KeyHome Côte d'Ivoire »
- [ ] Créer « KeyHome Bénin »
- [ ] Créer « KeyHome Togo »
- [ ] Pour chaque profil :
 - Catégorie : « Service immobilier en ligne »
 - Description : Texte de l'Organization schema
 - Logo + photos de l'app
 - Lien : `https://keyhome.app/immobilier/[ville-principale]`
 - Solliciter des avis Google

---

### TODO 20 : Soumettre aux annuaires et lancer le link building

> **Objectif** : Backlinks de qualité pour augmenter l'autorité du domaine.

- [ ] Soumettre KeyHome à :
 - GoAfricaOnline
 - AfricanBizDirectory
 - PagesJaunes Cameroun / CI / Bénin / Togo
 - Product Hunt
 - AlternativeTo (catégorie « Real Estate »)
 - Capterra
- [ ] Préparer 2-3 pitchs pour guest posts sur :
 - TechCabal (PropTech en Afrique)
 - Jeune Afrique Digital
 - CIO Mag Afrique
- [ ] Créer une infographie « Baromètre des loyers en Afrique 2026 » (contenu partageable = backlinks)

---

## P3 — NICE-TO-HAVE (Améliorations continues)

### TODO 21 : Migrer vers un robots.ts dynamique

> **Fichier actuel** : `public/robots.txt` → Migrer vers `src/app/robots.ts`

- [ ] Créer `src/app/robots.ts` :
 ```ts
 import type { MetadataRoute } from 'next';
 export default function robots(): MetadataRoute.Robots {
 return {
 rules: {
 userAgent: '*',
 allow: '/',
 disallow: [
 '/api/',
 '/auth/',
 '/complete-profile',
 '/verify-email',
 '/verify-otp',
 '/reset-password',
 '/forgot-password',
 '/sso-callback',
 '/payment-success',
 '/profile',
 '/payments',
 '/publish',
 ],
 },
 sitemap: 'https://keyhome.app/sitemap.xml',
 };
 }
 ```
- [ ] Supprimer `public/robots.txt`

---

### TODO 22 : Ajouter le monitoring des Core Web Vitals en continu

> **Objectif** : Alertes automatiques si les métriques se dégradent.

- [ ] Installer la lib `web-vitals` :
 ```bash
 pnpm add web-vitals
 ```
- [ ] Créer un composant reporter :
 ```tsx
 // src/components/seo/WebVitals.tsx
 'use client';
 import { useReportWebVitals } from 'next/web-vitals';
 export function WebVitals() {
 useReportWebVitals((metric) => {
 // Envoyer vers GA4 ou endpoint custom
 console.log(metric);
 });
 return null;
 }
 ```
- [ ] Intégrer dans `layout.tsx`

---

### TODO 23 : Créer les pages de type de bien

> **Nouvelles routes** : `/appartements`, `/maisons`, `/terrains`, `/villas`

- [ ] Créer `src/app/appartements/page.tsx` — Hub SSR avec annonces récentes type appartement
- [ ] Créer `src/app/maisons/page.tsx`
- [ ] Créer `src/app/terrains/page.tsx`
- [ ] Créer `src/app/villas/page.tsx`
- [ ] Metadata + JSON-LD + canonical pour chaque page
- [ ] Ajouter au sitemap

---

### TODO 24 : Créer les pages de comparaison

> **Nouvelles routes** : `/comparaison/keyhome-vs-coinafrique`, etc.

- [ ] Créer `src/app/comparaison/[slug]/page.tsx`
- [ ] Rédiger « KeyHome vs CoinAfrique vs Expat-Dakar : quel site choisir ? »
- [ ] Rédiger « Les meilleurs sites immobiliers en Afrique (2026) »

---

### TODO 25 : Ajouter un ItemList schema sur la page de recherche

> **Fichier** : `src/app/search/page.tsx` (après déplacement)
> **Objectif** : Rich snippet pour les résultats de recherche.

- [ ] Générer un JSON-LD `ItemList` avec les annonces affichées :
 ```json
 {
 "@context": "https://schema.org",
 "@type": "ItemList",
 "name": "Résultats de recherche immobilière",
 "numberOfItems": 20,
 "itemListElement": [
 { "@type": "ListItem", "position": 1, "url": "https://keyhome.app/ads/1/..." },
 ...
 ]
 }
 ```

---

### TODO 26 : Soumettre HSTS au Preload List

> **Objectif** : Sécurité maximale + signal de confiance.

- [ ] Vérifier éligibilité : `https://hstspreload.org/?domain=keyhome.app`
- [ ] Soumettre si tous les critères sont remplis (max-age ≥ 1 an, includeSubDomains, preload — déjà en place dans le header)

---

### TODO 27 : Corriger les liens HeroSection (city chips)

> **Fichier** : `src/components/landing/HeroSection.tsx` (ligne 223)
> **Problème** : Les chips de villes (Douala, Garoua, Accra...) pointent tous vers `/register` au lieu de pages de recherche/ville.

- [ ] Changer les liens des chips :

 ```tsx
 // Avant :
 <Link key={city} href="/register">

 // Après :
 <Link key={city} href={`/search?city=${city.toLowerCase()}`}>
 ```

- [ ] Cela améliore à la fois le maillage interne et l'UX

---

## RÉCAPITULATIF

| Priorité | Nombre de TODOs | Effort total estimé |
| -------------------- | --------------- | ------------------------------------- |
| P0 (Critique) | 5 | ~1 semaine dev + 1h design |
| P1 (Important) | 8 | ~1-2 semaines dev + 2h config |
| P2 (Croissance) | 7 | ~3-4 semaines dev + rédaction contenu |
| P3 (Nice-to-have) | 7 | ~2 semaines dev |
| **Total** | **27 TODOs** | **~2-3 mois à 1 dev** |

### Ordre d'exécution recommandé

```
Semaine 1 : TODO 4 (AggregateRating) → TODO 5 (og-cover.png) → TODO 1 (SSR landing)
Semaine 2 : TODO 2 (ads publiques) → TODO 3 (search publique) → TODO 13 (lien 404)
Semaine 3 : TODO 6 (sitemap dynamique) → TODO 7 (meta description) → TODO 8 (preconnect)
Semaine 4 : TODO 9 (GSC) → TODO 10 (GA4) → TODO 11 (Core Web Vitals)
Mois 2 : TODO 14 (pages villes) → TODO 15 (blog) → TODO 17 (maillage)
Mois 3 : TODO 16 (schema annonces) → TODO 18 (hreflang) → TODO 20 (link building)
Continu : TODO 19-27 (profils, annuaires, monitoring, pages type)
```

---

_Généré le 1er mars 2026 — basé sur l'audit SEO KeyHome v1.0_
