@extends('emails.layout')

@section('title', 'Bienvenue administrateur - ' . config('app.name'))

@section('content')

    <h1>Bienvenue, {{ $user->firstname }}</h1>

    <p class="text">
        Votre compte <strong>administrateur</strong> KeyHome est maintenant activé.
        Vous avez accès au panneau d'administration pour gérer la plateforme.
    </p>

    <p class="text" style="margin-top: 24px; font-weight: 600; color: #000;">
        En tant qu'administrateur, vous pouvez :
    </p>

    <table style="width: 100%; border-collapse: collapse; margin-top: 16px;">
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Modérer les annonces</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Valider, rejeter ou gérer les annonces en attente.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                <strong>Gérer les utilisateurs</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Agents, clients et paramètres de la plateforme.</span>
            </td>
        </tr>
        <tr>
            <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700; font-size: 16px;">→</span>
            </td>
            <td style="padding: 12px 0 12px 12px;">
                <strong>Consulter les statistiques</strong><br>
                <span style="color: #6b7280; font-size: 13px;">Tableaux de bord, revenus et indicateurs clés.</span>
            </td>
        </tr>
    </table>

    <p class="text" style="margin-top: 24px; font-weight: 600; color: #dc2626;">
        Sécurité : pensez à changer votre mot de passe et à configurer l'authentification à deux facteurs (2FA) lors de votre première connexion.
    </p>

    @php
        $domain = config('filament.panels.admin_domain');
        $adminUrl = $domain ? 'https://' . $domain . '/login' : (config('app.url') . '/admin/login');
    @endphp

    <div class="btn-wrapper">
        <a href="{{ $adminUrl }}" class="btn">
            Accéder au panneau d'administration
        </a>
    </div>

    <p class="fallback" style="margin-top: 24px;">
        Si vous avez des questions, contactez l'équipe technique à
        <a href="mailto:support@keyhome.app" class="link">support@keyhome.app</a>.
    </p>

@endsection
