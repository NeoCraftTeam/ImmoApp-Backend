---
sidebar_position: 5
title: "Lease Contracts"
---

# Lease Contracts

## Feature Description

The **Lease Contracts** module allows owners to generate legally-structured rental contracts (PDF), manage tenant information, and collect **electronic signatures** from tenants. It includes an AI assistant to enhance contract clauses.

This feature is available at `/owner/lease-contracts` and integrates with the **tenant management** system.

---

## User Journey

### Generate a Lease Contract

1. Owner navigates to `/owner/lease-contracts`
2. Clicks "Nouveau contrat"
3. Selects the listing (property) for the contract
4. Associates or creates a tenant record
5. Fills in contract details:
 - Lease start/end dates
 - Monthly rent amount
 - Deposit amount
 - Special conditions / clauses
 - (Optional) uses AI enhancement for conditions → `POST /my/lease-contracts/ai/enhance-conditions`
6. Clicks "Générer le contrat"
7. `POST /my/lease-contracts/:id/generate` creates the PDF
8. Owner can preview/download the PDF → `GET /my/lease-contracts/:id/download`

### Request E-Signature

1. After generating the contract, owner clicks "Demander une signature"
2. Enters the tenant's email and name
3. `POST /my/lease-contracts/:id/signatures` creates a signature request
4. Tenant receives an email with a unique signing link
5. Tenant clicks the link → navigated to the signature page (accessible without login)
6. Tenant reviews the contract and clicks "Signer" → `POST /signatures/:token/sign`
7. Owner is notified of the signed contract

### Tenant Declining to Sign

1. Tenant can click "Refuser" on the signing page
2. Optionally provides a reason
3. `POST /signatures/:token/decline` records the refusal
4. Owner is notified

---

## Tenant Management

Owners maintain a CRM of tenants at `/owner/tenants`:

| Operation | Description |
|---|---|
| List tenants | View all tenants associated with the owner |
| Create tenant | Add a new tenant with name, email, phone, ID number |
| Update tenant | Modify tenant details |
| Delete tenant | Remove a tenant record |

---

## Frontend Files Involved

| File | Role |
|---|---|
| `src/app/(owner)/owner/lease-contracts/page.tsx` | Lease contracts list |
| `src/app/(owner)/owner/tenants/page.tsx` | Tenant management |
| `src/components/owner/LeaseContractList.tsx` | Contracts table |
| `src/components/owner/LeaseContractForm.tsx` | Contract generation form |
| `src/components/owner/TenantForm.tsx` | Tenant create/edit form |
| `src/components/owner/SignatureStatus.tsx` | E-signature status indicator |
| `src/app/signatures/[token]/page.tsx` | Public signing page (no auth required) |
| `src/services/owner.service.ts` | All lease & tenant API calls |
| `src/types/index.ts` | `LeaseContract`, `Tenant`, `SignatureRequest` types |

---

## Key Code Snippets

### Generate Lease Contract

```typescript
// src/services/owner.service.ts
async generateLeaseContract(contractId: string, payload: LeaseGeneratePayload) {
 const { data } = await api.post(
 `/my/lease-contracts/${contractId}/generate`,
 payload
 );
 return data;
},
```

### Download PDF

```typescript
// src/services/owner.service.ts
async downloadLeaseContract(contractId: string): Promise<Blob> {
 const { data } = await api.get(
 `/my/lease-contracts/${contractId}/download`,
 { responseType: 'blob' }
 );
 return data;
},
```

### AI-Enhanced Conditions

```typescript
// src/services/owner.service.ts
async enhanceLeaseConditions(conditions: string) {
 const { data } = await api.post(
 '/my/lease-contracts/ai/enhance-conditions',
 { conditions }
 );
 return data; // { enhanced: string }
},
```

### Create Signature Request

```typescript
// src/services/owner.service.ts
async createSignatureRequest(contractId: string, payload: {
 signer_email: string;
 signer_name: string;
}) {
 const { data } = await api.post(
 `/my/lease-contracts/${contractId}/signatures`,
 payload
 );
 return data; // SignatureRequest
},
```

### Tenant Signing (Public Route)

```typescript
// src/services/owner.service.ts
// Accessible without authentication via token
async getPublicSignatureRequest(token: string) {
 const { data } = await api.get(`/signatures/${token}`);
 return data; // { request: SignatureRequest, contract: LeaseContract }
},

async signSignatureRequest(token: string) {
 const { data } = await api.post(`/signatures/${token}/sign`);
 return data;
},

async declineSignatureRequest(token: string, reason?: string) {
 const { data } = await api.post(`/signatures/${token}/decline`, { reason });
 return data;
},
```

---

## Data Models

```typescript
interface LeaseContract {
 id: string;
 ad_id: string;
 tenant_id: string;
 start_date: string;
 end_date: string;
 monthly_rent: number;
 deposit: number;
 conditions: string | null;
 pdf_url: string | null;
 status: 'draft' | 'generated' | 'signed' | 'declined';
 created_at: string;
}

interface Tenant {
 id: string;
 firstname: string;
 lastname: string;
 email: string;
 phone_number: string;
 national_id?: string;
 created_at: string;
}

interface SignatureRequest {
 id: string;
 token: string;
 signer_email: string;
 signer_name: string;
 status: 'pending' | 'signed' | 'declined';
 signed_at: string | null;
 declined_at: string | null;
 decline_reason: string | null;
}
```

---

## Backend API Endpoints

** Inferred from frontend code**

| Method | Endpoint | Body | Response |
|---|---|---|---|
| `GET` | `/api/v1/my/lease-contracts` | `{ page?, per_page? }` | `PaginatedResponse<LeaseContract>` |
| `GET` | `/api/v1/my/lease-contracts/:id` | — | `{ data: LeaseContract }` |
| `PUT` | `/api/v1/my/lease-contracts/:id` | Contract update fields | `{ data: LeaseContract }` |
| `POST` | `/api/v1/my/lease-contracts/:id/generate` | Tenant & lease data | Generated contract |
| `GET` | `/api/v1/my/lease-contracts/:id/download` | — | `Blob` (PDF) |
| `POST` | `/api/v1/my/lease-contracts/ai/enhance-conditions` | `{ conditions }` | `{ enhanced }` |
| `GET` | `/api/v1/my/lease-contracts/:id/signatures` | — | `SignatureRequest[]` |
| `POST` | `/api/v1/my/lease-contracts/:id/signatures` | `{ signer_email, signer_name }` | `SignatureRequest` |
| `GET` | `/api/v1/signatures/:token` | — | `{ request, contract }` |
| `POST` | `/api/v1/signatures/:token/sign` | — | `{}` |
| `POST` | `/api/v1/signatures/:token/decline` | `{ reason? }` | `{}` |
| `GET` | `/api/v1/my/tenants` | `{ page?, per_page? }` | `{ data: Tenant[], meta }` |
| `POST` | `/api/v1/my/tenants` | `TenantPayload` | `{ data: Tenant }` |
| `PUT` | `/api/v1/my/tenants/:id` | `TenantPayload` | `{ data: Tenant }` |
| `DELETE` | `/api/v1/my/tenants/:id` | — | `{}` |

---

## Related Documentation

- [Ad Management](./ad-management.md)
- [Owner Dashboard](./dashboard.md)
