@extends('emails.layout')

@php
    $isEarly  = $daysSinceLogin <= 14;
    $isDeep   = $daysSinceLogin >= 60;
@endphp

@section('title', __('emails.inactivity.subject', ['name' => $user->firstname, 'app' => config('app.name')]))

@section('preheader', $isEarly
    ? 'Des biens correspondant à votre recherche ont été publiés — ' . $newAdsCount . ' nouvelles annonces vous attendent.'
    : 'Cela fait ' . $daysSinceLogin . ' jours. Vos critères sont toujours actifs — ' . $newAdsCount . ' biens vous attendent sur ' . config('app.name') . '.')

@section('content')

    @if($isEarly)
        {{-- D7/D14 — warm check-in, not alarm --}}
        <h1>{{ $user->firstname }}, du nouveau sur {{ config('app.name') }}</h1>

        <p class="text" style="margin-top: 16px;">
            Depuis votre dernière visite,
            @if($newAdsCount > 0)
                <strong>{{ $newAdsCount }} nouvelle{{ $newAdsCount > 1 ? 's annonce' : ' annonce' }}{{ $newAdsCount > 1 ? 's' : '' }}</strong>
                ont été publiées sur {{ config('app.name') }}.
            @else
                notre équipe a continué de vérifier et publier de nouveaux biens sur <strong>{{ config('app.name') }}</strong>.
            @endif
            Les meilleures offres partent vite — jetez un œil.
        </p>

    @elseif($isDeep)
        {{-- D60/D90 — win-back, strong emotional hook --}}
        <h1>{{ $user->firstname }}, votre recherche vous attend encore</h1>

        <p class="text" style="margin-top: 16px;">
            Ça fait un moment. Depuis votre dernière visite sur <strong>{{ config('app.name') }}</strong>,
            <strong>{{ $newAdsCount }} bien{{ $newAdsCount > 1 ? 's' : '' }}</strong>
            correspondant à votre profil ont été publiés. Votre prochaine maison est peut-être parmi eux.
        </p>

        {{-- Win-back incentive --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
            border-collapse: collapse;
            margin-top: 24px;
            background: linear-gradient(135deg, #1a1a2e 0%, #C73B52 100%);
            border-radius: 10px;
        ">
            <tr>
                <td align="center" style="padding: 20px 24px;">
                    <p style="margin: 0 0 4px 0; font-size: 13px; color: #fecdd3; font-weight: 600; text-transform: uppercase; letter-spacing: 0.7px;">
                        Biens publiés depuis votre absence
                    </p>
                    <p style="margin: 0; font-size: 34px; font-weight: 800; color: #ffffff; line-height: 1.1;">
                        {{ $newAdsCount }}
                    </p>
                    <p style="margin: 6px 0 0 0; font-size: 13px; color: #fecdd3;">
                        annonce{{ $newAdsCount > 1 ? 's' : '' }} correspondant à votre profil
                    </p>
                </td>
            </tr>
        </table>

    @else
        {{-- D30 — standard re-engagement --}}
        <h1>{{ __('emails.inactivity.heading') }}</h1>

        <p class="text" style="margin-top: 16px;">
            {!! __('emails.inactivity.intro', ['name' => $user->firstname, 'count' => $newAdsCount, 'app' => config('app.name')]) !!}
        </p>

        @if($newAdsCount > 0)
            <div style="background-color: #f0fdf4; border-radius: 10px; padding: 20px 24px; margin: 24px 0; border: 1px solid #bbf7d0; text-align: center;">
                <span style="font-size: 32px; font-weight: 800; color: #166534;">{{ $newAdsCount }}</span>
                <p style="margin: 4px 0 0 0; font-size: 14px; color: #166534; font-weight: 600;">
                    {{ __('emails.inactivity.stats', ['count' => $newAdsCount]) }}
                </p>
            </div>
        @endif

    @endif

    {{-- For early + deep, show count badge separately if not already shown --}}
    @if(($isEarly || $isDeep) && $newAdsCount > 0 && !$isDeep)
        <div style="background-color: #fff1f2; border-radius: 10px; padding: 18px 24px; margin: 24px 0; border: 1px solid #fecdd3; text-align: center;">
            <span style="font-size: 28px; font-weight: 800; color: #C73B52;">{{ $newAdsCount }}</span>
            <p style="margin: 4px 0 0 0; font-size: 13px; color: #C73B52; font-weight: 600;">
                nouvelle{{ $newAdsCount > 1 ? 's annonce' : ' annonce' }}{{ $newAdsCount > 1 ? 's' : '' }} depuis votre dernière visite
            </p>
        </div>
    @endif

    {{-- Tips block for D7/D14 only --}}
    @if($isEarly)
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
            border-collapse: collapse;
            margin-top: 20px;
            background-color: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 10px;
        ">
            <tr>
                <td style="padding: 16px 20px;">
                    <p style="margin: 0 0 10px 0; font-size: 13px; font-weight: 700; color: #C73B52; text-transform: uppercase; letter-spacing: 0.6px;">
                        Conseil rapide
                    </p>
                    <p style="margin: 0; font-size: 14px; color: #374151; line-height: 1.7;">
                        Activez une <strong>alerte personnalisée</strong> pour être notifié en temps réel dès qu'un bien
                        correspondant à vos critères est publié — même sans vous connecter.
                    </p>
                </td>
            </tr>
        </table>
    @endif

    <div style="margin-top: 28px;">
        @include('emails.partials.button', [
            'url'   => config('app.frontend_url', config('app.url')) . '/home',
            'label' => $isEarly ? 'Voir les nouvelles annonces' : ($isDeep ? 'Reprendre ma recherche' : __('emails.inactivity.cta')),
            'width' => 240,
        ])
    </div>

@endsection
