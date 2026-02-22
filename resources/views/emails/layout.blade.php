<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', config('app.name'))</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background-color: #ffffff;
            color: #000000;
            -webkit-font-smoothing: antialiased;
            font-size: 14px;
            line-height: 1.6;
        }
        .wrapper {
            width: 100%;
            background-color: #ffffff;
            padding: 48px 32px;
        }
        .container {
            max-width: 600px;
            margin: 0 auto;
        }
        /* Header */
        .header {
            padding: 20px 32px;
        }
        .logo-img {
            height: 40px;
            width: auto;
            display: block;
        }
        /* Main block */
        .block {
            background-color: #ffffff;
            padding: 32px 32px 48px 32px;
            margin-top: 16px;
        }
        h1 {
            font-size: 24px;
            font-weight: 700;
            color: #000000;
            line-height: 1.3;
        }
        .text {
            margin: 16px 0 0 0;
            font-size: 14px;
            color: #000000;
        }
        /* CTA Button */
        .btn-wrapper {
            margin: 32px 0 0 0;
        }
        .btn {
            display: inline-block;
            background-color: #F6475F;
            color: #ffffff !important;
            font-size: 14px;
            font-weight: 600;
            text-decoration: none;
            padding: 15px 24px;
            border-radius: 8px;
            line-height: 1;
        }
        .link {
            color: #F6475F;
            text-decoration: none;
        }
        .fallback {
            margin: 16px 0 0 0;
            font-size: 14px;
            color: #000000;
        }
        /* Footer */
        .footer {
            margin-top: 40px;
            padding-top: 24px;
            border-top: 1px solid #e5e7eb;
            font-size: 12px;
            color: #6b7280;
            text-align: center;
        }
        @media only screen and (max-width: 600px) {
            .wrapper { padding: 24px 16px; }
            .header { padding: 16px; }
            .block { padding: 24px 16px 32px 16px; }
        }
    </style>
</head>
<body>
    <div class="wrapper">
        <div class="container">

            <div class="header">
                <a href="{{ config('app.url') }}">
                    <img src="{{ asset('images/keyhomelogo_transparent.png') }}" alt="KeyHome" class="logo-img" />
                </a>
            </div>

            <div class="block">
                @yield('content')
            </div>

            <div class="footer">
                <p>© {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.</p>
                <p style="margin-top: 8px;">
                    Vous recevez cet email car vous êtes inscrit sur {{ config('app.name') }}.
                </p>
            </div>

        </div>
    </div>
</body>
</html>
