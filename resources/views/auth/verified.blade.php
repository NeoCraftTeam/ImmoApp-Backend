<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Compte Activé — KeyHome</title>
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

            .container {
                background: #111827;
                border-color: #1f2937;
            }

            .container h1 { color: #f9fafb; }

            .container p { color: #94a3b8; }

            .icon-circle {
                background: #064e3b;
            }

            .icon-circle svg { color: #5eead4; }
        }

        .container {
            max-width: 440px;
            width: 100%;
            background: #ffffff;
            padding: 3rem 2.25rem;
            border-radius: 1rem;
            border: 1px solid #e5e7eb;
            box-shadow: 0 10px 15px -3px rgb(0 0 0 / 0.08), 0 4px 6px -4px rgb(0 0 0 / 0.06);
            text-align: center;
        }

        .logo-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 2rem;
        }

        .logo-row img {
            width: 40px;
            height: 40px;
        }

        .logo-text {
            font-size: 22px;
            font-weight: 800;
            color: #f6475f;
            letter-spacing: -0.03em;
        }

        .icon-circle {
            width: 88px;
            height: 88px;
            background: #d1fae5;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 1.75rem;
        }

        .icon-circle svg {
            width: 44px;
            height: 44px;
            color: #0d9488;
        }

        h1 {
            font-size: 1.5rem;
            font-weight: 800;
            margin-bottom: 0.75rem;
            letter-spacing: -0.025em;
            color: #0f172a;
        }

        p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 2rem;
            font-size: 15px;
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #e11d48;
            color: #ffffff;
            padding: 14px 36px;
            border-radius: 0.75rem;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            transition: background-color 0.2s ease, box-shadow 0.2s ease;
            box-shadow: 0 4px 14px -3px rgb(225 29 72 / 0.45);
        }

        .btn:hover {
            background: #be123c;
            box-shadow: 0 6px 18px -3px rgb(225 29 72 / 0.5);
        }

        .btn:focus-visible {
            outline: 2px solid #be123c;
            outline-offset: 3px;
        }

        .btn svg {
            width: 18px;
            height: 18px;
        }

        .redirect-hint {
            margin-top: 1.25rem;
            font-size: 13px;
            color: #64748b;
        }

        .redirect-hint span {
            font-weight: 600;
            color: #e11d48;
        }

        @media (prefers-reduced-motion: reduce) {
            .btn {
                transition: none;
            }
        }
    </style>
</head>

<body>
    <div class="container">
        <div class="logo-row">
            <img src="{{ asset('images/logo.png') }}" alt="KeyHome">
            <span class="logo-text">KeyHome</span>
        </div>

        <div class="icon-circle" aria-hidden="true">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>

        <h1>Compte activé avec succès !</h1>
        <p>Merci d'avoir vérifié votre adresse email.<br>Votre compte est maintenant pleinement opérationnel.</p>
        @if($isAdmin ?? false)
        <p style="margin-top: -16px; margin-bottom: 24px; font-size: 14px;">Connectez-vous puis <strong>changez votre mot de passe</strong> et <strong>configurez l'authentification à deux facteurs (2FA)</strong>.</p>
        @endif

        <a href="{{ $loginUrl }}" class="btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0 0 13.5 3h-6a2.25 2.25 0 0 0-2.25 2.25v13.5A2.25 2.25 0 0 0 7.5 21h6a2.25 2.25 0 0 0 2.25-2.25V15m3-3h-9m0 0 3-3m-3 3 3 3" />
            </svg>
            Accéder à mon espace
        </a>

        <p class="redirect-hint">
            Redirection automatique dans <span id="countdown">5</span>s…
        </p>
    </div>

    <script>
        (function() {
            var seconds = 5;
            var el = document.getElementById('countdown');
            var url = @json($loginUrl);
            var timer = setInterval(function() {
                seconds--;
                if (el) el.textContent = seconds;
                if (seconds <= 0) {
                    clearInterval(timer);
                    window.location.href = url;
                }
            }, 1000);
        })();
    </script>
</body>

</html>
