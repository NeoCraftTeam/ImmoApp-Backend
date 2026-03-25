<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Erreur de vérification — KeyHome</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=instrument-sans:400,600,700,800" rel="stylesheet" />
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Instrument Sans', ui-sans-serif, system-ui, sans-serif;
            background: #f3f4f6;
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            padding: 1.5rem;
        }

        @media (prefers-color-scheme: dark) {
            body {
                background: #030712;
                color: #f1f5f9;
            }

            .card {
                background: #111827;
                border-color: #1f2937;
            }

            .card h1 { color: #f9fafb; }

            .card p { color: #94a3b8; }

            .icon-circle {
                background: #450a0a;
            }

            .icon-circle svg { color: #fca5a5; }
        }

        .card {
            text-align: center;
            background: #ffffff;
            padding: 3rem 2.25rem;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.06);
            max-width: 450px;
            width: 100%;
        }

        .logo {
            font-size: 22px;
            font-weight: 800;
            color: #e11d48;
            letter-spacing: -0.03em;
            margin-bottom: 2rem;
        }

        .icon-circle {
            width: 88px;
            height: 88px;
            background: #ffe4e6;
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.5rem;
        }

        .icon-circle svg {
            width: 44px;
            height: 44px;
            color: #e11d48;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 1rem;
            color: #0f172a;
            letter-spacing: -0.025em;
        }

        p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 1.75rem;
            font-size: 15px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            width: 100%;
            box-sizing: border-box;
            background: #e11d48;
            color: #ffffff !important;
            padding: 14px 32px;
            border-radius: 0.75rem;
            text-decoration: none;
            font-weight: 700;
            font-size: 16px;
            border: none;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn:hover {
            background: #be123c;
        }

        .btn:focus-visible {
            outline: 2px solid #be123c;
            outline-offset: 3px;
        }

        @media (prefers-reduced-motion: reduce) {
            .btn {
                transition: none;
            }
        }
    </style>
</head>
<body>
    <div class="card" role="alert">
        <div class="logo">KeyHome</div>

        <div class="icon-circle" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
            </svg>
        </div>

        <h1>Une erreur est survenue</h1>

        <p>{{ $message ?? 'Le lien de vérification est invalide ou a expiré.' }}</p>

        <a href="{{ config('app.email_verify_callback', 'http://localhost:3000') }}" class="btn">
            Retourner à l'accueil
        </a>
    </div>
</body>
</html>
