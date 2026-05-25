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
        }

        @keyframes fade-up {
            from { opacity: 0; transform: translateY(22px); }
            to   { opacity: 1; transform: translateY(0); }
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
    </section>
</body>
</html>
