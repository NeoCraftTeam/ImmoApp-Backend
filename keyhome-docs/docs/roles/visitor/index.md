---
sidebar_position: 1
title: "Visitor Role"
---

# Visitor Role

## Description

A **Visitor** is any unauthenticated user who accesses KeyHome. Visitors can explore the platform's full catalogue of property listings, use the search engine, view ad details, and sign up for the newsletter — all without creating an account.

The visitor experience is optimised for discovery and conversion: compelling landing page, fast search, rich ad detail pages, and low-friction sign-up prompts.

---

## Permissions

| Action | Allowed |
|---|---|
| View landing page | |
| Browse / search listings | |
| View ad detail pages | (limited — contact info hidden behind unlock) |
| View 3D virtual tours | |
| See neighbourhood scorecard | |
| Subscribe to newsletter | |
| Submit public surveys | |
| Book a viewing | (must register) |
| Save favourites | (must register) |
| Save search alerts | (must register) |
| Post a listing | (must register as Owner) |

---

## Available Routes / Pages

| Route | Purpose |
|---|---|
| `/` | Marketing landing page |
| `/search` | Advanced search / listing browser |
| `/home` | Main listings feed (accessible without auth) |
| `/immobilier` | Real estate info & market overview |
| `/agences` | Public agency directory |
| `/bailleurs` | Landlord information page |
| `/proprietaires` | Property owner information page |
| `/blog` | Articles & market insights |
| `/blog/[slug]` | Individual blog article |
| `/conditions` | Terms of service |
| `/confidentialite` | Privacy policy |
| `/sondage/[slug]` | Public survey (anonymous response) |
| `/type-bien` | Property type browser |
| `/nearby` | Geolocation-based nearby listings |

---

## Navigation Flow

```
Landing (/)
 Search Bar → /search?q=...
 "Voir les annonces" CTA → /home
 "Publier une annonce" CTA → /login (redirects owner to /owner/login)
 Blog section → /blog
 Agency section → /agences

Search (/home or /search)
 Ad Card click → /ads/[slug]

Ad Detail (/ads/[slug])
 Contact button → /login (if unauthenticated)
 "Voir le tour 3D" → Inline PSV viewer
 "Réserver une visite" → /login
```

---

## Related Documentation

- [Landing Page](./landing-page.md)
- [Ad Search](./ad-search.md)
- [Ad Details](./ad-details.md)
- [Newsletter](./newsletter.md)
