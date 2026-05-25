@php
    $apiVersion = 'v1';
    $isLocal    = app()->environment('local');
@endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <meta name="description" content="KeyHome — Plateforme immobilière pour l'Afrique francophone subsaharienne.">
    <title>KeyHome — Votre patrimoine immobilier en poche</title>

    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">

    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --brand:   #F6475F;
            --teal:    #0D9488;
            --bg:      #0f0f13;
            --surface: rgba(255,255,255,.045);
            --border:  rgba(255,255,255,.08);
            --text:    #f0f0f4;
            --muted:   #7a7a8e;
            --radius:  14px;
        }

        html { scroll-behavior: smooth; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: var(--bg);
            color: var(--text);
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            overflow-x: hidden;
        }

        /* ── Background glow ──────────────────────────────── */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background:
                radial-gradient(ellipse 70% 50% at 50% -10%, rgba(246,71,95,.18) 0%, transparent 70%),
                radial-gradient(ellipse 40% 30% at 80% 90%, rgba(13,148,136,.1) 0%, transparent 60%);
            pointer-events: none;
            z-index: 0;
        }

        /* Subtle dot grid */
        body::after {
            content: '';
            position: fixed;
            inset: 0;
            background-image: radial-gradient(rgba(255,255,255,.035) 1px, transparent 1px);
            background-size: 32px 32px;
            pointer-events: none;
            z-index: 0;
        }

        /* ── Navigation ───────────────────────────────────── */
        nav {
            position: relative;
            z-index: 10;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 2rem;
            border-bottom: 1px solid var(--border);
            backdrop-filter: blur(12px);
            background: rgba(15,15,19,.6);
        }

        .nav-logo {
            display: flex;
            align-items: center;
            gap: .625rem;
            text-decoration: none;
            color: var(--text);
        }

        .nav-logo svg { flex-shrink: 0; }

        .nav-logo-text {
            font-size: 1.125rem;
            font-weight: 700;
            letter-spacing: -.03em;
        }

        .nav-logo-text span { color: var(--brand); }

        .nav-right {
            display: flex;
            align-items: center;
            gap: .875rem;
        }

        .badge-api {
            font-size: .7rem;
            font-weight: 600;
            letter-spacing: .08em;
            text-transform: uppercase;
            color: var(--muted);
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 999px;
            padding: .25rem .7rem;
        }

        .badge-status {
            display: flex;
            align-items: center;
            gap: .4rem;
            font-size: .75rem;
            font-weight: 500;
            color: #4ade80;
        }

        .badge-status::before {
            content: '';
            display: block;
            width: 7px;
            height: 7px;
            background: #4ade80;
            border-radius: 50%;
            box-shadow: 0 0 6px #4ade80;
            animation: pulse-dot 2.4s ease-in-out infinite;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; }
            50%       { opacity: .4; }
        }

        /* ── Main content ─────────────────────────────────── */
        main {
            position: relative;
            z-index: 1;
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 5rem 1.5rem 4rem;
        }

        /* ── Hero ─────────────────────────────────────────── */
        .hero { text-align: center; max-width: 680px; }

        .hero-icon {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 88px;
            height: 88px;
            border-radius: 24px;
            background: linear-gradient(135deg, rgba(246,71,95,.22) 0%, rgba(246,71,95,.08) 100%);
            border: 1px solid rgba(246,71,95,.3);
            margin-bottom: 2rem;
            position: relative;
        }

        .hero-icon::before {
            content: '';
            position: absolute;
            inset: -20px;
            border-radius: 50%;
            background: radial-gradient(circle, rgba(246,71,95,.2) 0%, transparent 70%);
            animation: glow-pulse 3s ease-in-out infinite;
        }

        @keyframes glow-pulse {
            0%, 100% { transform: scale(1);   opacity: 1; }
            50%       { transform: scale(1.15); opacity: .6; }
        }

        .hero h1 {
            font-size: clamp(2.25rem, 5vw, 3.5rem);
            font-weight: 800;
            letter-spacing: -.04em;
            line-height: 1.1;
            margin-bottom: 1rem;
        }

        .hero h1 .accent { color: var(--brand); }

        .hero p {
            font-size: 1.1rem;
            color: var(--muted);
            line-height: 1.65;
            margin-bottom: 2.25rem;
        }

        .hero-actions {
            display: flex;
            flex-wrap: wrap;
            gap: .75rem;
            justify-content: center;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: .45rem;
            border-radius: 999px;
            padding: .7rem 1.5rem;
            font-size: .9rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform .18s, box-shadow .18s, opacity .18s;
            cursor: pointer;
            border: none;
        }

        .btn:hover { transform: translateY(-2px); opacity: .92; }

        .btn-primary {
            background: var(--brand);
            color: #fff;
            box-shadow: 0 4px 24px rgba(246,71,95,.35);
        }

        .btn-primary:hover { box-shadow: 0 6px 30px rgba(246,71,95,.5); }

        .btn-ghost {
            background: var(--surface);
            color: var(--text);
            border: 1px solid var(--border);
        }

        /* ── Divider ──────────────────────────────────────── */
        .divider {
            width: 100%;
            max-width: 840px;
            height: 1px;
            background: linear-gradient(90deg, transparent, var(--border), transparent);
            margin: 4rem 0;
        }

        /* ── Features ─────────────────────────────────────── */
        .features {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(240px, 1fr));
            gap: 1.25rem;
            width: 100%;
            max-width: 840px;
        }

        .card {
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            padding: 1.5rem;
            transition: transform .2s, border-color .2s, background .2s;
        }

        .card:hover {
            transform: translateY(-3px);
            border-color: rgba(246,71,95,.25);
            background: rgba(246,71,95,.04);
        }

        .card-icon {
            width: 44px;
            height: 44px;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 1rem;
            font-size: 1.25rem;
        }

        .card-icon.red  { background: rgba(246,71,95,.12); }
        .card-icon.teal { background: rgba(13,148,136,.12); }
        .card-icon.gold { background: rgba(234,179,8,.1); }

        .card h3 {
            font-size: .95rem;
            font-weight: 600;
            margin-bottom: .4rem;
        }

        .card p {
            font-size: .84rem;
            color: var(--muted);
            line-height: 1.6;
        }

        /* ── Stats ────────────────────────────────────────── */
        .stats {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1px;
            background: var(--border);
            border: 1px solid var(--border);
            border-radius: var(--radius);
            overflow: hidden;
            width: 100%;
            max-width: 840px;
            margin-top: 1.25rem;
        }

        .stat {
            background: var(--bg);
            padding: 1.5rem;
            text-align: center;
        }

        .stat-value {
            font-size: 1.75rem;
            font-weight: 800;
            letter-spacing: -.04em;
            color: var(--text);
        }

        .stat-value .unit { color: var(--brand); font-size: 1.1rem; }

        .stat-label {
            font-size: .78rem;
            color: var(--muted);
            margin-top: .25rem;
        }

        /* ── API endpoints pill list ─────────────────────── */
        .endpoints {
            width: 100%;
            max-width: 840px;
            margin-top: 1.25rem;
        }

        .endpoints-header {
            display: flex;
            align-items: center;
            gap: .75rem;
            margin-bottom: .875rem;
        }

        .endpoints-header h2 {
            font-size: .8rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .1em;
            color: var(--muted);
        }

        .endpoints-list {
            display: flex;
            flex-wrap: wrap;
            gap: .5rem;
        }

        .ep {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            background: var(--surface);
            border: 1px solid var(--border);
            border-radius: 8px;
            padding: .35rem .75rem;
            font-size: .78rem;
            font-family: 'SF Mono', 'Fira Code', ui-monospace, monospace;
            color: var(--muted);
            transition: color .15s, border-color .15s;
        }

        .ep:hover { color: var(--text); border-color: rgba(246,71,95,.2); }

        .ep .method {
            font-size: .65rem;
            font-weight: 700;
            letter-spacing: .04em;
            padding: .1rem .35rem;
            border-radius: 4px;
        }

        .get  { background: rgba(34,197,94,.15);  color: #4ade80; }
        .post { background: rgba(59,130,246,.15);  color: #60a5fa; }
        .del  { background: rgba(246,71,95,.15);   color: #f87171; }

        /* ── Footer ───────────────────────────────────────── */
        footer {
            position: relative;
            z-index: 1;
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 1.25rem 2rem;
            border-top: 1px solid var(--border);
            font-size: .78rem;
            color: var(--muted);
            flex-wrap: wrap;
            gap: .5rem;
        }

        footer a {
            color: var(--muted);
            text-decoration: none;
            transition: color .15s;
        }

        footer a:hover { color: var(--text); }

        .footer-links { display: flex; gap: 1.25rem; }

        /* ── Fade-in animation ────────────────────────────── */
        @keyframes fade-up {
            from { opacity: 0; transform: translateY(20px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .hero     { animation: fade-up .6s ease both; }
        .divider  { animation: fade-up .6s .15s ease both; }
        .features { animation: fade-up .6s .25s ease both; }
        .stats    { animation: fade-up .6s .35s ease both; }
        .endpoints{ animation: fade-up .6s .45s ease both; }

        /* ── Responsive ───────────────────────────────────── */
        @media (max-width: 640px) {
            nav { padding: 1rem; }
            .badge-status-label { display: none; }
            main { padding: 3rem 1rem 3rem; }
            .stats { grid-template-columns: 1fr; }
            footer { flex-direction: column; align-items: flex-start; }
        }
    </style>
</head>
<body>

{{-- ── Navigation ─────────────────────────────────────────────── --}}
<nav>
    <a href="/" class="nav-logo">
        {{-- Key SVG (silhouette brand icon, no color fill block) --}}
        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle cx="8" cy="8" r="4.5" stroke="#F6475F" stroke-width="1.8"/>
            <path d="M11.5 11.5L20 20" stroke="#F6475F" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M17 18l2 2" stroke="#F6475F" stroke-width="1.8" stroke-linecap="round"/>
            <path d="M15 16l2 2" stroke="#F6475F" stroke-width="1.8" stroke-linecap="round"/>
        </svg>
        <span class="nav-logo-text">Key<span>Home</span></span>
    </a>
    <div class="nav-right">
        <span class="badge-api">API {{ $apiVersion }}</span>
        <span class="badge-status">
            <span class="badge-status-label">Opérationnelle</span>
        </span>
    </div>
</nav>

{{-- ── Main ────────────────────────────────────────────────────── --}}
<main>

    {{-- Hero --}}
    <section class="hero">
        <div class="hero-icon" aria-hidden="true">
            <svg width="40" height="40" viewBox="0 0 24 24" fill="none">
                <circle cx="8" cy="8" r="4.5" stroke="#F6475F" stroke-width="1.6"/>
                <path d="M11.5 11.5L20 20" stroke="#F6475F" stroke-width="1.6" stroke-linecap="round"/>
                <path d="M17 18l2 2" stroke="#F6475F" stroke-width="1.6" stroke-linecap="round"/>
                <path d="M15 16l2 2" stroke="#F6475F" stroke-width="1.6" stroke-linecap="round"/>
            </svg>
        </div>

        <h1>Votre patrimoine immobilier<br><span class="accent">en poche.</span></h1>

        <p>
            La plateforme de référence pour trouver, gérer et valoriser<br>
            votre bien immobilier en Afrique francophone.
        </p>

        <div class="hero-actions">
            <a href="https://keyhome.app" target="_blank" rel="noopener" class="btn btn-primary">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M18 13v6a2 2 0 01-2 2H5a2 2 0 01-2-2V8a2 2 0 012-2h6"/><polyline points="15 3 21 3 21 9"/><line x1="10" y1="14" x2="21" y2="3"/></svg>
                Accéder à la plateforme
            </a>
            @if ($isLocal)
            <a href="/api/documentation" class="btn btn-ghost">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                Documentation API
            </a>
            @endif
            <a href="/api/ping" class="btn btn-ghost">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="22 12 18 12 15 21 9 3 6 12 2 12"/></svg>
                Vérifier le statut
            </a>
        </div>
    </section>

    <div class="divider" role="separator"></div>

    {{-- Feature cards --}}
    <div class="features" role="list">
        <div class="card" role="listitem">
            <div class="card-icon red" aria-hidden="true">🔍</div>
            <h3>Recherche intelligente</h3>
            <p>Filtres avancés, carte interactive, requêtes en langage naturel et alertes personnalisées.</p>
        </div>
        <div class="card" role="listitem">
            <div class="card-icon teal" aria-hidden="true">✓</div>
            <h3>Annonces vérifiées</h3>
            <p>Chaque annonce est modérée par notre équipe. Confiance et transparence pour chaque transaction.</p>
        </div>
        <div class="card" role="listitem">
            <div class="card-icon gold" aria-hidden="true">🏠</div>
            <h3>Visites 3D immersives</h3>
            <p>Explorez les biens en visite virtuelle 360° depuis votre smartphone ou navigateur, où que vous soyez.</p>
        </div>
    </div>

    {{-- Stats --}}
    <div class="stats" role="region" aria-label="Chiffres clés">
        <div class="stat">
            <div class="stat-value">204<span class="unit">+</span></div>
            <div class="stat-label">Endpoints API</div>
        </div>
        <div class="stat">
            <div class="stat-value">XAF<span class="unit">/XOF</span></div>
            <div class="stat-label">Devise CEMAC · UEMOA</div>
        </div>
        <div class="stat">
            <div class="stat-value">99<span class="unit">%</span></div>
            <div class="stat-label">Disponibilité cible</div>
        </div>
    </div>

    {{-- API endpoint pills --}}
    <div class="endpoints">
        <div class="endpoints-header">
            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><polyline points="16 18 22 12 16 6"/><polyline points="8 6 2 12 8 18"/></svg>
            <h2>Quelques ressources exposées</h2>
        </div>
        <div class="endpoints-list">
            <span class="ep"><span class="method get">GET</span>/v1/ads</span>
            <span class="ep"><span class="method get">GET</span>/v1/ads/search</span>
            <span class="ep"><span class="method get">GET</span>/v1/ads/{id}/tour</span>
            <span class="ep"><span class="method post">POST</span>/v1/auth/login</span>
            <span class="ep"><span class="method get">GET</span>/v1/cities</span>
            <span class="ep"><span class="method get">GET</span>/v1/recommendations</span>
            <span class="ep"><span class="method get">GET</span>/v1/ads/{id}/similar</span>
            <span class="ep"><span class="method del">DEL</span>/v1/my/account</span>
        </div>
    </div>

</main>

{{-- ── Footer ──────────────────────────────────────────────────── --}}
<footer>
    <span>&copy; {{ date('Y') }} KeyHome — NéoCraft SAS. Tous droits réservés.</span>
    <nav class="footer-links" aria-label="Liens secondaires">
        <a href="https://keyhome.app" target="_blank" rel="noopener">keyhome.app</a>
        <a href="https://keyhome.app/contact" target="_blank" rel="noopener">Support</a>
        @if ($isLocal)
            <a href="/api/documentation">Swagger UI</a>
        @endif
    </nav>
</footer>

</body>
</html>
