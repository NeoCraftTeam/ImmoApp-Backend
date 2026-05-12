{{-- Converted from Clerk invitation email template --}}
@extends('emails.layout')

@section('title', 'Votre invitation sur ' . config('app.name'))

@section('content')

    <h1>Vous avez été invité sur {{ config('app.name') }}</h1>

    <p class="text">
        @if (!empty($inviterName))
            <strong>{{ $inviterName }}</strong> vous invite à rejoindre {{ config('app.name') }},
            la plateforme immobilière simple et intelligente.
        @else
            Vous êtes invité à rejoindre {{ config('app.name') }},
            la plateforme immobilière simple et intelligente.
        @endif
    </p>

    {{-- Features --}}
    <table style="width: 100%; border-collapse: collapse; margin-top: 24px;">
        <tr>
            <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700;">→</span>
            </td>
            <td style="padding: 10px 0 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #475569;">
                Publiez et gérez vos annonces immobilières
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700;">→</span>
            </td>
            <td style="padding: 10px 0 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #475569;">
                Accédez à des outils de recherche avancés
            </td>
        </tr>
        <tr>
            <td style="padding: 10px 0; vertical-align: top; width: 28px;">
                <span style="color: #F6475F; font-weight: 700;">→</span>
            </td>
            <td style="padding: 10px 0 10px 12px; font-size: 14px; color: #475569;">
                Rejoignez une communauté de propriétaires et de locataires
            </td>
        </tr>
    </table>

    <div class="btn-wrapper">
        <a href="{{ $actionUrl }}" class="btn">Accepter l'invitation</a>
    </div>

    <p class="fallback">
        Si le bouton ne fonctionne pas,
        <a href="{{ $actionUrl }}" class="link">cliquez ici</a>.
    </p>

    <p class="text" style="margin-top: 28px; font-size: 13px; color: #94a3b8;">
        Cette invitation expirera dans <strong style="color: #64748b;">{{ $expiresInDays }} jours</strong>.
        Après cette date, vous devrez demander un nouveau lien.
    </p>

@endsection
