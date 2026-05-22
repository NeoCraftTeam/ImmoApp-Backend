# KeyHome — SEO / GEO Audit & Gap Analysis (2026)

> **Scope:** Frontend (`keyhome-frontend-next/`) + Backend API (`/api/v1/`).
> **Research sources:** plantandgrowseo.com, eseospace.com, CREA GEO guide, gracker.ai IndexNow,
> THEHOTH backlinks guide, Botify hreflang guide (all scraped May 2026 via Firecrawl).
> **Goal:** Google + Bing worldwide indexing & ranking — with GEO coverage for AI answer engines.

---

## 1. Baseline Inventory — What Is Already Built

| Layer | Feature | File(s) |
|---|---|---|
| **robots.ts** | Dynamic, correct disallow list for auth/owner routes | `src/app/robots.ts` |
| **sitemap.ts** | Dynamic: static + city + type + comparison + blog + ads (5 000) + agencies | `src/app/sitemap.ts` |
| **Global meta** | Title template, description, keywords, OG, Twitter card, canonical, alternates (`fr-FR` + `x-default`) | `src/app/layout.tsx` |
| **Root JSON-LD** | 7 schemas: WebSite (SearchAction), Organization, RealEstateAgent, SoftwareApplication, FAQPage (10 Q), HowTo, BreadcrumbList | `src/components/seo/JsonLd.tsx` |
| **Ad detail JSON-LD** | RealEstateListing with address, GeoCoordinates, Offer (price/XAF), floorSize, numberOfRooms, AggregateRating (conditional), VideoObject (3D tour) | `src/app/ads/[slug]/page.tsx` |
| **Ad detail meta** | `generateMetadata` → title, description, OG article, Twitter card, canonical, hreflang, geo.position / ICBM | `src/app/ads/[slug]/page.tsx` |
| **City pages** | `generateMetadata` + RealEstateAgent + BreadcrumbList JSON-LD, geo.placename + ICBM, canonical | `src/app/immobilier/[ville]/page.tsx` |
| **Property type pages** | `generateMetadata`, live ad count from API | `src/app/type-bien/[type]/page.tsx` |
| **Core Web Vitals** | LCP, CLS, INP, FCP, TTFB via `web-vitals`, forwarded to GA4 | `src/components/seo/WebVitals.tsx` |
| **Canonical origin** | Priority: `NEXT_PUBLIC_SITE_URL` → `NEXT_PUBLIC_APP_URL` → Vercel fallback | `src/lib/site-url.ts` |
| **Verification tags** | `buildSiteVerification()` for Google / Bing | `src/lib/seo-verification.ts` |
| **canonical_url + whatsapp_share_url** | Exposed in `AdResource` for sharing | `app/Http/Resources/AdResource.php` |

**Foundation score: 7/10 — excellent base, material gaps in schema completeness, IndexNow, GEO/AI, and backlinks.**

---

## 2. Schema Markup Gaps (Structured Data)

### 2.1 Missing `@id` on every entity — CRITICAL

Every JSON-LD entity should carry a stable `@id` URI so Google can build a Knowledge Graph
node for KeyHome and deduplicate it across pages. Currently **zero** schemas have `@id`.

**Fix (root schemas in `JsonLd.tsx`):**

```json
{
  "@context": "https://schema.org",
  "@type": "Organization",
  "@id": "https://keyhome.app/#organization",
  "name": "KeyHome",
  ...
}
```

```json
{
  "@context": "https://schema.org",
  "@type": "WebSite",
  "@id": "https://keyhome.app/#website",
  ...
}
```

Per-ad:
```json
{
  "@type": "RealEstateListing",
  "@id": "https://keyhome.app/ads/{slug}#listing",
  ...
}
```

### 2.2 Missing property sub-type schema

`RealEstateListing` is correct but Google also parses the nested `@type` of the property
for richer classification. Add sub-type based on `ad.type`:

| ad.type | Recommended `@type` pair |
|---|---|
| Appartement / Studio | `["RealEstateListing", "Apartment"]` |
| Maison / Villa | `["RealEstateListing", "SingleFamilyResidence"]` |
| Terrain | `["RealEstateListing", "LandmarksOrHistoricalBuildings"]` — use bare `RealEstateListing` |
| Bureau | `["RealEstateListing", "Accommodation"]` |

