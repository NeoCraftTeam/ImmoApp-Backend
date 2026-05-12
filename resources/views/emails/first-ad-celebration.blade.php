@extends('emails.owner-layout')

@section('title', 'Bravo pour votre première annonce !')

@section('content')

    {{-- Hero celebration banner --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom: 28px;">
        <tr>
            <td align="center" style="
                background: linear-gradient(135deg, #0f766e 0%, #0d9488 50%, #14b8a6 100%);
                border-radius: 12px;
                padding: 32px 24px;
            ">
                <div style="font-size: 48px; line-height: 1; margin-bottom: 12px;">🏆</div>
                <p style="margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; line-height: 1.3;">
                    Bravo, {{ $authorName }} !
                </p>
                <p style="margin: 8px 0 0 0; font-size: 14px; color: #99f6e4; font-weight: 500;">
                    Vous venez de poster votre première annonce
                </p>
            </td>
        </tr>
    </table>

    <p class="text">
        C'est une étape importante. Votre annonce
        <strong>« {{ $adTitle }} »</strong>
        a bien été reçue et est en cours de vérification par notre équipe.
    </p>

    {{-- Ad status card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
        border-collapse: collapse;
        margin-top: 24px;
        background-color: #f0fdfa;
        border: 1px solid #99f6e4;
        border-radius: 10px;
    ">
        <tr>
            <td style="padding: 20px 24px;">
                <p style="margin: 0 0 4px 0; font-size: 11px; font-weight: 700; text-transform: uppercase;
                    letter-spacing: 0.8px; color: #0d9488;">Votre annonce</p>
                <p style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;">
                    {{ $adTitle }}
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-top: 14px;">
                    <tr>
                        <td>
                            <span style="
                                display: inline-block;
                                background-color: #fef9c3;
                                color: #854d0e;
                                border: 1px solid #fde68a;
                                border-radius: 20px;
                                padding: 4px 14px;
                                font-size: 12px;
                                font-weight: 700;
                            ">⏳ En attente de validation</span>
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- What happens next — modern timeline --}}
    <p style="margin: 28px 0 14px 0; font-size: 14px; font-weight: 700; color: #0f172a; text-transform: uppercase;
        letter-spacing: 0.5px;">
        Ce qui se passe maintenant
    </p>

    {{-- Step 1 --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom: 12px;">
        <tr>
            <td width="44" valign="top">
                <div style="
                    width: 36px; height: 36px;
                    border-radius: 50%;
                    background-color: #0d9488;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                    line-height: 36px;
                    font-size: 16px;
                ">✅</div>
            </td>
            <td valign="middle" style="padding-left: 4px;">
                <p style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;">Annonce soumise</p>
                <p style="margin: 2px 0 0 0; font-size: 13px; color: #64748b;">
                    Votre annonce a été envoyée avec succès
                </p>
            </td>
        </tr>
    </table>

    {{-- Connector --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom: 12px;">
        <tr>
            <td width="44" align="center">
                <div style="width: 2px; height: 20px; background-color: #99f6e4; margin: 0 auto;"></div>
            </td>
            <td></td>
        </tr>
    </table>

    {{-- Step 2 --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom: 12px;">
        <tr>
            <td width="44" valign="top">
                <div style="
                    width: 36px; height: 36px;
                    border-radius: 50%;
                    background-color: #f59e0b;
                    text-align: center;
                    line-height: 36px;
                    font-size: 16px;
                ">🔍</div>
            </td>
            <td valign="middle" style="padding-left: 4px;">
                <p style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;">Vérification en cours</p>
                <p style="margin: 2px 0 0 0; font-size: 13px; color: #64748b;">
                    Notre équipe examine votre annonce (généralement sous 24 h)
                </p>
            </td>
        </tr>
    </table>

    {{-- Connector --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom: 12px;">
        <tr>
            <td width="44" align="center">
                <div style="width: 2px; height: 20px; background-color: #e2e8f0; margin: 0 auto;"></div>
            </td>
            <td></td>
        </tr>
    </table>

    {{-- Step 3 --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse:collapse; margin-bottom: 28px;">
        <tr>
            <td width="44" valign="top">
                <div style="
                    width: 36px; height: 36px;
                    border-radius: 50%;
                    background-color: #e2e8f0;
                    text-align: center;
                    line-height: 36px;
                    font-size: 16px;
                ">🚀</div>
            </td>
            <td valign="middle" style="padding-left: 4px;">
                <p style="margin: 0; font-size: 14px; font-weight: 700; color: #94a3b8;">Publication</p>
                <p style="margin: 2px 0 0 0; font-size: 13px; color: #94a3b8;">
                    Vous serez notifié dès que votre annonce sera en ligne
                </p>
            </td>
        </tr>
    </table>

    {{-- Tips box --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
        border-collapse: collapse;
        background-color: #f0fdfa;
        border-left: 4px solid #0d9488;
        border-radius: 0 8px 8px 0;
        margin-bottom: 28px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 700; color: #0f172a;">
                    💡 Conseils pour maximiser vos chances
                </p>
                <p style="margin: 0; font-size: 13px; color: #475569; line-height: 1.6;">
                    Ajoutez des photos de qualité · Répondez rapidement aux demandes ·
                    Renseignez toutes les informations demandées
                </p>
            </td>
        </tr>
    </table>

    {{-- CTA --}}
    <div class="btn-wrapper">
        <a href="{{ $panelUrl }}" class="btn">
            Voir mon tableau de bord →
        </a>
    </div>

    <p class="text" style="margin-top: 24px;">
        Merci de faire confiance à <strong>{{ config('app.name') }}</strong>.
        Notre équipe est là pour vous accompagner à chaque étape.
    </p>

@endsection
