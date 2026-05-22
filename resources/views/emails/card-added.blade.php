@extends('emails.layout')

@section('title', 'Carte bancaire ajoutée — ' . config('app.name'))

@section('preheader', 'Une carte bancaire a été associée à votre compte. Si vous n\'en êtes pas à l\'origine, contactez notre support.')

@section('content')

    {{-- Security badge --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-bottom: 6px;">
        <tr>
            <td style="text-align: center; padding: 8px 0 2px 0;">
                <span style="
                    display: inline-block;
                    background-color: #f0fdf4;
                    border: 1px solid #bbf7d0;
                    border-radius: 20px;
                    padding: 5px 14px;
                    font-size: 12px;
                    font-weight: 600;
                    color: #166534;
                    letter-spacing: 0.3px;
                ">💳&nbsp; Moyen de paiement ajouté</span>
            </td>
        </tr>
    </table>

    <h1 style="text-align: center; font-size: 20px; margin: 10px 0 6px 0;">Nouvelle carte enregistrée</h1>

    <p class="text" style="text-align: center; color: #64748b; margin-bottom: 24px;">
        Bonjour <strong style="color: #1e293b;">{{ $user->firstname }}</strong>,<br>
        une carte bancaire a été ajoutée à votre compte <strong style="color: #1e293b;">{{ config('app.name') }}</strong>.
    </p>

    {{-- Card details --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin: 0 0 24px 0;">
        <tr>
            <td style="
                background-color: #f8fafc;
                border: 1px solid #e2e8f0;
                border-left: 4px solid #F6475F;
                border-radius: 8px;
                padding: 0;
                overflow: hidden;
            ">
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td colspan="2" style="
                            padding: 12px 20px 10px 20px;
                            font-size: 10px;
                            font-weight: 700;
                            text-transform: uppercase;
                            letter-spacing: 1px;
                            color: #F6475F;
                            border-bottom: 1px solid #e2e8f0;
                        ">Détails de la carte</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 40%; color: #64748b; font-size: 13px; border-bottom: 1px solid #f1f5f9;">Type</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; font-weight: 600; color: #0f172a; border-bottom: 1px solid #f1f5f9; text-transform: capitalize;">{{ $cardBrand }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 20px 9px 20px; width: 40%; color: #64748b; font-size: 13px;">Numéro</td>
                        <td style="padding: 9px 20px 9px 8px; font-size: 13px; font-weight: 600; font-family: 'SFMono-Regular', Consolas, 'Liberation Mono', Menlo, monospace; color: #0f172a;">•••• •••• •••• {{ $cardLast4 }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Not you? --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse; margin-bottom: 24px;">
        <tr>
            <td style="background-color: #fff1f2; border: 1px solid #fecdd3; border-radius: 8px; padding: 14px 18px;">
                <p style="margin: 0; font-size: 14px; color: #9f1239;">
                    <strong>Ce n'est pas vous ?</strong> Si vous n'avez pas effectué cette action, contactez immédiatement notre support et vérifiez la sécurité de votre compte.
                </p>
            </td>
        </tr>
    </table>

    <p class="text" style="margin-top: 24px; font-size: 12px; color: #94a3b8;">
        Cet e-mail a été généré automatiquement par {{ config('app.name') }}.
    </p>

@endsection
