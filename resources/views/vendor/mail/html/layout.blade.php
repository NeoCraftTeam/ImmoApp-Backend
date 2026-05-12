<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <style>
        body {
            font-family: Arial, Helvetica, sans-serif;
            background-color: #f4f4f7;
            margin: 0;
            padding: 0;
        }
        .email-wrapper {
            width: 100%;
            background-color: #f4f4f7;
            padding: 20px 0;
        }
        .email-content {
            width: 100%;
            max-width: 600px;
            margin: auto;
            background: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        }
        .email-header {
            display: flex;
            align-items: center;
            padding: 20px 30px;
            border-bottom: 1px solid #e0e0e0;
        }
        .email-header img {
            max-height: 50px;
            margin-right: 15px;
        }
        .email-header h1 {
            font-size: 24px;
            margin: 0;
            color: #333333;
        }
        .email-body {
            padding: 30px;
            color: #333333;
            font-size: 16px;
            line-height: 1.6;
        }
        .email-button a {
            background-color: #F6475F;
            color: #ffffff !important;
            padding: 12px 24px;
            border-radius: 6px;
            text-decoration: none;
            font-weight: bold;
            display: inline-block;
        }
        .email-footer {
            text-align: center;
            font-size: 12px;
            color: #999999;
            padding: 20px;
        }

        @media (prefers-color-scheme: dark) {
            body, .email-wrapper {
                background-color: #1a1a2e !important;
            }
            .email-content {
                background-color: #16213e !important;
                box-shadow: 0 4px 6px rgba(0,0,0,0.3) !important;
            }
            .email-header {
                border-bottom-color: #2d3748 !important;
            }
            .email-header h1 {
                color: #f1f5f9 !important;
            }
            .email-body {
                color: #cbd5e1 !important;
            }
            .email-footer {
                color: #64748b !important;
            }
        }
    </style>
</head>
<body>
<div class="email-wrapper">
    <div class="email-content">

        {{-- Header avec logo à gauche et titre --}}
        <div class="email-header">
            <img src="data:image/png;base64,{{ base64_encode(file_get_contents(public_path('images/logo.png'))) }}"
                 alt="KeyHome logo" style="max-height:50px;">
            <h1>KeyHome</h1>
        </div>

        {{-- Contenu principal --}}
        <div class="email-body">
            {{ Illuminate\Mail\Markdown::parse($slot) }}
        </div>

        {{-- Footer --}}
        <div class="email-footer">
            &copy; {{ date('Y') }} NeoCraft. Tous droits réservés.
            <br>
            <a href="https://neocraft.dev" style="color:#F6475F; text-decoration:none;">Visitez notre site</a>
            @isset($unsubscribeUrl)
                <br>
                <a href="{{ $unsubscribeUrl }}" style="color:#999999; text-decoration:none;">Se désabonner</a>
                @isset($preferencesUrl)
                    &nbsp;|&nbsp;
                    <a href="{{ $preferencesUrl }}" style="color:#999999; text-decoration:none;">Gérer mes préférences</a>
                @endisset
            @endisset
        </div>

    </div>
</div>
</body>
</html>