**Fix in `ads/[slug]/page.tsx`:**
```typescript
const typeMap: Record<string, string> = {
  Appartement: 'Apartment',
  Studio: 'Apartment',
  Maison: 'SingleFamilyResidence',
  Villa: 'SingleFamilyResidence',
};
schema['@type'] = typeMap[ad.type] ? ['RealEstateListing', typeMap[ad.type]] : 'RealEstateListing';
```

### 2.3 Missing fields on RealEstateListing

| Field | Impact | Source in API |
|---|---|---|
| `numberOfBathroomsTotal` | Rich snippet completeness | `ad.bathrooms` |
| `yearBuilt` | Filtering signal for buyers | `ad.year_built` (if available) |
| `leaseLength` | For rental listings | `ad.lease_duration_months` |
| `propertyType` | `"Rental"` or `"ForSale"` | `ad.transaction_type` |
| `dateModified` | Freshness signal | `ad.updated_at` |
| `validThrough` | Shows availability end-date | optional |
| `@id` | Entity disambiguation | `${BASE_URL}/ads/${slug}#listing` |

**Fix (additive block in `ads/[slug]/page.tsx`):**
```typescript
if (ad.bathrooms) schema.numberOfBathroomsTotal = ad.bathrooms;
if (ad.year_built) schema.yearBuilt = String(ad.year_built);
if (ad.transaction_type) schema.propertyType = ad.transaction_type === 'rent' ? 'Rental' : 'ForSale';
schema.dateModified = ad.updated_at;
schema['@id'] = absoluteUrl(`/ads/${slug}#listing`);
```

### 2.4 Missing schemas on other page types

| Page | Missing schema | Priority |
|---|---|---|
| `blog/[slug]/page.tsx` | `BlogPosting` with `author`, `datePublished`, `dateModified`, `image`, `publisher` | 🔴 HIGH |
| `agences/[id]/page.tsx` | `RealEstateAgent` with `address`, `telephone`, `aggregateRating` | 🟠 HIGH |
| `bailleurs/[username]` + `proprietaires/[id]` | `Person` with `knowsAbout`, `worksFor` | 🟡 MEDIUM |
| `comparaison/[slug]` | `Article` with `author`, `datePublished` | 🟡 MEDIUM |
| `search/page.tsx` | `SearchResultsPage` (harmless, signals crawler intent) | 🟢 LOW |

**Blog fix example (`blog/[slug]/page.tsx`):**
```typescript
const blogSchema = {
  '@context': 'https://schema.org',
  '@type': 'BlogPosting',
  '@id': absoluteUrl(`/blog/${post.slug}#article`),
  headline: post.title,
  description: post.excerpt,
  image: post.coverImage || absoluteUrl('/images/og-cover.png'),
  datePublished: post.publishedAt,
  dateModified: post.updatedAt || post.publishedAt,
  author: {
    '@type': 'Organization',
    '@id': 'https://keyhome.app/#organization',
    name: 'KeyHome',
    url: 'https://keyhome.app',
  },
  publisher: {
    '@type': 'Organization',
    '@id': 'https://keyhome.app/#organization',
    name: 'KeyHome',
    logo: { '@type': 'ImageObject', url: absoluteUrl('/images/logo.png') },
  },
  mainEntityOfPage: absoluteUrl(`/blog/${post.slug}`),
};
```

### 2.5 Organization schema: add Wikidata / Wikipedia sameAs

For Google Knowledge Graph recognition, link to authoritative external URIs:

```json
"sameAs": [
  "https://x.com/Keyhomeapp",
  "https://www.facebook.com/keyhomeapp",
  "https://www.linkedin.com/company/keyhome",
  "https://www.instagram.com/keyhome.app",
  "https://www.crunchbase.com/organization/keyhome"
]
```

Once a Crunchbase entry or Wikipedia page exists, add those. Until then Crunchbase is the
highest-authority stable external entity for a startup.

---

## 3. IndexNow — Not Implemented (CRITICAL for Bing)

IndexNow allows instant notification to Bing, Yandex, and Seznam when URLs are
created/updated/deleted. Google does not officially support IndexNow (they have their own
Indexing API) but Bing is critical for worldwide coverage.

### 3.1 Setup

1. Generate a key at [Bing Webmaster Tools](https://www.bing.com/webmasters)
2. Host the key file:

```
keyhome-frontend-next/public/{KEY}.txt
```

Content of the file: the key string itself.

3. Add the key to `NEXT_PUBLIC_INDEXNOW_KEY` env var.

### 3.2 Backend: ping on ad publish/update/delete

**New service `app/Services/IndexNowService.php`:**
```php
final readonly class IndexNowService
{
    private const ENDPOINT = 'https://api.indexnow.org/indexnow';

    public function ping(string|array $urls): void
    {
        $key = config('services.indexnow.key');
        $host = config('services.indexnow.host'); // keyhome.app

        if (! $key || ! $host) {
            return;
        }

        $urls = (array) $urls;
        $payload = [
            'host'    => $host,
            'key'     => $key,
            'keyLocation' => "https://{$host}/{$key}.txt",
            'urlList' => array_values($urls),
        ];

        Http::post(self::ENDPOINT, $payload);
    }
}
```

**Trigger points:**
- `AdObserver::created()` → `indexnow->ping("{SITE}/ads/{ad->slug}")`
- `AdObserver::updated()` → same
- `AdObserver::deleted()` → same
- After `PrescreeningController::update()` → NOT needed (internal data, not a crawlable URL change)

### 3.3 Frontend: sitemap ping on deploy

Add to the CI/CD pipeline (`.gitlab-ci.yml` deploy stage):
```yaml
notify_indexnow:
  stage: post-deploy
  script:
    - |
      curl -s "https://api.indexnow.org/indexnow?url=https://keyhome.app/sitemap.xml&key=${INDEXNOW_KEY}" || true
