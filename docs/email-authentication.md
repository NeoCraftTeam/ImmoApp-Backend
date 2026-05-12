# Email Authentication — SPF, DKIM, DMARC

## Overview

KeyHome sends transactional emails from the domain configured in `MAIL_FROM_ADDRESS`.
All three email authentication standards must be configured in DNS and (for DKIM) in the
mail transport provider to avoid spam classification and ensure deliverability.

---

## 1. SPF — Sender Policy Framework

Add a **TXT record** on your sending domain authorizing your mail transport:

### Example (Mailgun EU)
```
Type:  TXT
Host:  @  (or the domain itself)
Value: v=spf1 include:eu.mailgun.org ~all
```

### Example (Amazon SES)
```
Type:  TXT
Host:  @
Value: v=spf1 include:amazonses.com ~all
```

### Example (self-hosted SMTP)
```
Type:  TXT
Host:  @
Value: v=spf1 ip4:<YOUR_SERVER_IP> -all
```

> **Laravel config**: `MAIL_EHLO_DOMAIN` in `.env` must equal the domain in your SPF record.
> The `MailHeaderServiceProvider` sets `Return-Path` to `MAIL_FROM_ADDRESS` automatically,
> ensuring SPF alignment in DMARC.

---

## 2. DKIM — DomainKeys Identified Mail

DKIM signing is performed by the **mail transport**, not by Laravel itself.

### Mailgun
1. In Mailgun dashboard → Sending → Domains → add `keyhome.app`
2. Mailgun generates a 2048-bit key pair
3. Add the CNAME records Mailgun provides, e.g.:
   ```
   Type:  CNAME
   Host:  mailo._domainkey.keyhome.app
   Value: mailo.domainkey.eu.mailgun.org
   ```
4. Set `MAILGUN_DOMAIN=keyhome.app` and `MAILGUN_SECRET=<key>` in `.env`
5. Set `MAIL_MAILER=mailgun` in `.env`

### Amazon SES
1. SES Console → Identities → Verify `keyhome.app`
2. Add the CNAME records SES provides for DKIM
3. Set `AWS_ACCESS_KEY_ID`, `AWS_SECRET_ACCESS_KEY`, `AWS_DEFAULT_REGION` in `.env`
4. Set `MAIL_MAILER=ses` in `.env`

### Self-hosted (Postfix + OpenDKIM)
1. Install `opendkim` on your server
2. Generate key: `opendkim-genkey -b 2048 -d keyhome.app -s mail`
3. Add DNS TXT record:
   ```
   Type:  TXT
   Host:  mail._domainkey.keyhome.app
   Value: v=DKIM1; k=rsa; p=<PUBLIC_KEY>
   ```
4. Configure `/etc/opendkim.conf` and restart services

---

## 3. DMARC — Domain-based Message Authentication

Add a **TXT record** to enforce SPF + DKIM alignment and receive reports:

```
Type:  TXT
Host:  _dmarc.keyhome.app
Value: v=DMARC1; p=quarantine; rua=mailto:dmarc@keyhome.app; ruf=mailto:dmarc@keyhome.app; pct=100; adkim=s; aspf=s
```

| Tag   | Value         | Meaning                                            |
|-------|---------------|----------------------------------------------------|
| `p`   | `quarantine`  | Failing emails go to spam (use `none` while testing) |
| `rua` | MAILTO        | Aggregate report destination                       |
| `ruf` | MAILTO        | Forensic (per-message) report destination          |
| `pct` | `100`         | Apply policy to 100 % of messages                  |
| `adkim` | `s`         | Strict DKIM alignment (DKIM domain = From domain)  |
| `aspf` | `s`          | Strict SPF alignment (Return-Path = From domain)   |

> Start with `p=none` during rollout, then escalate to `p=quarantine` → `p=reject`.

---

## 4. Laravel `.env` Reference

```dotenv
MAIL_MAILER=mailgun            # or ses, postmark, resend, smtp
MAIL_FROM_ADDRESS=no-reply@keyhome.app
MAIL_FROM_NAME="KeyHome"
MAIL_REPLY_TO_ADDRESS=support@keyhome.app
MAIL_REPLY_TO_NAME="Support KeyHome"
MAIL_EHLO_DOMAIN=keyhome.app   # must match SPF record domain

# Mailgun
MAILGUN_DOMAIN=keyhome.app
MAILGUN_SECRET=<api-key>
MAILGUN_ENDPOINT=api.eu.mailgun.net  # EU region

# SES (alternative)
AWS_ACCESS_KEY_ID=<key>
AWS_SECRET_ACCESS_KEY=<secret>
AWS_DEFAULT_REGION=eu-west-1
```

---

## 5. Verification Checklist

- [ ] `dig TXT keyhome.app` returns SPF record
- [ ] `dig TXT mail._domainkey.keyhome.app` (or provider equivalent) returns DKIM key
- [ ] `dig TXT _dmarc.keyhome.app` returns DMARC policy
- [ ] Send test email and check headers (`Authentication-Results`) via mail-tester.com
- [ ] DMARC reports arriving at `dmarc@keyhome.app`
- [ ] mail-tester.com score ≥ 9/10
