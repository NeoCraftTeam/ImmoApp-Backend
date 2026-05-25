@extends('emails.owner-layout')

@php
    $headings = [
        1 => 'Publiez votre premier bien en 5 minutes',
        3 => 'Des photos qui font la différence',
        7 => 'Votre première annonce peut être en ligne aujourd\'hui',
    ];
    $heading = $headings[$day] ?? $headings[1];
@endphp

@section('title', $heading)

@section('preheader', $heading . ' — ' . config('app.name') . ' vous guide pas à pas.')

@section('content')

    <h1>{{ $heading }}</h1>

    @if($day === 1)

        <p class="text" style="margin-top: 16px;">
            Bonjour {{ $user->firstname }}, bienvenue sur <strong>{{ config('app.name') }}</strong> !<br>
            Voici comment mettre votre bien en ligne rapidement et attirer les bons locataires.
        </p>

        <table style="width: 100%; border-collapse: collapse; margin-top: 24px;">
            <tr>
                <td style="padding: 12px 0; border-bottom: 1px solid #f0fdf4; vertical-align: top; width: 28px;">
                    <span style="color: #0d9488; font-weight: 800; font-size: 16px;">1</span>
                </td>
                <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f0fdf4;">
                    <strong>Complétez votre profil bailleur</strong><br>
                    <span style="color: #6b7280; font-size: 13px;">Photo, bio et numéro WhatsApp — les locataires font confiance aux profils complets.</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0; border-bottom: 1px solid #f0fdf4; vertical-align: top; width: 28px;">
                    <span style="color: #0d9488; font-weight: 800; font-size: 16px;">2</span>
                </td>
                <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f0fdf4;">
                    <strong>Créez votre première annonce</strong><br>
                    <span style="color: #6b7280; font-size: 13px;">Titre, description, type, loyer en FCFA et localisation — notre assistant vous guide.</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                    <span style="color: #0d9488; font-weight: 800; font-size: 16px;">3</span>
                </td>
                <td style="padding: 12px 0 12px 12px;">
                    <strong>Recevez vos premières demandes</strong><br>
                    <span style="color: #6b7280; font-size: 13px;">Dès validation, votre bien est visible par des milliers de locataires actifs.</span>
                </td>
            </tr>
        </table>

        @include('emails.partials.button', [
            'url'   => $newAdUrl,
            'label' => 'Publier mon premier bien',
            'color' => '#0d9488',
            'width' => 240,
        ])

    @elseif($day === 3)

        <p class="text" style="margin-top: 16px;">
            Bonjour {{ $user->firstname }},<br>
            saviez-vous que les annonces avec <strong>5 photos minimum</strong> reçoivent
            <strong>4× plus de demandes de visite</strong> sur {{ config('app.name') }} ?
        </p>

        {{-- Photo tips card --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
            border-collapse: collapse;
            margin-top: 24px;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
        ">
            <tr>
                <td style="padding: 20px 24px;">
                    <p style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; text-transform: uppercase;
                        letter-spacing: 0.6px; color: #0d9488;">Conseils photos</p>
                    <ul style="margin: 0; padding: 0 0 0 16px; color: #374151; font-size: 14px; line-height: 1.8;">
                        <li>Photographiez en lumière naturelle (fenêtres, journée)</li>
                        <li>Orientez-vous vers les angles larges pour montrer l'espace</li>
                        <li>Incluez salon, chambre(s), cuisine et salle de bain</li>
                        <li>Ajoutez une photo de la façade ou de l'entrée</li>
                    </ul>
                </td>
            </tr>
        </table>

        <p class="text" style="margin-top: 20px;">
            Vous pouvez aussi ajouter une <strong>visite virtuelle 360°</strong> pour vous démarquer —
            une fonctionnalité exclusive {{ config('app.name') }}.
        </p>

        @include('emails.partials.button', [
            'url'   => $panelUrl,
            'label' => 'Améliorer mes annonces',
            'color' => '#0d9488',
            'width' => 240,
        ])

    @else {{-- day 7 --}}

        <p class="text" style="margin-top: 16px;">
            Bonjour {{ $user->firstname }},<br>
            cela fait une semaine que vous avez rejoint {{ config('app.name') }}. C'est le bon moment pour
            mettre votre premier bien en ligne et commencer à recevoir des locataires.
        </p>

        {{-- Social proof block --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
            border-collapse: collapse;
            margin-top: 24px;
            background: linear-gradient(135deg, #f0fdf4 0%, #ecfdf5 100%);
            border: 1px solid #bbf7d0;
            border-radius: 10px;
        ">
            <tr>
                <td style="padding: 20px 24px;">
                    <p style="margin: 0 0 4px 0; font-size: 13px; font-weight: 700; color: #0d9488; text-transform: uppercase; letter-spacing: 0.6px;">
                        Ce que disent nos bailleurs
                    </p>
                    <p style="margin: 8px 0 0 0; font-size: 15px; color: #1e293b; line-height: 1.6; font-style: italic;">
                        « J'ai reçu 3 demandes de visite dès le premier jour de publication.
                        Le tableau de bord est vraiment simple à utiliser. »
                    </p>
                    <p style="margin: 8px 0 0 0; font-size: 12px; color: #0d9488; font-weight: 600;">
                        — Bailleur certifié, Douala
                    </p>
                </td>
            </tr>
        </table>

        <p class="text" style="margin-top: 20px;">
            En moins de <strong>5 minutes</strong>, votre bien peut être visible par des milliers de locataires
            actifs sur {{ config('app.name') }}.
        </p>

        @include('emails.partials.button', [
            'url'   => $newAdUrl,
            'label' => 'Je publie maintenant',
            'color' => '#0d9488',
            'width' => 220,
        ])

    @endif

    <p class="fallback" style="margin-top: 32px; font-size: 13px;">
        Besoin d'aide ? <a href="mailto:support@keyhome.app" class="link">support@keyhome.app</a>
    </p>

@endsection