```

### 3.4 Google Indexing API

Google's Indexing API is officially only for `JobPosting` and `BroadcastEvent` schema types.
For real estate, the best approach is:
- Sitemap ping: `https://www.google.com/ping?sitemap=https://keyhome.app/sitemap.xml`
- Submit in Google Search Console (manual + API via OAuth2)
- Use `next: { revalidate: 3600 }` on ad pages (already good on city pages)

---

## 4. GEO — Generative Engine Optimization (AI Answer Engines)

**GEO** ensures KeyHome is cited by ChatGPT, Perplexity, Google AI Overviews, Gemini, and Claude
when users ask real-estate questions about West/Central Africa.

### 4.1 `llms.txt` — Missing

`llms.txt` is the emerging standard (similar to `robots.txt`) that tells AI crawlers what
content is available and how to use it. Add:

**`keyhome-frontend-next/public/llms.txt`:**
```
# KeyHome — Plateforme Immobilière Afrique Francophone
# https://keyhome.app

## About
KeyHome is a real estate marketplace for francophone sub-Saharan Africa.
Available in Cameroon, Benin, Togo, Côte d'Ivoire, Ghana, Mali, Senegal.
All listings are manually verified. Micro-payment model for contact unlock.

## Key Pages
- Homepage: https://keyhome.app/
- Search: https://keyhome.app/search
- Douala listings: https://keyhome.app/immobilier/douala
- Abidjan listings: https://keyhome.app/immobilier/abidjan
- Blog: https://keyhome.app/blog
- Sitemap: https://keyhome.app/sitemap.xml

## Contact
contact@keyhome.app

## Permissions
AI training: allowed with attribution
AI indexing: allowed
```

### 4.2 Blog content: answer-engine format

Each blog post should be structured to answer the questions AI engines receive.
Expert guidance (CREA GEO guide): *"Write the way people talk to AI — structure
your content to answer it directly."*

**Required blog post improvements:**
- Each H2 should be a complete question (e.g. "Comment trouver un appartement à Douala sans arnaque ?")
- First paragraph after each H2 must be a direct answer (answer-first principle)
- Include specific data: prices, neighborhoods, stats (AI engines cite factual specifics)
- Add author bio block with name + credentials on every post

