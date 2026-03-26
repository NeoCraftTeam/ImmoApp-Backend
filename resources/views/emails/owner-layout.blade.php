<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="color-scheme" content="light dark">
    <meta name="supported-color-schemes" content="light dark">
    <title>@yield('title', config('app.name'))</title>
    <style>
        * {
            box-sizing: border-box;
            margin: 0;
            padding: 0;
        }

        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
            background-color: #f0fdfa;
            color: #1e293b;
            -webkit-font-smoothing: antialiased;
            font-size: 15px;
            line-height: 1.6;
        }

        .wrapper {
            width: 100%;
            background-color: #f0fdfa;
            padding: 40px 16px;
        }

        .container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 12px;
            border: 1px solid #ccfbf1;
            overflow: hidden;
        }

        /* Teal accent bar */
        .accent-bar {
            height: 4px;
            background: #0d9488;
        }

        /* Header */
        .header {
            padding: 24px 32px;
            background-color: #ffffff;
            border-bottom: 1px solid #f0fdfa;
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

        /* CTA Button — teal */
        .btn-wrapper {
            margin: 28px 0 0 0;
        }

        .btn {
            display: inline-block;
            background-color: #0d9488;
            color: #ffffff !important;
            font-size: 15px;
            font-weight: 600;
            text-decoration: none;
            padding: 14px 28px;
            border-radius: 8px;
            line-height: 1;
        }

        .link {
            color: #0d9488;
            text-decoration: none;
        }

        .fallback {
            margin: 14px 0 0 0;
            font-size: 13px;
            color: #94a3b8;
        }

        /* OTP code box */
        .otp-box {
            margin: 28px 0 0 0;
            padding: 28px 32px;
            background-color: #f0fdfa;
            border: 2px dashed #0d9488;
            border-radius: 12px;
            text-align: center;
        }

        .otp-code {
            font-size: 44px;
            font-weight: 800;
            letter-spacing: 10px;
            color: #0d9488;
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

        /* Badge / info box */
        .info-box {
            margin: 20px 0 0 0;
            padding: 16px 20px;
            background-color: #f0fdfa;
            border: 1px solid #99f6e4;
            border-radius: 8px;
        }

        /* Footer */
        .footer {
            padding: 24px 32px;
            background-color: #f0fdfa;
            border-top: 1px solid #ccfbf1;
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

        /* Dark mode */
        @media (prefers-color-scheme: dark) {
            body, .wrapper {
                background-color: #042f2e !important;
                color: #e2e8f0 !important;
            }
            .container {
                background-color: #0f3d3a !important;
                border-color: #115e59 !important;
            }
            .header {
                background-color: #0f3d3a !important;
                border-bottom-color: #115e59 !important;
            }
            .block {
                background-color: #0f3d3a !important;
            }
            h1 {
                color: #f1f5f9 !important;
            }
            .text, p {
                color: #cbd5e1 !important;
            }
            .otp-box {
                background-color: #042f2e !important;
                border-color: #0d9488 !important;
            }
            .info-box {
                background-color: #042f2e !important;
                border-color: #115e59 !important;
            }
            .otp-label {
                color: #94a3b8 !important;
            }
            .footer {
                background-color: #021f1e !important;
                border-top-color: #115e59 !important;
                color: #64748b !important;
            }
            .footer a {
                color: #94a3b8 !important;
            }
            .link {
                color: #2dd4bf !important;
            }
            .fallback {
                color: #64748b !important;
            }
        }

        [data-ogsc] .wrapper,
        [data-ogsc] body {
            background-color: #042f2e !important;
        }
        [data-ogsc] .container {
            background-color: #0f3d3a !important;
        }
    </style>
</head>

<body>
    <div class="wrapper">
        <div class="container">

            <div class="accent-bar"></div>

            <div class="header">
                <a href="{{ $emailFrontendUrl ?? config('app.frontend_url', config('app.url')) }}" style="display: inline-block;">
                    @if(!empty($emailLogoUrl ?? ''))
                        <img src="{{ $emailLogoUrl }}"
                            alt="{{ config('app.name') }}"
                            class="logo-img"
                            style="max-height: 48px; height: auto; width: auto;" />
                    @elseif(!empty($emailLogoBase64))
                        <img src="data:image/png;base64,{{ $emailLogoBase64 }}"
                            alt="{{ config('app.name') }}"
                            class="logo-img"
                            style="max-height: 48px; height: auto; width: auto;" />
                    @else
                        <span style="font-size:20px;font-weight:700;color:#0d9488;">{{ config('app.name') }}</span>
                    @endif
                </a>
            </div>

            <div class="block">
                @yield('content')
            </div>

            <div class="footer">
                <p>&copy; {{ date('Y') }} {{ config('app.name') }}. {{ __('emails.layout.rights') }}</p>
                <p style="margin-top: 6px;">
                    {{ __('emails.layout.receiving_reason', ['app' => config('app.name')]) }}
                </p>
                @isset($unsubscribeUrl)
                    <p style="margin-top: 8px;">
                        <a href="{{ $unsubscribeUrl }}">{{ __('emails.layout.unsubscribe') }}</a>
                        @isset($preferencesUrl)
                            &nbsp;|&nbsp;
                            <a href="{{ $preferencesUrl }}">{{ __('emails.layout.manage_preferences') }}</a>
                        @endisset
                    </p>
                @endisset
            </div>

        </div>
    </div>
</body>

</html>
