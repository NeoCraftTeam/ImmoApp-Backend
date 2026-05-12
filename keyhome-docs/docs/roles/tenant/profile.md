---
sidebar_position: 3
title: "Profile Management"
---

# Profile Management

## Feature Description

Authenticated tenants can view and update their personal profile including display name, phone number, city, and avatar image. Profile management is available at `/profile` (PRIVATE route).

---

## User Journey

1. Authenticated user navigates to `/profile`
2. Current profile data is loaded from the authenticated user context (`AuthProvider`)
3. User can edit:
 - First name and last name
 - Phone number
 - City (via city autocomplete)
 - Profile avatar (image upload, compressed before upload)
4. User submits the form → `POST /users/:id` (multipart FormData for avatar) or `PUT /users/:id` (JSON for profile fields)
5. On success → `AuthProvider.refreshUser()` updates the global user state
6. Success toast is displayed

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(dashboard)/profile/page.tsx` | Profile route (PRIVATE) |
| `src/components/dashboard/ProfileForm.tsx` | Profile edit form |
| `src/components/dashboard/AvatarUpload.tsx` | Avatar image upload component |
| `src/services/users.service.ts` | `update()`, `updateProfile()` |
| `src/providers/AuthProvider.tsx` | `user`, `setUser`, `refreshUser` |
| `src/lib/image-compression.ts` | Client-side image compression before upload |
| `src/lib/city-autocomplete-config.tsx` | City search autocomplete |
| `src/lib/password-strength.ts` | Password validation |

---

## Key Code Snippets

### Update Profile (JSON fields)

```typescript
// src/services/users.service.ts
async updateProfile(id: string, payload: Partial<UserUpdatePayload>): Promise<User> {
 const { data } = await api.put(`/users/${id}`, payload);
 return data.data;
},
```

### Update Avatar (FormData multipart)

```typescript
// src/services/users.service.ts
async update(id: string, formData: FormData): Promise<User> {
 const { data } = await api.post(`/users/${id}`, formData, {
 headers: { 'Content-Type': 'multipart/form-data' },
 });
 return data.data;
},
```

### Image Compression Before Upload

```typescript
// src/lib/image-compression.ts
export async function compressImage(file: File, maxSizeKB = 800): Promise<File> {
 // Uses browser-image-compression library
 // Reduces file size while maintaining quality
 const compressed = await imageCompression(file, {
 maxSizeMB: maxSizeKB / 1024,
 maxWidthOrHeight: 1200,
 useWebWorker: true,
 });
 return compressed;
}
```

---

## Password Change

Tenants can change their password via the settings page (`/parametres`):

```typescript
// src/services/auth.service.ts
async updatePassword(payload: {
 current_password: string;
 new_password: string;
 new_password_confirmation: string;
}): Promise<{ message: string }> {
 const { data } = await api.post('/auth/update-password', payload);
 return data;
},
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Body | Response |
|---|---|---|---|
| `GET` | `/api/v1/auth/me` | — | `User` |
| `POST` | `/api/v1/users/:id` | FormData (avatar, name, etc.) | `{ data: User }` |
| `PUT` | `/api/v1/users/:id` | `{ phone_number?, city_id? }` | `{ data: User }` |
| `POST` | `/api/v1/auth/update-password` | `{ current_password, new_password, new_password_confirmation }` | `{ message }` |

### User Model Shape

```typescript
interface User {
 id: string;
 firstname: string;
 lastname: string;
 email: string;
 phone_number: string | null;
 avatar: string | null;
 display_name: string;
 role: 'customer' | 'agent' | 'admin';
 type: 'individual' | 'agency';
 city_id: string | null;
 point_balance: number;
 onboarding_completed_at: string | null;
 preferences: {
 survey_postponed_ids: string[];
 };
}
```
