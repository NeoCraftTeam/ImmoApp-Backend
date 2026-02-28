@extends('emails.layout')

@section('title', 'Annonce reçue — en attente de validation')

@section('content')

    <h1>Votre annonce a bien été reçue</h1>

    <p class="text">
        Bonjour <strong>{{ $authorName }}</strong>,
    </p>

    <p class="text">
        Nous avons bien reçu votre annonce <strong>« {{ $adTitle }} »</strong>.
        Elle est actuellement <strong>en cours de vérification</strong> par notre équipe de modération.
        Vous serez notifié par email dès qu'une décision sera prise.
    </p>

    {{-- Status badge --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-collapse: collapse;">
        <tr>
            <td align="center">
                <span style="
                        display: inline-block;
                        background-color: #fffbeb;
                        color: #92400e;
                        border: 1px solid #fcd34d;
                        border-radius: 20px;
                        padding: 6px 20px;
                        font-size: 13px;
                        font-weight: 700;
                        letter-spacing: 0.3px;
                    ">En attente de validation</span>
            </td>
        </tr>
    </table>

    {{-- Steps tracker --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
            margin-top: 28px;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        ">
        <tr>
            <td style="padding: 20px 24px;">

                {{-- Step 1 : done --}}
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 14px;">
                    <tr>
                        <td width="36" valign="top">
                            <div style="
                                    width: 28px; height: 28px;
                                    border-radius: 50%;
                                    background-color: #22c55e;
                                    color: #ffffff;
                                    text-align: center;
                                    line-height: 28px;
                                    font-size: 14px;
                                    font-weight: 700;
                                ">1</div>
                        </td>
                        <td valign="top" style="padding-top: 4px;">
                            <p style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;">Soumission</p>
                            <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Votre annonce a été envoyée avec
                                succès</p>
                        </td>
                    </tr>
                </table>

                {{-- Step 2 : in progress --}}
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 14px;">
                    <tr>
                        <td width="36" valign="top">
                            <div style="
                                    width: 28px; height: 28px;
                                    border-radius: 50%;
                                    background-color: #F6475F;
                                    color: #ffffff;
                                    text-align: center;
                                    line-height: 28px;
                                    font-size: 14px;
                                    font-weight: 700;
                                ">2</div>
                        </td>
                        <td valign="top" style="padding-top: 4px;">
                            <p style="margin: 0; font-size: 14px; font-weight: 700; color: #0f172a;">Vérification</p>
                            <p style="margin: 4px 0 0 0; font-size: 13px; color: #64748b;">Notre équipe examine votre
                                annonce</p>
                        </td>
                    </tr>
                </table>

                {{-- Step 3 : pending --}}
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td width="36" valign="top">
                            <div style="
                                    width: 28px; height: 28px;
                                    border-radius: 50%;
                                    background-color: #e2e8f0;
                                    color: #94a3b8;
                                    text-align: center;
                                    line-height: 28px;
                                    font-size: 14px;
                                    font-weight: 700;
                                ">3</div>
                        </td>
                        <td valign="top" style="padding-top: 4px;">
                            <p style="margin: 0; font-size: 14px; font-weight: 700; color: #94a3b8;">Publication</p>
                            <p style="margin: 4px 0 0 0; font-size: 13px; color: #94a3b8;">Vous serez notifié dès que votre
                                annonce sera en ligne</p>
                        </td>
                    </tr>
                </table>

            </td>
        </tr>
    </table>

    <p class="text" style="margin-top: 28px;">
        Merci pour votre confiance. Notre équipe traite les annonces dans les meilleurs délais.
    </p>

@endsection
