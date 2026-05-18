@extends('emails.layout')

@section('title', 'Connexion depuis un nouvel emplacement — ' . config('app.name'))

@section('content')

    {{-- Security badge --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 6px;">
        <tr>
            <td style="text-align: center; padding: 8px 0 2px 0;">
                <span style="
                    display: inline-block;
                    background-color: #fffbeb;
                    border: 1px solid #fde68a;
                    border-radius: 20px;
                    padding: 5px 14px;
                    font-size: 12px;
                    font-weight: 600;
                    color: #92400e;
                    letter-spacing: 0.3px;
                ">⚠&nbsp; Alerte de sécurité</span>
            </td>
        </tr>
    </table>

    <h1 style="text-align: center; font-size: 20px; margin: 10px 0 6px 0;">Connexion depuis un nouvel emplacement</h1>

    <p class="text" style="text-align: center; color: #64748b; margin-bottom: 24px;">
        Bonjour <strong style="color: #1e293b;">{{ $userName }}</strong>, une nouvelle connexion à votre compte
        <strong style="color: #1e293b;">{{ config('app.name') }}</strong> vient d'être détectée depuis une localisation différente de votre habitude.
    </p>

    {{-- Details card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 24px 0;">
        <tr>
            <td style="
                background-color: #fffbeb;
                border: 1px solid #fde68a;
                border-left: 4px solid #f59e0b;
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
                            color: #92400e;
                            border-bottom: 1px solid #fde68a;
                        ">Détails de la connexion</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #78716c; font-size: 13px; border-bottom: 1px solid #fef3c7;">Date &amp; heure</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; font-weight: 600; color: #1c1917; border-bottom: 1px solid #fef3c7;">{{ $loginAt }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #78716c; font-size: 13px; border-bottom: 1px solid #fef3c7;">Appareil</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; color: #1c1917; border-bottom: 1px solid #fef3c7;">{{ $device }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #78716c; font-size: 13px; border-bottom: 1px solid #fef3c7;">Navigateur</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; color: #1c1917; border-bottom: 1px solid #fef3c7;">{{ $browser }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #78716c; font-size: 13px; border-bottom: 1px solid #fef3c7;">Système</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; color: #1c1917; border-bottom: 1px solid #fef3c7;">{{ $operatingSystem }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #78716c; font-size: 13px; border-bottom: 1px solid #fef3c7;">Adresse IP</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; font-weight: 600; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; color: #1c1917; border-bottom: 1px solid #fef3c7;">{{ $ipAddress }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 38%; color: #78716c; font-size: 13px;">Localisation</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; font-weight: 600; color: #1c1917;">{{ $city !== 'Inconnue' ? $city . ', ' : '' }}{{ $country !== 'Inconnu' ? $country : 'Inconnue' }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Action sections --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 20px;">
        <tr>
            <td style="background-color: #f0fdf4; border: 1px solid #bbf7d0; border-radius: 8px; padding: 14px 18px;">
                <p style="margin: 0; font-size: 14px; color: #166534;">
                    <strong>✓ C'est bien vous ?</strong> Vous n'avez rien à faire — votre compte est en sécurité.
                </p>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 24px;">
        <tr>
            <td style="background-color: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 14px 18px;">
                <p style="margin: 0 0 10px 0; font-size: 14px; color: #9f1239;">
                    <strong>✕ Ce n'est pas vous ?</strong> Sécurisez votre compte immédiatement.
                </p>
                @if(!empty($secureAccountUrl))
                    <a href="{{ $secureAccountUrl }}"
                       style="display: inline-block; background-color: #dc2626; color: #ffffff; text-decoration: none; font-size: 13px; font-weight: 700; padding: 9px 20px; border-radius: 6px;">
                        Sécuriser mon compte →
                    </a>
                @endif
            </td>
        </tr>
    </table>

    @if(!empty($secureAccountUrl))
        <p class="fallback">
            Lien de sécurisation : <a href="{{ $secureAccountUrl }}" class="link">{{ $secureAccountUrl }}</a>
        </p>
    @endif

    <p class="text" style="margin-top: 24px; font-size: 12px; color: #94a3b8;">
        Cet e-mail a été généré automatiquement par les systèmes de sécurité de {{ config('app.name') }}.
        @if(!empty($supportEmail))
            Une question ? Contactez <a href="mailto:{{ $supportEmail }}" class="link">{{ $supportEmail }}</a>.
        @endif
    </p>

@endsection