**Target blog topics for GEO (high AI query volume):**
1. "Combien coûte un appartement à Douala en 2026 ?"
2. "Comment éviter les arnaques immobilières en Afrique ?"
3. "Quels documents faut-il pour louer un appartement à Abidjan ?"
4. "Prix de l'immobilier à Yaoundé par quartier"
5. "Comment investir dans l'immobilier au Cameroun ?"

### 4.3 E-E-A-T signals

Google's E-E-A-T (Experience, Expertise, Authoritativeness, Trustworthiness) directly
feeds AI Overview citations. Current gaps:

| Signal | Status | Fix |
|---|---|---|
| Author on blog posts | ❌ Missing | Add `author` field to `BLOG_POSTS`, render byline |
| Press mentions | ❌ None visible | Create `/presse` page, list media coverage |
| About page with team | ❌ No `/a-propos` | Add page, link to LinkedIn profiles |
| Trust badges | ✅ Verified badge on ads | Already done |
| Organization sameAs | ⚠️ Partial | Add Crunchbase once entry created |

---

## 5. International SEO & hreflang

### 5.1 Current state

Only `fr-FR` and `x-default` are declared. This is **correct** for a French-only platform
but misses opportunities for country-level targeting.

### 5.2 Country-specific hreflang

For city pages, add the country-specific `hreflang` variant:

```typescript
// In immobilier/[ville]/page.tsx generateMetadata:
const hreflangCountryCode: Record<string, string> = {
  douala: 'fr-CM', yaounde: 'fr-CM', bafoussam: 'fr-CM',
  abidjan: 'fr-CI',
  cotonou: 'fr-BJ',
  lome: 'fr-TG',
  accra: 'en-GH',   // Accra needs English content
  dakar: 'fr-SN',
  bamako: 'fr-ML',
};

alternates: {
  canonical: absoluteUrl(path),
  languages: {
    'fr-FR': absoluteUrl(path),
    [hreflangCountryCode[ville] || 'fr-FR']: absoluteUrl(path),
    'x-default': absoluteUrl(path),
  },
}
```

### 5.3 Country landing pages (programmatic SEO gap)

Currently there are only city pages (`/immobilier/douala`). Add country pages:

- `/immobilier/cameroun` — aggregates all Cameroon cities, 0.85 priority
- `/immobilier/cote-divoire`
- `/immobilier/benin`
- `/immobilier/togo`
- `/immobilier/senegal`

These pages intercept queries like "immobilier Cameroun" (high volume) vs just city queries.

### 5.4 English content for Ghana

`/immobilier/accra` currently renders French content. Accra users search in English.
Options:
- Add bilingual `fr`/`en` toggle on Accra/Kumasi pages
- OR create `/real-estate/accra` in English (better for SEO) with proper hreflang

---

## 6. Sitemap Gaps

### 6.1 Missing URL sets

| Missing | Fix |
|---|---|
| `/type-bien/appartement` etc. | Already in `sitemap.ts` as `typePages` ✅ — but `type-bien/[type]` routes exist and are in sitemap. **No gap.** |
| `bailleurs/[username]` pages | Add API fetch to sitemap (like agencies) |
| `proprietaires/[id]` pages | Add API fetch (same) |
| Country pages (once created) | Add static entries |
| Blog posts missing `image` | Add `images` extension per post |

**Add to `sitemap.ts`:**
```typescript
// Landlord public profiles
let landlordPages: MetadataRoute.Sitemap = [];
try {
  const res = await fetch(`${API_URL}/users?role=agent&per_page=500&public=true`, {
    next: { revalidate: 3600 },
  });
  if (res.ok) {
    const json = await res.json();
    landlordPages = (json.data ?? []).map((u: { username: string; updated_at?: string }) => ({
      url: `${baseUrl}/bailleurs/${u.username}`,
      lastModified: u.updated_at || now,
      changeFrequency: 'weekly' as const,
      priority: 0.55,
    }));
  }
} catch { /* fail silently */ }
```

### 6.2 Sitemap index (required at scale)

