Based on everything I've seen in this codebase — the stack, the market (francophone Africa, FedaPay, Cameroon), the existing features — here's an honest prioritized roadmap:

Revenue-Critical (build these first)
1. Agent Lead Management CRM
Agents pay subscriptions but have no tools to convert leads. Add a pipeline: visitor contacts agent → lead captured → agent tracks status (contacted, visit scheduled, offer made, closed). This directly increases subscription renewal rate, which is your core recurring revenue.

2. Virtual Property Tours (360° / Video)
Spatie Media is already wired. Extend it to support ordered photo galleries, video uploads, and an embeddable 360° viewer (Pannellum, open source). In markets with bad infrastructure, reducing unnecessary physical visits is a massive competitive advantage. Charge agents a "premium listing" tier for this.

3. Verified Listings Badge
A manual or semi-automated verification workflow where an admin confirms a property exists (photo review, WhatsApp call, GPS check). Charge agents per verification or include it in a higher subscription tier. Solves the trust problem that kills real estate marketplaces in Africa.

4. Rental Contract Generator
Pre-filled PDF contract generation based on listing data, tenant info, and local legal templates. Landlords pay per contract. Extremely high value, near-zero technical risk — use a PDF library (barryvdh/laravel-dompdf already common in Laravel). This turns KeyHome from a listing site into a transaction platform.

Growth-Critical (drives user acquisition)
5. WhatsApp-First Notifications
You already send emails. In your market, WhatsApp has 10x the open rate. Integrate the WhatsApp Business API (or Vonage/Twilio) to send verification codes, rental reminders, price drop alerts. Every notification becomes a re-engagement touchpoint.

6. Price History & Market Analytics per Zone
You have geolocation (Magellan + Mapbox) and listings over time. Start recording price snapshots on listings at creation/update. Surface a "prix moyen au m²" per neighborhood on the search page. This creates a data moat — no competitor can replicate historical data.

7. Neighborhood Score / Life Quality Index
For each listing, show proximity to schools, hospitals, markets, transport (using Overpass API / OpenStreetMap data — free). Users filter by these. This is a strong SEO driver and differentiator over classifieds sites.

8. Saved Search Alerts
Users save a search (3 bed, < 150k FCFA, Akwa) and get notified when a new matching listing appears. Currently you have recommendations analytics. Add push/email/WhatsApp alerts. This makes the app habit-forming.

Monetization Unlock
9. Agent Directory with SEO Pages
Each verified agent gets a public SEO-indexed page (/agents/jean-dupont-douala) with their listings, reviews, response rate, and a "contact" CTA. Agents will pay more to be visible. Google indexes it and brings free organic traffic.

10. Escrow / Deposit Handling
The hardest one but the highest leverage: allow tenants to pay a deposit through KeyHome (held briefly, released to landlord after key handover confirmation). You take a percentage. FedaPay already handles payments. This is where the real money is — you become the trusted financial intermediary.

11. Boost / Sponsored Listings
Already in your PaymentType enum (boost). It's not implemented yet. Build the UI: agents pay to pin a listing to the top of search results for 7/14/30 days. This is pure margin, no new infrastructure needed.

Retention & Trust
12. Response Rate & Response Time on Agent Profiles
Track how fast agents reply to inquiries. Show a badge: "Répond en moins d'1h". This pressures agents to be responsive, improving user experience and your platform's reputation.

13. Tenant Scoring / Rental History
Landlords rate tenants after a rental. Tenants with good scores get priority access to listings. Creates strong two-sided network effects — both landlords and tenants need to be on KeyHome.

14. AI-Generated Listing Descriptions
Agent uploads photos → AI (GPT-4o vision or open source) generates a well-formatted listing description in French. Reduces friction for agents to create quality listings. Better listings → more conversions → higher LTV per agent.