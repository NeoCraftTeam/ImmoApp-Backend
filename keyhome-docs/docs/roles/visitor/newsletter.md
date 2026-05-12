---
sidebar_position: 5
title: "Newsletter"
---

# Newsletter

## Feature Description

The **Newsletter** feature allows any visitor to subscribe to KeyHome's email newsletter to receive property market updates, new listings, and platform news. Subscription is entirely unauthenticated — only an email address is required.

Subscribers can also manage their email preferences via a **one-click unsubscribe link** included in every newsletter email.

---

## User Journey

### Subscribe

1. Visitor lands on the landing page or any marketing page
2. Sees the newsletter subscription section (typically in the footer or a dedicated CTA section)
3. Enters their email address and clicks "S'abonner" (Subscribe)
4. A `POST /newsletter/subscribe` request is sent
5. If the email is new → subscription confirmed; a welcome email is sent by the backend
6. If the email already exists → a graceful "already subscribed" message is shown
7. Visitor receives a confirmation toast notification

### Unsubscribe

1. User receives a newsletter email with an unsubscribe link
2. Clicks the link → routed to `/email-preferences?token=...`
3. The `EmailPreferenceController` (backend) handles the token-based opt-out
4. User's preferences are updated; they receive a confirmation message

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/components/landing/NewsletterSection.tsx` | Newsletter signup form in landing page |
| `src/components/layout/Footer.tsx` | Footer with secondary newsletter CTA |
| `src/services/newsletter.service.ts` | `subscribe()`, `unsubscribe()` API calls |

---

## Key Code Snippets

### Subscribe Service Call

```typescript
// src/services/newsletter.service.ts — Inferred from service patterns
export const newsletterService = {
 async subscribe(email: string): Promise<{ message: string }> {
 const { data } = await api.post('/newsletter/subscribe', { email });
 return data;
 },

 async unsubscribe(token: string): Promise<{ message: string }> {
 const { data } = await api.post('/newsletter/unsubscribe', { token });
 return data;
 },
};
```

### Newsletter Form Component (Typical Pattern)

```typescript
// src/components/landing/NewsletterSection.tsx
function NewsletterSection() {
 const [email, setEmail] = useState('');
 const { enqueueSnackbar } = useSnackbar();

 const handleSubscribe = async (e: FormEvent) => {
 e.preventDefault();
 try {
 await newsletterService.subscribe(email);
 enqueueSnackbar('Inscription réussie !', { variant: 'success' });
 setEmail('');
 } catch (err) {
 enqueueSnackbar(extractErrorMessage(err), { variant: 'error' });
 }
 };

 return (
 <Box component="form" onSubmit={handleSubscribe}>
 <TextField
 type="email"
 value={email}
 onChange={(e) => setEmail(e.target.value)}
 placeholder="Votre adresse email"
 required
 />
 <Button type="submit" variant="contained">
 S'abonner
 </Button>
 </Box>
 );
}
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Body | Response |
|---|---|---|---|
| `POST` | `/api/v1/newsletter/subscribe` | `{ email: string }` | `{ message: string }` |
| `POST` | `/api/v1/newsletter/unsubscribe` | `{ token: string }` | `{ message: string }` |
| `GET` | `/email-preferences` | `?token=...` (query param) | Redirect / HTML confirmation |

### Subscribe Response

```json
{
 "message": "Vous avez été inscrit avec succès."
}
```

---

## Email Preferences Management

The backend `EmailPreferenceController` handles the preferences page at `/email-preferences`. This is a **non-API, server-rendered route** handled directly by Laravel (not Next.js).

Subscribers can control:
- Newsletter frequency
- New listings alerts
- Market report emails
- Complete unsubscribe

---

## Related Documentation

- [Landing Page](./landing-page.md)
- [Tenant Authentication](../tenant/authentication.md) — for logged-in users managing email prefs via settings