Google recommends splitting sitemaps when total URLs > 10 000. With 5 000 ads alone plus all
other pages, a single `sitemap.xml` will reach the 50 000 URL hard limit quickly.

**Fix: Create a `sitemap-index` architecture via Next.js route handlers:**

```
/sitemap.xml          → index pointing to:
/sitemap/static.xml   → static pages
/sitemap/cities.xml   → /immobilier/* pages
/sitemap/ads.xml      → /ads/* (paginated, 1 000 per file)
/sitemap/agencies.xml → /agences/*
/sitemap/blog.xml     → /blog/*
```

In `robots.ts`, list all sitemap files or just the index.

### 6.3 `changeFrequency` and `priority` tuning

| URL set | Current | Recommended |
|---|---|---|
| `/blog/*` | `monthly` / `0.6` | `weekly` / `0.75` (link magnet content) |
| `/ads/*` | `weekly` / `0.7` | `daily` / `0.8` (inventory changes fast) |
| `/comparaison/*` | `monthly` / `0.65` | `monthly` / `0.7` |
| `/bailleurs/*` | — | `weekly` / `0.55` |

### 6.4 `robots.ts` fix: disallow paginated search params

```typescript
disallow: [
  ...existingList,
  '/search?*',         // canonicalize all search param variants
  '/home?*',
],
```

But ensure `/search` (no params) stays crawlable — it already is.

---

## 7. Backlink Strategy

### 7.1 Priority link targets (Africa + French web)

| Source type | Target sites | Tactic |
|---|---|---|
| **African tech media** | TechCabal, AfricanBusinessCentral, Le360 Afrique | Press release on launch + data reports |
| **Real estate portals** | CIFrance (CI), ImmoClick (CM), Jumia House (if still active) | Broken-link replacement or partnership |
| **Government / NGO** | Min. Habitat Cameroun, APIX Sénégal | Free listing partnership, data sharing MOU |
| **Banks** | Afriland First Bank, BICEC, UBA Cameroun | "Partenaire immobilier" badge exchange |
| **Notaires / avocats** | Chambre des Notaires du Cameroun | Guest posts on legal aspects of buying |
| **Universities** | ESSEC Douala, IAI Cameroun | Student housing guide, sponsoring research |

### 7.2 Link magnet content to create

1. **Indice des loyers KeyHome** — monthly rent index by city/quartier (unique data, highly citable)
2. **Rapport immobilier annuel** — downloadable PDF with market stats
3. **Calculateur crédit immobilier** — free tool for Cameroun (CFA francs)
4. **Guide de l'acheteur immobilier au Cameroun** — pillar content, 5 000+ words
5. **Prix médian par quartier** (already have `/prix-marche` — expose it publicly without auth)

### 7.3 Digital PR / HARO equivalent

