---
sidebar_position: 5
title: "Reviews"
---

# Reviews

## Feature Description

Authenticated tenants can submit **property reviews** after interacting with a listing (typically after a confirmed viewing). Reviews include a star rating (1-5) and an optional text comment. Reviews are visible on the ad detail page and in the owner's review management dashboard.

---

## User Journey

1. Authenticated tenant navigates to an ad detail page (`/ads/[slug]`)
2. Scrolls to the "Avis & Notes" (Reviews) section
3. If eligible (has had a confirmed viewing or purchased an unlock), the "Laisser un avis" form is visible
4. Tenant selects a star rating (1–5) and optionally writes a comment
5. Clicks "Soumettre" → `POST /reviews` is called
6. On success → the review appears immediately in the list
7. The overall rating on the listing is updated

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(dashboard)/my/reviews/page.tsx` | Tenant's submitted reviews list |
| `src/components/ads/AdReviews.tsx` | Reviews section on ad detail page |
| `src/components/ads/ReviewForm.tsx` | Star rating + comment form |
| `src/components/ads/ReviewCard.tsx` | Individual review display |
| `src/services/reviews.service.ts` | `create()` |

---

## Key Code Snippets

### Create Review

```typescript
// src/services/reviews.service.ts
interface CreateReviewPayload {
 ad_id: string;
 rating: number; // 1 to 5
 comment?: string;
}

export const reviewsService = {
 async create(payload: CreateReviewPayload) {
 const { data } = await api.post('/reviews', payload);
 return data;
 },
};
```

### Review Type

```typescript
interface Review {
 id: string;
 rating: number;
 comment: string | null;
 user: {
 id: string;
 display_name: string;
 avatar: string | null;
 };
 created_at: string;
}
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Body | Response |
|---|---|---|---|
| `POST` | `/api/v1/reviews` | `{ ad_id, rating, comment? }` | `{ data: Review }` |
| `GET` | `/api/v1/my/reviews` | `{ page?, per_page? }` | `PaginatedResponse<Review>` |

---

## Related Documentation

- [Ad Details](../visitor/ad-details.md)
- [Owner Reviews](../owner/dashboard.md)
