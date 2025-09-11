<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
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
        </div>

    </div>
</div>
</body>
</html>
