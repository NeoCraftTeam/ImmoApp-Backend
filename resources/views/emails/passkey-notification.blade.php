{{-- Passkey added / removed notification — extends owner-layout (teal) or layout (primary) --}}
@extends($emailLayout ?? 'emails.layout')

@section('title', 'Passkey ' . $actionLabel . ' — ' . config('app.name'))

@section('content')

    {{-- Shield icon + heading --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
        <tr>
            <td align="center" style="padding-bottom: 24px;">
                <div style="
                    display: inline-block;
                    width: 64px;
                    height: 64px;
                    border-radius: 50%;
                    background-color: {{ $isOwner ? '#f0fdfa' : '#fff1f2' }};
                    text-align: center;
                    line-height: 64px;
                ">
                    @if($action === 'added')
                        {{-- Fingerprint / check icon --}}
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                             stroke="{{ $isOwner ? '#0d9488' : '#F6475F' }}" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" style="vertical-align: middle;">
                            <path d="M12 10v4"/>
                            <path d="M7.5 8a5 5 0 0 1 9 0"/>
                            <path d="M5 12a7 7 0 0 1 14 0"/>
                            <path d="M3 14a9 9 0 0 1 .5-3"/>
                            <path d="M20.5 11a9 9 0 0 1 .5 3"/>
                            <path d="M12 14a1 1 0 0 0 1 1h0a1 1 0 0 0 1-1v-1a1 1 0 0 0-1-1h0a1 1 0 0 0-1 1"/>
                        </svg>
                    @else
                        {{-- Shield alert icon --}}
                        <svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none"
                             stroke="{{ $isOwner ? '#0d9488' : '#F6475F' }}" stroke-width="2" stroke-linecap="round"
                             stroke-linejoin="round" style="vertical-align: middle;">
                            <path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/>
                            <line x1="12" y1="8" x2="12" y2="12"/>
                            <line x1="12" y1="16" x2="12.01" y2="16"/>
                        </svg>
                    @endif
                </div>
            </td>
        </tr>
    </table>

    <h1 style="text-align: center; margin-bottom: 8px;">
        Passkey {{ $actionLabel }}
    </h1>

    <p class="text" style="text-align: center; margin-top: 8px;">
        Bonjour <strong>{{ $userName }}</strong>, une passkey {{ $actionVerb }}.
    </p>

    {{-- Details card --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
           style="margin-top: 28px; border-radius: 12px; overflow: hidden;">
        <tr>
            <td style="
                background-color: {{ $isOwner ? '#f0fdfa' : '#fef2f2' }};
                border: 1px solid {{ $isOwner ? '#ccfbf1' : '#fecdd3' }};
                border-radius: 12px;
                padding: 24px;
            ">
                <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%">
                    {{-- Device --}}
                    <tr>
                        <td style="padding-bottom: 16px;">
                            <span style="
                                display: inline-block;
                                font-size: 11px;
                                font-weight: 600;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                color: #94a3b8;
                                margin-bottom: 4px;
                            ">Appareil</span>
                            <br>
                            <span style="
                                font-size: 15px;
                                font-weight: 600;
                                color: #1e293b;
                            ">{{ $deviceName }}</span>
                        </td>
                    </tr>
                    {{-- Separator --}}
                    <tr>
                        <td style="padding-bottom: 16px;">
                            <div style="height: 1px; background-color: {{ $isOwner ? '#99f6e4' : '#fecdd3' }};"></div>
                        </td>
                    </tr>
                    {{-- Date --}}
                    <tr>
                        <td style="padding-bottom: 16px;">
                            <span style="
                                display: inline-block;
                                font-size: 11px;
                                font-weight: 600;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                color: #94a3b8;
                                margin-bottom: 4px;
                            ">Date et heure</span>
                            <br>
                            <span style="font-size: 14px; color: #475569;">{{ $timestamp }}</span>
                        </td>
                    </tr>
                    {{-- IP --}}
                    <tr>
                        <td style="padding-bottom: 16px;">
                            <span style="
                                display: inline-block;
                                font-size: 11px;
                                font-weight: 600;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                color: #94a3b8;
                                margin-bottom: 4px;
                            ">Adresse IP</span>
                            <br>
                            <span style="font-size: 14px; color: #475569;">{{ $ipAddress }}</span>
                        </td>
                    </tr>
                    {{-- User-Agent --}}
                    <tr>
                        <td>
                            <span style="
                                display: inline-block;
                                font-size: 11px;
                                font-weight: 600;
                                text-transform: uppercase;
                                letter-spacing: 0.5px;
                                color: #94a3b8;
                                margin-bottom: 4px;
                            ">Navigateur</span>
                            <br>
                            <span style="font-size: 13px; color: #64748b; word-break: break-all;">{{ Str::limit($userAgent, 80) }}</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Action badge --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%" style="margin-top: 24px;">
        <tr>
            <td align="center">
                <span style="
                    display: inline-block;
                    padding: 6px 16px;
                    border-radius: 20px;
                    font-size: 13px;
                    font-weight: 600;
                    @if($action === 'added')
                        background-color: {{ $isOwner ? '#ccfbf1' : '#fce7f3' }};
                        color: {{ $isOwner ? '#0d9488' : '#be123c' }};
                    @else
                        background-color: #fef3c7;
                        color: #92400e;
                    @endif
                ">
                    @if($action === 'added')
                        ✓ Passkey enregistrée avec succès
                    @else
                        ✕ Passkey supprimée
                    @endif
                </span>
            </td>
        </tr>
    </table>

    {{-- CTA --}}
    <div class="btn-wrapper" style="text-align: center; margin-top: 32px;">
        <a href="{{ $securityUrl }}" class="btn">
            Gérer mes passkeys
        </a>
    </div>

    {{-- Security warning --}}
    <table role="presentation" cellspacing="0" cellpadding="0" border="0" width="100%"
           style="margin-top: 32px; border-radius: 8px;">
        <tr>
            <td style="
                padding: 16px 20px;
                background-color: #fffbeb;
                border: 1px solid #fde68a;
                border-radius: 8px;
            ">
                <p style="margin: 0; font-size: 13px; color: #92400e; line-height: 1.6;">
                    <strong>⚠️ Vous n'êtes pas à l'origine de cette action ?</strong><br>
                    Si vous n'avez pas {{ $action === 'added' ? 'ajouté' : 'supprimé' }} cette passkey,
                    votre compte pourrait être compromis. Changez votre mot de passe immédiatement et
                    <a href="{{ $securityUrl }}" style="color: #92400e; text-decoration: underline;">vérifiez vos paramètres de sécurité</a>.
                </p>
            </td>
        </tr>
    </table>

@endsection