- Subscribe to [HARO](https://www.helpareporter.com/) (English) and [JournauxAfrique](https://www.journaux.fr/) for reporter queries
- Track queries about "immobilier Afrique" — respond as KeyHome expert
- Issue press releases on Agence Ecofin, Business in Cameroon when hitting milestones (10k ads, 50k users etc.)

### 7.4 Link profile health

- Register on all major African business directories (Google My Business, Yelp Africa, Yellow Pages Africa)
- Ensure NAP consistency: **KeyHome** / **keyhome.app** / **contact@keyhome.app** across all citations

---

## 8. API / Interoperability Gaps

### 8.1 Google Search Console API integration (backend)

Add a Laravel command to submit the sitemap to GSC on deploy:

```bash
# After deploy
curl "https://www.google.com/ping?sitemap=https://keyhome.app/sitemap.xml"
```

Or programmatically via `app/Console/Commands/PingSitemaps.php` using Google's
[Indexing API](https://developers.google.com/search/apis/indexing-api/v3/quickstart).

### 8.2 Bing Webmaster Tools

- Verify keyhome.app in [Bing Webmaster Tools](https://www.bing.com/webmasters)
- Set up IndexNow (Section 3)
- Submit sitemap manually + auto-ping on deploy

### 8.3 Backend `AdResource` SEO fields

Already exposed: `canonical_url`, `whatsapp_share_url`, `slug`.

**Add:**
```php
'seo_title'       => $this->generateSeoTitle(),   // "{type} à {quartier}, {ville} — {price} FCFA"
'seo_description' => $this->generateSeoDesc(),    // First 160 chars of description
'structured_data_url' => absoluteUrl("/ads/{$this->slug}"),
```

These allow the mobile apps (React Native) to also surface good SEO titles when sharing.

### 8.4 Open Graph image API for dynamic previews

Currently all city/type pages share the same static `og-cover.png`.

**Fix: Add a dynamic OG image route:**
```
keyhome-frontend-next/src/app/og/[...params]/route.tsx
```

Using Next.js `ImageResponse` to generate:
- City OG: "**247 annonces à Douala** | KeyHome" with city skyline background
- Ad OG: property photo + price + title overlay

---

## 9. Core Web Vitals & Performance SEO

### 9.1 Current state

`WebVitals.tsx` captures CLS, INP, LCP, FCP, TTFB and forwards to GA4 (if available).

### 9.2 Gaps

| Issue | Impact | Fix |
|---|---|---|
| WebVitals data not in monitoring dashboard | Can't track regressions | Send to Vercel Analytics (already installed) or a custom `/api/vitals` endpoint |
| `<img>` tags without explicit `width`/`height` | CLS (layout shift) | Ensure all `<Image>` from `next/image` have dimensions set |
| Ad images served from external CDN without `sizes` | LCP on mobile | Add `sizes="(max-width: 640px) 100vw, 50vw"` to primary ad image |
| `revalidate: 60` on ad pages | Over-crawling = slow TTFB under load | Increase to `revalidate: 300` (5 min) for stable ad content |
| No `preconnect` for image CDN domain | LCP delay | Add `<link rel="preconnect" href="https://your-cdn.com">` to `layout.tsx` |
| Font loading: `Inter` + `Plus_Jakarta_Sans` both loaded | INP/LCP cost | Consider subsetting or using only one font family |

### 9.3 Lighthouse / CrUX targets

| Metric | Target | Notes |
|---|---|---|
| LCP | < 2.5s | Optimize hero/ad primary image (WebP, `priority` prop) |
| CLS | < 0.1 | Fix image dimensions everywhere |
| INP | < 200ms | Audit heavy client-side components (map, filters) |
| FCP | < 1.8s | Critical CSS inlined (Next.js does this by default) |
| TTFB | < 800ms | Vercel Edge network + Laravel response time |

---

## 10. Ad-Level SEO Details

### 10.1 Slug format

SEO-friendly slugs should contain type + location + ID for maximum keyword coverage:
```
/ads/appartement-3-pieces-akwa-douala-abc123
vs current:
/ads/abc123
```

**Backend fix in `Ad` model or `slug` generation:**
```php
// In AdService or Ad::creating():
$slug = Str::slug("{$ad->type} {$ad->bedrooms}pieces {$ad->quarter->name} {$ad->quarter->city->name}")
    . '-' . Str::random(8);
```

### 10.2 Missing `og:image:secure_url`

Add to ad `generateMetadata`:
```typescript
openGraph: {
  images: [{
    url: ogImage,
    secureUrl: ogImage,  // explicit HTTPS signal
    width: 1200,
    height: 630,
    alt: title,
    type: 'image/jpeg',
  }],
}
```

### 10.3 `description` truncation

Currently `ad.description?.slice(0, 160)` — this can cut in the middle of a word.
Fix:
```typescript
const rawDesc = ad.description || '';
const description = rawDesc.length > 157
  ? rawDesc.slice(0, 157).replace(/\s+\S*$/, '') + '…'
  : rawDesc;
```

---

## 11. Prioritized Action Plan

### 🔴 P0 — Critical (do immediately, highest ranking impact)

| # | Action | File | Effort |
|---|---|---|---|
| 1 | Add `@id` to all JSON-LD entities | `JsonLd.tsx`, `ads/[slug]/page.tsx`, `immobilier/[ville]/page.tsx` | 2h |
| 2 | Add `BlogPosting` schema to all blog posts | `blog/[slug]/page.tsx` | 3h |
| 3 | Implement IndexNow service (backend) + key file (frontend) | `IndexNowService.php`, `public/{KEY}.txt` | 4h |
| 4 | Create `llms.txt` in `public/` | `public/llms.txt` | 30min |
| 5 | Fix `robots.ts`: disallow `/search?*` (paginated search params) | `robots.ts` | 30min |
| 6 | Verify Bing Webmaster Tools + submit sitemap | Manual + CI | 1h |

### 🟠 P1 — High (next sprint, major SEO gains)

| # | Action | File | Effort |
|---|---|---|---|
| 7 | Add missing RealEstateListing fields: `@id`, `numberOfBathroomsTotal`, `propertyType`, `dateModified` | `ads/[slug]/page.tsx` | 2h |
| 8 | Add `RealEstateAgent` schema + `AggregateRating` to agency pages | `agences/[id]/page.tsx` | 3h |
| 9 | Create country landing pages (`/immobilier/cameroun`, etc.) | New page files | 1 day |
| 10 | Add country-specific hreflang (`fr-CM`, `fr-CI`, `fr-BJ`, `fr-TG`, `fr-SN`) | City pages | 2h |
| 11 | Publish `/prix-marche` publicly (remove auth gate for the index) | Route config | 1h |
| 12 | Dynamic OG images (Next.js `ImageResponse`) for city + ad pages | `app/og/route.tsx` | 1 day |
| 13 | Improve ad slugs to `{type}-{quartier}-{ville}-{id}` | `Ad` model | 3h + migration |

### 🟡 P2 — Medium (following sprint, differentiation)

| # | Action | File | Effort |
|---|---|---|---|
| 14 | Sitemap index architecture (split by type, paginate ads) | `sitemap.ts` refactor | 1 day |
| 15 | Add `bailleurs/[username]` pages to sitemap | `sitemap.ts` | 2h |
| 16 | Add `Person` schema to landlord/owner profile pages | `bailleurs/[username]/page.tsx` | 2h |
| 17 | Blog content: rewrite headers as Q&A, add author bylines | Blog data + template | 2 days |
| 18 | Create rent index content (`/indices-loyers`) — link magnet | New page + API endpoint | 3 days |
| 19 | Register on African business directories (Google My Business, etc.) | Manual | 2h |
| 20 | Crunchbase entry + link in Organization `sameAs` | Manual + `JsonLd.tsx` | 1h |

### 🟢 P3 — Low (long-term SEO authority)

| # | Action | Effort |
|---|---|---|
| 21 | Calculateur crédit immobilier tool | 3 days |
| 22 | Rapport immobilier annuel PDF (downloadable, gated by email) | 1 week |
| 23 | English landing pages for Ghana (Accra, Kumasi) | 2 days |
| 24 | Digital PR: press releases, HARO outreach | Ongoing |
| 25 | Google Indexing API for high-velocity ad publishing | 1 day |
| 26 | Add `preconnect` for CDN domain in `layout.tsx` | 30min |

---

## 12. Google & Bing Worldwide Ranking Targets

### Short-term (3 months) — Technical SEO complete
- All P0 + P1 actions done
- IndexNow live → Bing picks up new ads within minutes
- Google Search Console indexing rate > 95%
- Core Web Vitals: LCP < 2.5s, CLS < 0.1 on mobile

### Medium-term (6 months) — Content authority
- Rank top 3 for "immobilier Douala", "appartement Abidjan", "location villa Cotonou"
- Blog ranking for long-tail queries: "comment louer un appartement à Douala"
- AI Overview citations on at least 5 major queries

### Long-term (12 months) — Market leadership
- Rank #1 for "immobilier Cameroun", "immobilier Côte d'Ivoire"
- Knowledge Panel for KeyHome brand in Google
- Domain Authority (Moz) > 40 / Domain Rating (Ahrefs) > 35
- Citations in ChatGPT / Perplexity answers for Africa real estate queries

---

*Research: Firecrawl deep-research, May 2026. Sources: plantandgrowseo.com, eseospace.com, CREA GEO guide, gracker.ai IndexNow guide, THEHOTH backlinks guide, Botify hreflang guide.*
