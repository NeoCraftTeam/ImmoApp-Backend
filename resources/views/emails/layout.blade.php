<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>@yield('title', config('app.name'))</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background-color: #f1f5f9;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
            font-size: 15px;
            line-height: 1.6;
        }

        .wrapper {
            width: 100%;
            background-color: #f1f5f9;
            padding: 40px 16px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #e2e8f0;
            overflow: hidden;
        }

        /* Accent bar */
        .accent-bar {
            height: 4px;
            background: linear-gradient(90deg, #F6475F 0%, #ff6b6b 100%);
        }

        /* Header */
        .header {
            padding: 24px 32px;
            background-color: #ffffff;
            border-bottom: 1px solid #f1f5f9;
        }

        .logo-img {
            height: 36px;
            width: auto;
            display: block;
        }

        /* Main block */
        .block {
            padding: 40px 32px 48px 32px;
        }

        h1 {
            font-size: 22px;
            font-weight: 700;
            color: #0f172a;
            line-height: 1.3;
        }

        .text {
            margin: 14px 0 0 0;
            font-size: 15px;
            color: #475569;
            line-height: 1.7;
        }

        /* CTA Button */
        .btn-wrapper {
            margin: 28px 0 0 0;
        }

        .btn {
            display: inline-block;
            background-color: #F6475F;
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            line-height: 1;
        }

        .link {
            color: #F6475F;
            text-decoration: none;
        }

        .fallback {
            margin: 14px 0 0 0;
            font-size: 13px;
            color: #94a3b8;
        }

        /* OTP code box — utilisé par verify-email, reset-password, pricing-verification */
        .otp-box {
            margin: 28px 0 0 0;
            padding: 28px 32px;
            background-color: #f8fafc;
            border: 2px dashed #F6475F;
            border-radius: 12px;
            text-align: center;
        }

        .otp-code {
            font-size: 44px;
            font-weight: 800;
            letter-spacing: 10px;
            color: #F6475F;
            font-family: 'Courier New', Courier, monospace;
            line-height: 1;
        }

        .otp-label {
            margin-top: 10px;
            font-size: 12px;
            color: #94a3b8;
            font-weight: 500;
            letter-spacing: 0.5px;
            text-transform: uppercase;
        }

        /* Footer */
        .footer {
            padding: 24px 32px;
            background-color: #f8fafc;
            border-top: 1px solid #e2e8f0;
            font-size: 12px;
            color: #94a3b8;
            text-align: center;
        }

        .footer a {
            color: #64748b;
            text-decoration: none;
        }

        @media only screen and (max-width: 600px) {
            .wrapper {
                padding: 16px 8px;
            }

            .header {
                padding: 20px;
            }

            .block {
                padding: 28px 20px 36px 20px;
            }

            .footer {
                padding: 20px;
            }

            h1 {
                font-size: 20px;
            }

            .otp-code {
                font-size: 36px;
                letter-spacing: 6px;
            }
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="container">

            <div class="accent-bar"></div>

            <div class="header">
                <a href="{{ config('app.url') }}" style="display: inline-block;">
                    @if(!empty($emailLogoBase64))
                        <img src="data:image/png;base64,{{ $emailLogoBase64 }}"
                            alt="{{ config('app.name') }}"
                            class="logo-img" />
                    @else
                        <span style="font-size:20px;font-weight:700;color:#F6475F;">{{ config('app.name') }}</span>
                    @endif
                </a>
            </div>

            <div class="block">
                @yield('content')
            </div>

            <div class="footer">
                <p>© {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.</p>
                <p style="margin-top: 6px;">
                    Vous recevez cet email car vous êtes inscrit sur
                    <a href="{{ config('app.url') }}">{{ config('app.name') }}</a>.
                </p>
            </div>

        </div>
    </div>
</body>

</html>
