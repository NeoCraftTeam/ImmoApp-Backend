@extends('emails.layout')

@section('title', 'Vérification de sécurité — Tarification')

@section('content')

    <h1>Vérification de sécurité</h1>

    <p class="text">
        Bonjour <strong>{{ $user->firstname }}</strong>,
    </p>

    <p class="text">
        Vous avez demandé à modifier la <strong>tarification de l'application KeyHome</strong>.
        Pour confirmer cette opération sensible, entrez le code ci-dessous lorsqu'il vous est demandé.
    </p>

    <div class="otp-box">
        <div class="otp-code">{{ $code }}</div>
        <div class="otp-label">Expire dans 10 minutes</div>
    </div>

    {{-- Warning box --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 28px; border-collapse: collapse;">
        <tr>
            <td style="
                background-color: #fff1f2;
                border: 1px solid #fecdd3;
                border-left: 4px solid #F6475F;
                border-radius: 8px;
                padding: 16px 20px;
                font-size: 14px;
                color: #9f1239;
                line-height: 1.6;
            ">
                <strong>⚠️ Vous n'avez pas fait cette demande ?</strong><br>
                Ignorez cet email — aucune modification ne sera effectuée sans saisie du code.
                Nous vous recommandons de changer votre mot de passe par mesure de précaution.
            </td>
        </tr>
    </table>

    <p class="text" style="margin-top: 28px; font-size: 13px; color: #94a3b8;">
        Cet email a été envoyé suite à une demande de modification de tarification
        depuis le panneau d'administration.
    </p>

@endsection
