@extends('emails.owner-layout')

@php
    $isEarlyNoAd  = $daysSinceActivity <= 7 && !$hasPublishedAd;
    $isMidInactive = $daysSinceActivity > 7 && $daysSinceActivity <= 14;
    $isDeepWinBack = $daysSinceActivity > 14;

    $heading = match(true) {
        $isEarlyNoAd  => 'Votre premier bien vous attend',
        $isMidInactive => 'Vos annonces méritent votre attention',
        default        => $user->firstname . ', vos locataires potentiels sont là',
    };
@endphp

@section('title', $heading)

@section('preheader', $heading . ' — revenez sur ' . config('app.name') . ' et boostez votre activité.')

@section('content')

    <h1>{{ $heading }}</h1>

    @if($isEarlyNoAd)

        {{-- D+7 — registered but no ad yet --}}
        <p class="text" style="margin-top: 16px;">
            Bonjour {{ $user->firstname }},<br>
            vous avez créé votre espace bailleur il y a {{ $daysSinceActivity }} jours mais n'avez pas encore
            publié de bien. Les locataires sont actifs sur {{ config('app.name') }} —
            ne manquez pas cette opportunité.
        </p>

        {{-- Urgency card --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
            border-collapse: collapse;
            margin-top: 24px;
            background-color: #fff7ed;
            border: 1px solid #fed7aa;
            border-radius: 10px;
        ">
            <tr>
                <td style="padding: 20px 24px;">
                    <p style="margin: 0 0 8px 0; font-size: 14px; font-weight: 700; color: #c2410c;">
                        Pourquoi publier maintenant ?
                    </p>
                    <ul style="margin: 0; padding: 0 0 0 16px; color: #374151; font-size: 14px; line-height: 1.9;">
                        <li>Les nouvelles annonces bénéficient d'un <strong>boost de visibilité automatique</strong></li>
                        <li>Votre annonce sera vérifiée sous <strong>24 heures</strong></li>
                        <li>Publication <strong>gratuite</strong> pour votre premier bien</li>
                    </ul>
                </td>
            </tr>
        </table>

        <div style="margin-top: 28px;">
            @include('emails.partials.button', [
                'url'   => $newAdUrl,
                'label' => 'Publier mon premier bien',
                'color' => '#0d9488',
                'width' => 260,
            ])
        </div>

    @elseif($isMidInactive)

        {{-- D+14 — has ad(s) but inactive --}}
        <p class="text" style="margin-top: 16px;">
            Bonjour {{ $user->firstname }},<br>
            @if($activeAdsCount > 0)
                vos <strong>{{ $activeAdsCount }} annonce{{ $activeAdsCount > 1 ? 's' : '' }} active{{ $activeAdsCount > 1 ? 's' : '' }}</strong>
                continuent de recevoir des vues, mais les annonces mises à jour régulièrement
                obtiennent <strong>3× plus de contact</strong>.
            @else
                cela fait {{ $daysSinceActivity }} jours que vous n'avez pas mis à jour votre espace.
                Les locataires cherchent activement des biens — c'est le moment idéal.
            @endif
        </p>

        {{-- Tips card --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
            border-collapse: collapse;
            margin-top: 24px;
            background-color: #f0fdf4;
            border: 1px solid #bbf7d0;
            border-radius: 10px;
        ">
            <tr>
                <td style="padding: 20px 24px;">
                    <p style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #0d9488; text-transform: uppercase; letter-spacing: 0.6px;">
                        Actions rapides pour +de visibilité
                    </p>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 6px 0; font-size: 14px; color: #374151;">
                                <span style="color: #0d9488; font-weight: 700; margin-right: 8px;">→</span>
                                Rafraîchissez les photos de vos annonces
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; font-size: 14px; color: #374151;">
                                <span style="color: #0d9488; font-weight: 700; margin-right: 8px;">→</span>
                                Ajoutez vos créneaux de visite disponibles
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 6px 0; font-size: 14px; color: #374151;">
                                <span style="color: #0d9488; font-weight: 700; margin-right: 8px;">→</span>
                                Vérifiez les messages de locataires en attente
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <div style="margin-top: 28px;">
            @include('emails.partials.button', [
                'url'   => $manageAdsUrl,
                'label' => 'Gérer mes annonces',
                'color' => '#0d9488',
                'width' => 220,
            ])
        </div>

    @else

        {{-- D+30 — deep win-back --}}
        <p class="text" style="margin-top: 16px;">
            Bonjour {{ $user->firstname }},<br>
            votre espace bailleur sur {{ config('app.name') }} vous attend. Pendant votre absence,
            des centaines de locataires ont recherché des biens similaires aux vôtres.
        </p>

        {{-- Win-back incentive --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
            border-collapse: collapse;
            margin-top: 24px;
            background: linear-gradient(135deg, #0f766e 0%, #0d9488 100%);
            border-radius: 12px;
        ">
            <tr>
                <td align="center" style="padding: 24px 20px;">
                    <p style="margin: 0 0 4px 0; font-size: 13px; color: #99f6e4; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px;">
                        Nous vous avons réservé
                    </p>
                    <p style="margin: 0; font-size: 28px; font-weight: 800; color: #ffffff; line-height: 1.2;">
                        +5 crédits offerts
                    </p>
                    <p style="margin: 8px 0 0 0; font-size: 13px; color: #ccfbf1;">
                        Pour booster votre prochaine annonce — valables 7 jours
                    </p>
                </td>
            </tr>
        </table>

        <p class="text" style="margin-top: 20px;">
            Reconnectez-vous, mettez à jour vos annonces et vos crédits seront automatiquement appliqués.
        </p>

        <div style="margin-top: 28px;">
            @include('emails.partials.button', [
                'url'   => $panelUrl,
                'label' => 'Revenir sur mon espace',
                'color' => '#0d9488',
                'width' => 240,
            ])
        </div>

    @endif

    <p class="fallback" style="margin-top: 32px; font-size: 13px; color: #9ca3af; text-align: center;">
        Pour ne plus recevoir ces rappels, vous pouvez
        <a href="{{ $unsubscribeUrl ?? '#' }}" style="color: #9ca3af;">vous désabonner ici</a>.
    </p>

@endsection
