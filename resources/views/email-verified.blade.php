<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Email vérifié — KeyHome</title>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: 'Inter', sans-serif;
            background: linear-gradient(135deg, #0f0c29 0%, #302b63 40%, #8b1a2e 75%, #F6475F 100%);
            color: #1e293b;
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 100vh;
            overflow: hidden;
            position: relative;
        }

        .particle {
            position: fixed;
            border-radius: 50%;
            opacity: 0;
            animation: float 3s ease-in-out infinite;
        }
        @keyframes float {
            0%   { opacity: 0; transform: translateY(0) rotate(0deg); }
            20%  { opacity: 0.8; }
            80%  { opacity: 0.6; }
            100% { opacity: 0; transform: translateY(-120px) rotate(720deg); }
        }

        .container {
            text-align: center;
            background: white;
            padding: 48px 36px;
            border-radius: 24px;
            box-shadow: 0 25px 50px -12px rgba(0, 0, 0, 0.25);
            max-width: 440px;
            width: 90%;
            position: relative;
            z-index: 1;
            animation: cardIn 0.6s cubic-bezier(0.22, 1, 0.36, 1) both;
        }
        @keyframes cardIn {
            from { opacity: 0; transform: translateY(30px) scale(0.96); }
            to   { opacity: 1; transform: translateY(0) scale(1); }
        }

        .logo-row {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            margin-bottom: 32px;
        }

        .logo-row img { width: 40px; height: 40px; }

        .logo-text {
            font-size: 22px;
            font-weight: 800;
            color: #F6475F;
            letter-spacing: -0.5px;
        }

        .icon-circle {
            width: 88px;
            height: 88px;
            background: linear-gradient(135deg, #dcfce7, #bbf7d0);
            border-radius: 50%;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 28px;
            animation: bounceIn 0.7s cubic-bezier(0.22, 1, 0.36, 1) 0.3s both;
        }
        @keyframes bounceIn {
            0%   { opacity: 0; transform: scale(0.3); }
            60%  { transform: scale(1.1); }
            80%  { transform: scale(0.95); }
            100% { opacity: 1; transform: scale(1); }
        }

        .icon-circle svg { width: 44px; height: 44px; color: #0D9488; }

        h1 {
            font-size: 26px;
            font-weight: 800;
            margin-bottom: 12px;
            color: #0f172a;
            letter-spacing: -0.025em;
            animation: slideUp 0.5s ease 0.5s both;
        }

        p {
            color: #64748b;
            line-height: 1.6;
            margin-bottom: 28px;
            font-size: 15px;
            animation: slideUp 0.5s ease 0.6s both;
        }

        @keyframes slideUp {
            from { opacity: 0; transform: translateY(12px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .btn {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: linear-gradient(to right, #F6475F, #D93A50);
            color: #ffffff !important;
            padding: 14px 36px;
            border-radius: 14px;
            text-decoration: none;
            font-weight: 700;
            font-size: 15px;
            border: none;
            cursor: pointer;
            transition: all 0.25s ease;
            box-shadow: 0 8px 20px -4px rgba(246, 71, 95, 0.35);
            animation: slideUp 0.5s ease 0.7s both;
        }

        .btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 12px 28px -4px rgba(246, 71, 95, 0.45);
        }

        .btn svg { width: 18px; height: 18px; }

        .redirect-hint {
            margin-top: 20px;
            font-size: 13px;
            color: #94a3b8;
            animation: slideUp 0.5s ease 0.8s both;
        }

        .redirect-hint span { font-weight: 600; color: #F6475F; }

        @media (prefers-reduced-motion: reduce) {
            *, *::before, *::after {
                animation-duration: 0.01ms !important;
                animation-delay: 0ms !important;
            }
            .particle { display: none; }
        }
    </style>
</head>
<body>
    @for ($i = 0; $i < 14; $i++)
        <div class="particle" style="
            width: {{ rand(5, 12) }}px;
            height: {{ rand(5, 12) }}px;
            background: {{ ['#F6475F','#ffffff','#FFD700','#ff9f43','#48dbfb','#a29bfe','#55efc4','#fd79a8'][$i % 8] }};
            left: {{ rand(5, 95) }}%;
            bottom: {{ rand(-5, 30) }}%;
            animation-delay: {{ ($i * 0.2) + 0.5 }}s;
            animation-duration: {{ 2.5 + ($i % 4) * 0.4 }}s;
        "></div>
    @endfor

    <div class="container">
        <div class="logo-row">
            <img src="{{ asset('images/logo.png') }}" alt="KeyHome">
            <span class="logo-text">KeyHome</span>
        </div>

        <div class="icon-circle">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
            </svg>
        </div>

        <h1>Email vérifié avec succès !</h1>

        <p>Merci <strong>{{ $user->firstname }}</strong>, votre compte est maintenant sécurisé et actif.</p>

        @php
            $redirectUrl = config('app.email_verify_callback', 'http://localhost:8000');
        @endphp

        <a href="{{ $redirectUrl }}" class="btn">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
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
            var url = @json($redirectUrl);
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
