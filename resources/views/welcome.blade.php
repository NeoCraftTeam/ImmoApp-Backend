<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>KeyHome — Votre patrimoine immobilier en poche</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;700;800&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            min-height: 100dvh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 2rem;
            background: #E8304A;
            background-image:
                radial-gradient(ellipse 80% 60% at 50% 0%,   rgba(255,255,255,.12) 0%, transparent 65%),
                radial-gradient(ellipse 50% 40% at 80% 100%, rgba(0,0,0,.15)        0%, transparent 60%);
            overflow: hidden;
        }

        /* Subtle dot texture */
        body::before {
            content: '';
            position: fixed;
            inset: 0;
            background-image: radial-gradient(rgba(255,255,255,.07) 1px, transparent 1px);
            background-size: 28px 28px;
            pointer-events: none;
        }

        .hero {
            position: relative;
            text-align: center;
            max-width: 620px;
            animation: fade-up .5s ease both;
        }

        /* Brand mark */
        .brand {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: .55rem;
            margin-bottom: 2.25rem;
        }

        .brand-name {
            font-size: .9rem;
            font-weight: 700;
            letter-spacing: .18em;
            text-transform: uppercase;
            color: rgba(255,255,255,.7);
        }

        /* Divider line between brand and title */
        .brand::after {
            display: none;
        }

        h1 {
            font-size: clamp(2.8rem, 7vw, 4.5rem);
            font-weight: 800;
            color: #fff;
            letter-spacing: -.04em;
            line-height: 1.05;
            margin-bottom: 1.375rem;
        }

        p {
            font-size: clamp(.95rem, 1.8vw, 1.1rem);
            color: rgba(255,255,255,.7);
            line-height: 1.7;
            max-width: 440px;
            margin: 0 auto;
        }

        /* Bottom wordmark */
        .wordmark {
            position: fixed;
            bottom: 1.75rem;
            left: 50%;
            transform: translateX(-50%);
            font-size: .72rem;
            font-weight: 500;
            letter-spacing: .06em;
            color: rgba(255,255,255,.35);
            white-space: nowrap;
            animation: fade-up .5s .3s ease both;
        }

        @keyframes fade-up {
            from { opacity: 0; transform: translateY(18px); }
            to   { opacity: 1; transform: translateY(0); }
        }
    </style>
</head>
<body>

    <section class="hero">
        <div class="brand">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                <circle cx="8" cy="8" r="4.5" stroke="rgba(255,255,255,.75)" stroke-width="2"/>
                <path d="M11.5 11.5L20 20" stroke="rgba(255,255,255,.75)" stroke-width="2" stroke-linecap="round"/>
                <path d="M17 18l2 2" stroke="rgba(255,255,255,.75)" stroke-width="2" stroke-linecap="round"/>
                <path d="M15 16l2 2" stroke="rgba(255,255,255,.75)" stroke-width="2" stroke-linecap="round"/>
            </svg>
            <span class="brand-name">KeyHome</span>
        </div>

        <h1>Votre patrimoine immobilier en poche.</h1>

        <p>La plateforme de référence pour trouver, gérer et valoriser votre bien immobilier</p>
    </section>

    <span class="wordmark">keyhome.app</span>

</body>
</html>
