# ADR-001 — Flutterwave as Sole Payment Gateway

**Status:** Accepted
**Date:** March 2026
**Deciders:** NeoCraft engineering team

---

## Context

KeyHome operates in XAF/XOF markets (Cameroon, CEMAC, UEMOA). The previous architecture supported two gateways: FedaPay and Flutterwave. FedaPay was removed in March 2026 (commit `3232679f`) after evaluation showed:

- Flutterwave supports XAF/XOF natively with better coverage across West/Central Africa
- FedaPay integration added 605 lines of duplicated gateway code and test scaffolding
- Maintaining two gateways with the same feature set introduced synchronization risk

---

## Decision

**Use Flutterwave as the sole payment gateway.** The architecture remains extensible via the `PaymentGatewayInterface` strategy pattern — adding Wave Mobile Money, Stripe (for agency SaaS billing), or Paystack in the future requires zero changes to `PaymentService`.

---

## Consequences

### Positive
- `-605 lines` of code removed
- Single gateway reduces maintenance surface and security audit scope
- `Payment::gateway` stored as plain `string` (not enum cast) — future gateways don't require enum changes or migrations
- All 592 tests still pass after removal

### Negative / Risks
- Single point of failure for payments — if Flutterwave has downtime, payments are unavailable
- Mitigation: `PaymentService` constructor accepts `$primary` and `$fallback` gateway — wire fallback when a second gateway is integrated

### Neutral
- `PaymentGateway` enum retained with single case `Flutterwave = 'flutterwave'` — ready to extend
- Webhook route constrained: `POST /api/v1/webhooks/{gateway}` where `gateway` is `flutterwave`

---

## Alternatives Considered

| Option | Rejected reason |
|--------|----------------|
| Keep both FedaPay + Flutterwave | Duplicated test surface, maintenance risk |
| Keep FedaPay only | Worse geographic coverage |
| Abstract fully to multi-gateway | Premature — no current second gateway requirement |
