# Email Deliverability — SPF, DKIM, DMARC Configuration Guide

> **Last Updated:** 2025-03-21
> **Scope:** DNS records required for production email deliverability

---

## Overview

Without properly configured SPF, DKIM, and DMARC records, emails sent from KeyHome may be:
- Delivered to spam/junk folders
- Rejected outright by major providers (Gmail, Outlook, Yahoo)
- Flagged as phishing attempts

---

## 1. SPF (Sender Policy Framework)

SPF tells receiving servers which mail servers are authorized to send email on behalf of your domain.

### DNS Record

Add a **TXT** record to your domain's DNS:

```
Type:  TXT
Host:  @
Value: v=spf1 include:_spf.google.com include:sendgrid.net include:mailgun.org ~all
```

**Adjust `include:` entries** based on your actual mail provider:

| Provider | SPF Include |
|----------|-------------|
| Mailgun | `include:mailgun.org` |
| SendGrid | `include:sendgrid.net` |
| Amazon SES | `include:amazonses.com` |
| Postmark | `include:spf.mtasv.net` |
| Google Workspace | `include:_spf.google.com` |
| Brevo (Sendinblue) | `include:sendinblue.com` |

**Rules:**
- Maximum 10 DNS lookups per SPF record
- Use `~all` (soft fail) initially, move to `-all` (hard fail) once verified
- Only ONE SPF record per domain (merge if needed)

---

## 2. DKIM (DomainKeys Identified Mail)

DKIM adds a cryptographic signature to outgoing emails, proving they weren't altered in transit.

### Setup

Your mail provider will generate a DKIM key pair. Add the **public key** as a DNS TXT record:

```
Type:  TXT
Host:  <selector>._domainkey     (e.g., mail._domainkey or s1._domainkey)
Value: v=DKIM1; k=rsa; p=<public-key-base64>
```

**Provider-specific selectors:**

| Provider | Selector / Setup Location |
|----------|---------------------------|
| Mailgun | Dashboard → Domain → DNS Records |
| SendGrid | Settings → Sender Authentication → Authenticate Domain |
| Amazon SES | Identities → Domain → DKIM |
| Postmark | Sender Signatures → DNS Settings |

**Note:** Each provider gives you the exact record to add. Follow their documentation.

---

## 3. DMARC (Domain-based Message Authentication, Reporting & Conformance)

DMARC tells receivers what to do when SPF/DKIM checks fail, and where to send reports.

### DNS Record — Start with Monitoring Mode

```
Type:  TXT
Host:  _dmarc
Value: v=DMARC1; p=none; rua=mailto:dmarc-reports@keyhome.cm; pct=100; adkim=r; aspf=r
```

### Progressive Enforcement

| Phase | Policy | Duration | Purpose |
|-------|--------|----------|---------|
| 1 | `p=none` | 2–4 weeks | Monitor only — collect reports, identify legitimate senders |
| 2 | `p=quarantine; pct=25` | 2 weeks | Quarantine 25% of failing emails |
| 3 | `p=quarantine; pct=100` | 2 weeks | Quarantine all failing emails |
| 4 | `p=reject; pct=100` | Ongoing | Reject all non-authenticated emails |

**DMARC Parameters:**
- `p=` — Policy: `none` (monitor), `quarantine` (spam), `reject` (block)
- `rua=` — Aggregate report recipient (daily XML reports)
- `ruf=` — Forensic report recipient (per-failure reports, optional)
- `pct=` — Percentage of messages subject to policy (1–100)
- `adkim=` — DKIM alignment: `r` (relaxed) or `s` (strict)
- `aspf=` — SPF alignment: `r` (relaxed) or `s` (strict)

---

## 4. Verification

### Online Tools
- [MXToolbox SPF/DKIM/DMARC Checker](https://mxtoolbox.com/SuperTool.aspx)
- [Google Admin Toolbox](https://toolbox.googleapps.com/apps/checkmx/)
- [DMARC Analyzer](https://www.dmarcanalyzer.com/)
- [Mail Tester](https://www.mail-tester.com/) — send a test email and get a deliverability score

### CLI Verification

```bash
# Check SPF
dig TXT keyhome.cm +short | grep spf

# Check DKIM (replace 'selector' with your actual selector)
dig TXT selector._domainkey.keyhome.cm +short

# Check DMARC
dig TXT _dmarc.keyhome.cm +short
```

---

## 5. Laravel Configuration

Ensure your `.env` matches the sending domain:

```env
MAIL_MAILER=smtp
MAIL_FROM_ADDRESS=no-reply@keyhome.cm
MAIL_FROM_NAME="KeyHome"
```

The `MAIL_FROM_ADDRESS` domain must match or be a subdomain of the domain with SPF/DKIM/DMARC records.

---

## 6. Bounce & Complaint Handling

### Recommended Setup
1. Configure your mail provider's **webhook** for bounces and complaints
2. Add a `MailBounce` listener to automatically:
   - Mark hard-bounced emails as undeliverable
   - Suppress further sends to bounced addresses
   - Log complaint (spam report) events

### Provider Webhooks

| Provider | Webhook Events |
|----------|---------------|
| Mailgun | Bounced, Complained, Unsubscribed |
| SendGrid | Bounce, SpamReport, Invalid |
| Amazon SES | Bounce, Complaint (via SNS) |
| Postmark | Bounce, SpamComplaint |

---

## 7. Checklist

- [ ] SPF record added and verified
- [ ] DKIM key generated and DNS record added
- [ ] DMARC record added in `p=none` monitoring mode
- [ ] Test email sent and scored 9+ on mail-tester.com
- [ ] DMARC reports being received and reviewed
- [ ] Bounce/complaint webhook configured
- [ ] Graduated to `p=quarantine` after 2–4 weeks of clean reports
- [ ] Graduated to `p=reject` after confident all legitimate senders authenticated
