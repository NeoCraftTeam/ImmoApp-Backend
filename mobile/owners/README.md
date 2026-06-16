# KeyHome — Owners (mobile)

Placeholder for the **bailleur / agent** mobile experience.

The owner flow is a separate Expo workspace (deliberately not a route
group inside `visitors/`) because the two audiences have:

- different bundle identifiers (`com.keyhome.visitors` vs.
  `com.keyhome.owners`) so the App Store / Play Store listings can be
  marketed independently;
- different default landing screens (visitors → feed; owners →
  dashboard) and different bottom-nav surfaces;
- different feature gates (owners can publish ads, manage subscriptions,
  see analytics — none of which the visitor app exposes);
- different release cadences likely (visitor app is consumer-facing and
  benefits from frequent OTA updates; the owner app is more
  transaction-critical and probably wants slower, audited releases).

Scaffold lives outside this commit — start it the same way as
`visitors/`:

```bash
cd mobile/owners
# scaffold from the visitors workspace as a template — copy
# tamagui.config.ts, the api/ and theme/ folders, and the providers.
```

The web equivalent is `keyhome-frontend-next/src/app/(owner)/...`.
