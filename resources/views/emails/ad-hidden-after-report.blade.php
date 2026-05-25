@extends('emails.owner-layout')

@section('title', 'Votre annonce a été masquée')

@section('preheader', 'Votre annonce a été temporairement masquée suite à un signalement — vous pouvez la modifier et la resoumettre.')

@section('content')

    <h1>Votre annonce a été masquée</h1>

    <p class="text">
        Bonjour <strong>{{ $ownerName }}</strong>,
    </p>

    <p class="text">
        Nous vous informons que votre annonce <strong>« {{ $adTitle }} »</strong> a été
        masquée par notre équipe de modération suite à un signalement reçu.
        Elle n'apparaît plus dans les résultats de recherche.
    </p>

    {{-- Reason box --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
        style="margin-top: 24px; margin-bottom: 24px; border-collapse: collapse;">
        <tr>
            <td style="
                background-color: #fffbeb;
                border: 1px solid #fcd34d;
                border-left: 4px solid #f59e0b;
                border-radius: 8px;
                padding: 20px 24px;
                font-size: 14px;
                color: #1e293b;
                line-height: 1.7;
            ">
                <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 700;
                    text-transform: uppercase; letter-spacing: 0.8px; color: #92400e;">
                    Motif communiqué par notre équipe
                </p>
                <p style="margin: 0;">{{ $reason }}</p>
            </td>
        </tr>
    </table>

    <p class="text">
        Vous pouvez corriger votre annonce en tenant compte de ces remarques.
        Une fois les modifications apportées, votre annonce repassera en validation.
    </p>

    @include('emails.partials.button', [
        'url'   => $manageUrl,
        'label' => 'Modifier mon annonce',
        'color' => '#0d9488',
        'width' => 240,
    ])

    <p class="text" style="margin-top: 32px;">
        Si vous pensez qu'il s'agit d'une erreur ou si vous avez des questions,
        n'hésitez pas à nous contacter en répondant à cet email.
    </p>

@endsection
