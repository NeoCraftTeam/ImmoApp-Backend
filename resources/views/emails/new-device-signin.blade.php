@extends('emails.layout')

@section('title', 'Nouvelle connexion à votre compte — ' . config('app.name'))

@section('content')

    {{-- Security badge --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 6px;">
        <tr>
            <td style="text-align: center; padding: 8px 0 2px 0;">
                <span style="
                    display: inline-block;
                    background-color: #eff6ff;
                    border: 1px solid #bfdbfe;
                    border-radius: 20px;
                    padding: 5px 14px;
                    font-size: 12px;
                    font-weight: 600;
                    color: #1d4ed8;
                    letter-spacing: 0.3px;
                ">🔐&nbsp; Activité de connexion</span>
            </td>
        </tr>
    </table>

    <h1 style="text-align: center; font-size: 20px; margin: 10px 0 6px 0;">Nouvelle connexion à votre compte</h1>

    @if(!empty($userName))
        <p class="text" style="text-align: center; color: #64748b; margin-bottom: 4px;">
            Bonjour <strong style="color: #1e293b;">{{ $userName }}</strong>,
        </p>
    @endif
    <p class="text" style="text-align: center; color: #64748b; margin-bottom: 24px;">
        Une nouvelle connexion à votre compte <strong style="color: #1e293b;">{{ config('app.name') }}</strong> a été détectée.
        Si vous êtes bien à l'origine de cette action, vous n'avez rien à faire.
    </p>

    {{-- Details card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 24px 0;">
        <tr>
            <td style="
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                border-left: 4px solid #3b82f6;
                border-radius: 8px;
                padding: 0;
                overflow: hidden;
            ">
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td colspan="2" style="
                            padding: 12px 20px 10px 20px;
                            font-size: 10px;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: 1px;
                            color: #1d4ed8;
                            border-bottom: 1px solid #e2e8f0;
                        ">Détails de la connexion</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #64748b; font-size: 13px; border-bottom: 1px solid #f1f5f9;">Date &amp; heure</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; font-weight: 600; color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $sessionCreatedAt }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #64748b; font-size: 13px; border-bottom: 1px solid #f1f5f9;">Appareil</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $deviceType }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #64748b; font-size: 13px; border-bottom: 1px solid #f1f5f9;">Navigateur</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $browserName }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #64748b; font-size: 13px; border-bottom: 1px solid #f1f5f9;">Système</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $operatingSystem }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #64748b; font-size: 13px; border-bottom: 1px solid #f1f5f9;">Adresse IP</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; font-weight: 600; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $ipAddress }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #64748b; font-size: 13px; border-bottom: 1px solid #f1f5f9;">Localisation approximative</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $location }}</td>
                    </tr>
                    @if(!empty($signInMethod))
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #64748b; font-size: 13px;">Méthode</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; color: #0f172a;">{{ $signInMethod }}</td>
                    </tr>
                    @endif
                </table>
            </td>
        </tr>
    </table>

    {{-- Action sections --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 20px;">
        <tr>
            <td style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px 18px;">
                <p style="margin: 0; font-size: 14px; color: #166534;">
                    <strong>✓ C'est bien vous ?</strong> Parfait — votre compte est en sécurité, aucune action requise.
                </p>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 24px;">
        <tr>
            <td style="background-color: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 14px 18px;">
                <p style="margin: 0 0 10px 0; font-size: 14px; color: #9f1239;">
                    <strong>✕ Ce n'est pas vous ?</strong> Révoquez immédiatement cette session.
                </p>
                @if(!empty($revokeSessionUrl))
                    <a href="{{ $revokeSessionUrl }}"
                       style="display: inline-block; background-color: #dc2626; color: #ffffff; text-decoration: none; font-size: 13px; font-weight: 700; padding: 9px 20px; border-radius: 6px;">
                        Révoquer cette session →
                    </a>
                @endif
            </td>
        </tr>
    </table>

    @if(!empty($revokeSessionUrl))
        <p class="fallback">
            Lien de révocation : <a href="{{ $revokeSessionUrl }}" class="link">{{ $revokeSessionUrl }}</a>
        </p>
    @endif

    <p class="text" style="margin-top: 24px; font-size: 12px; color: #94a3b8;">
        Cet e-mail a été généré automatiquement par les systèmes de sécurité de {{ config('app.name') }}.
        @if(!empty($supportEmail))
            Une question ? Contactez <a href="mailto:{{ $supportEmail }}" class="link">{{ $supportEmail }}</a>.
        @endif
    </p>

@endsection
