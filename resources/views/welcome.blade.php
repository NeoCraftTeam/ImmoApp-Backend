@php $isLocal = app()->environment('local'); @endphp
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>KeyHome — Votre patrimoine immobilier en poche</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:ital,wght@0,400;0,700;0,800;1,400&display=swap" rel="stylesheet">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'Inter', system-ui, sans-serif;
            background: #F6475F;
            min-height: 100dvh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2rem;
        }

        .hero {
            text-align: center;
            max-width: 680px;
            animation: fade-up .55s ease both;
        }

        h1 {
            font-size: clamp(2.5rem, 6vw, 4rem);
            font-weight: 800;
            color: #fff;
            letter-spacing: -.04em;
            line-height: 1.08;
            margin-bottom: 1.25rem;
        }

        p {
            font-size: clamp(1rem, 2vw, 1.15rem);
            color: rgba(255,255,255,.78);
            line-height: 1.65;
            margin-bottom: 2.5rem;
        }

        .actions {
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
            padding: .75rem 1.6rem;
            font-size: .9rem;
            font-weight: 600;
            text-decoration: none;
            transition: transform .18s, opacity .18s;
            cursor: pointer;
        }

        .btn:hover { transform: translateY(-2px); opacity: .88; }

        .btn-white {
            background: #fff;
            color: #F6475F;
        }

        .btn-outline {
            background: rgba(255,255,255,.15);
            color: #fff;
            border: 1px solid rgba(255,255,255,.35);
            backdrop-filter: blur(4px);
        }

        @keyframes fade-up {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        @media (max-width: 480px) {
            .actions { flex-direction: column; align-items: center; }
        }
    </style>
</head>
<body>
    <section class="hero">
        <h1>Votre patrimoine immobilier<br>en poche.</h1>

        <p>
            La plateforme de référence pour trouver, gérer et valoriser<br>
            votre bien immobilier en Afrique francophone.
        </p>

        <div class="actions">
            <a href="https://keyhome.app" target="_blank" rel="noopener" class="btn btn-white">
                Accéder à la plateforme
            </a>
            @if ($isLocal)
            <a href="/api/documentation" class="btn btn-outline">
                Documentation API
            </a>
            @endif
            <a href="/api/ping" class="btn btn-outline">
                Vérifier le statut
            </a>
        </div>
    </section>
</body>
</html>
