{{-- Password reset email — adapts layout for owner (teal) vs client (default) --}}
@extends($emailLayout ?? 'emails.layout')

@section('title', __('emails.forgot_password.subject', ['app' => config('app.name')]))

@section('preheader', 'Réinitialisez votre mot de passe — ce lien expire dans ' . ($ttlMinutes ?? 60) . ' minutes.')

@section('hero')
    @include('emails.partials.hero', [
        'heroBg'      => !empty($isOwner)
            ? 'linear-gradient(135deg, #042f2e 0%, #164e63 100%)'
            : 'linear-gradient(135deg, #0f172a 0%, #1e3a5f 100%)',
        'heroEyebrow' => 'Sécurité du compte',
        'heroText'    => 'Réinitialisation de mot de passe',
        'heroSub'     => 'Lien valide ' . ($ttlMinutes ?? 60) . ' minutes — ne le partagez jamais',
    ])
@endsection

@section('content')

    <span class="eyebrow">{{ !empty($isOwner) ? 'Espace Bailleur' : 'Votre compte' }}</span>
    <h1>{{ __('emails.forgot_password.heading') }}</h1>

    @if(!empty($isOwner))
        <p class="text" style="margin-top: 16px; color: #0d9488; font-weight: 600;">
            Espace Bailleur — réinitialisation du mot de passe de votre compte propriétaire.
        </p>
    @endif

    <p class="text" style="margin-top: 32px;">
        {!! __('emails.forgot_password.intro', ['app' => config('app.name')]) !!}
    </p>

    <p class="text">
        {!! __('emails.forgot_password.click_below') !!}
    </p>

    @include('emails.partials.button', [
        'url'   => $resetUrl,
        'label' => __('emails.forgot_password.cta'),
        'color' => !empty($isOwner) ? '#0d9488' : '#F6475F',
        'width' => 240,
    ])

    <p class="fallback" style="margin-top: 24px;">
        {{ __('emails.forgot_password.fallback') }}<br>
        <a href="{{ $resetUrl }}" class="link">{{ $resetUrl }}</a>
    </p>

    <p class="text" style="margin-top: 64px;"><strong>{{ __('emails.forgot_password.not_requested') }}</strong></p>
    <p class="text" style="margin-top: 4px;">
        {!! __('emails.forgot_password.requested_from', ['from' => $requestedFrom, 'at' => $requestedAt]) !!}
    </p>

@endsection
