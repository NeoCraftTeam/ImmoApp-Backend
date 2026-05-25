@extends('emails.layout')

@section('title', __("emails.welcome_drip.day{$day}_subject", ['app' => config('app.name')]))

@section('preheader', __("emails.welcome_drip.day{$day}_heading") . ' — ' . config('app.name') . ' vous accompagne dans votre recherche immobilière.')

@php
    $heroLabels = [1 => 'Jour 1 — Démarrage', 3 => 'Jour 3 — Alertes', 7 => 'Jour 7 — Résultats'];
    $heroSubs   = [
        1 => '3 fonctionnalités à activer pour trouver votre bien plus vite',
        3 => 'Ne ratez aucune annonce — activez votre première alerte',
        7 => 'Votre prochain chez-vous est peut-être déjà en ligne',
    ];
@endphp

@section('hero')
    @include('emails.partials.hero', [
        'heroBg'      => 'linear-gradient(135deg, #1e293b 0%, #C73B52 100%)',
        'heroEyebrow' => $heroLabels[$day] ?? ('Jour ' . $day),
        'heroText'    => __("emails.welcome_drip.day{$day}_heading"),
        'heroSub'     => $heroSubs[$day] ?? config('app.name'),
    ])
@endsection

@section('content')

    <span class="eyebrow">{{ $heroLabels[$day] ?? ('Jour ' . $day) }}</span>
    <h1>{{ __("emails.welcome_drip.day{$day}_heading") }}</h1>

    <p class="text" style="margin-top: 16px;">
        {!! __("emails.welcome_drip.day{$day}_intro", ['name' => $user->firstname, 'app' => config('app.name')]) !!}
    </p>

    @if($day === 1)
        {{-- Tips list --}}
        <table style="width: 100%; border-collapse: collapse; margin-top: 24px;">
            <tr>
                <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                    <span style="color: #F6475F; font-weight: 700; font-size: 16px;">1</span>
                </td>
                <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                    <strong>{{ __('emails.welcome_drip.day1_tip1') }}</strong><br>
                    <span style="color: #6b7280; font-size: 13px;">{{ __('emails.welcome_drip.day1_tip1_desc') }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                    <span style="color: #F6475F; font-weight: 700; font-size: 16px;">2</span>
                </td>
                <td style="padding: 12px 0 12px 12px; border-bottom: 1px solid #f1f5f9;">
                    <strong>{{ __('emails.welcome_drip.day1_tip2') }}</strong><br>
                    <span style="color: #6b7280; font-size: 13px;">{{ __('emails.welcome_drip.day1_tip2_desc') }}</span>
                </td>
            </tr>
            <tr>
                <td style="padding: 12px 0; vertical-align: top; width: 28px;">
                    <span style="color: #F6475F; font-weight: 700; font-size: 16px;">3</span>
                </td>
                <td style="padding: 12px 0 12px 12px;">
                    <strong>{{ __('emails.welcome_drip.day1_tip3') }}</strong><br>
                    <span style="color: #6b7280; font-size: 13px;">{{ __('emails.welcome_drip.day1_tip3_desc') }}</span>
                </td>
            </tr>
        </table>

    @elseif($day === 3)
        {{-- Alert creation guide card --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
            border-collapse: collapse; margin-top: 24px;
            background-color: #fff1f2; border: 1px solid #fecdd3; border-radius: 10px;
        ">
            <tr>
                <td style="padding: 20px 24px;">
                    <p style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #C73B52; text-transform: uppercase; letter-spacing: 0.6px;">
                        Créer une alerte en 30 secondes
                    </p>
                    <table style="width: 100%; border-collapse: collapse;">
                        <tr>
                            <td style="padding: 7px 0; font-size: 14px; color: #374151; vertical-align: top; width: 22px;">
                                <span style="color: #F6475F; font-weight: 800;">1.</span>
                            </td>
                            <td style="padding: 7px 0 7px 8px; font-size: 14px; color: #374151;">
                                Ouvrez la <strong>recherche</strong> et définissez vos filtres (ville, budget, type)
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 0; font-size: 14px; color: #374151; vertical-align: top; width: 22px;">
                                <span style="color: #F6475F; font-weight: 800;">2.</span>
                            </td>
                            <td style="padding: 7px 0 7px 8px; font-size: 14px; color: #374151;">
                                Cliquez sur <strong>« Créer une alerte »</strong> pour sauvegarder ces critères
                            </td>
                        </tr>
                        <tr>
                            <td style="padding: 7px 0; font-size: 14px; color: #374151; vertical-align: top; width: 22px;">
                                <span style="color: #F6475F; font-weight: 800;">3.</span>
                            </td>
                            <td style="padding: 7px 0 7px 8px; font-size: 14px; color: #374151;">
                                Recevez une <strong>notification instantanée</strong> dès qu'un bien correspond
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>

        <p class="text" style="margin-top: 16px; font-size: 13px; color: #6b7280;">
            Les locataires avec une alerte active trouvent leur bien <strong>2× plus vite</strong> que les autres.
        </p>

    @elseif($day === 7)
        {{-- Social proof + search nudge --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
            border-collapse: collapse; margin-top: 24px;
            background: linear-gradient(135deg, #1e293b 0%, #C73B52 100%);
            border-radius: 10px;
        ">
            <tr>
                <td style="padding: 20px 24px;">
                    <p style="margin: 0 0 4px 0; font-size: 12px; color: #fecdd3; font-weight: 600; text-transform: uppercase; letter-spacing: 0.8px;">
                        Ce que disent nos utilisateurs
                    </p>
                    <p style="margin: 8px 0 0 0; font-size: 15px; color: #ffffff; line-height: 1.6; font-style: italic;">
                        « J'ai trouvé mon appartement en 4 jours grâce aux alertes.
                        Le système de vérification des annonces m'a vraiment rassuré. »
                    </p>
                    <p style="margin: 8px 0 0 0; font-size: 12px; color: #fecdd3; font-weight: 600;">
                        — Locataire satisfait, Douala
                    </p>
                </td>
            </tr>
        </table>

        <p class="text" style="margin-top: 20px;">
            Votre prochain chez-vous est peut-être déjà en ligne.
            Affinez vos critères, activez une alerte et laissez {{ config('app.name') }} travailler pour vous.
        </p>

    @endif

    @include('emails.partials.button', [
        'url'   => config('app.frontend_url', config('app.url')) . '/home',
        'label' => __("emails.welcome_drip.day{$day}_cta"),
        'width' => 220,
    ])

@endsection
