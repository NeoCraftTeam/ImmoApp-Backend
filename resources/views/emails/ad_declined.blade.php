@extends('emails.layout')

@section('title', 'Votre annonce n\'a pas été publiée')

@section('content')

    <h1>Votre annonce n'a pas été publiée</h1>

    <p class="text">
        Bonjour <strong>{{ $authorName }}</strong>,
    </p>

    <p class="text">
        Nous vous informons que votre annonce <strong>« {{ $adTitle }} »</strong> n'a pas pu
        être publiée à l'issue de notre processus de modération.
    </p>

    @if($reasonHtml)
        {{-- Rejection reason box rendered from Markdown --}}
        <table width="100%" cellpadding="0" cellspacing="0"
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
                    {!! $reasonHtml !!}
                </td>
            </tr>
        </table>
    @endif

    <p class="text">
        Vous pouvez corriger votre annonce en tenant compte de ces remarques et la
        resoumettre directement depuis votre espace bailleur.
    </p>

    <div class="btn-wrapper">
        <a href="{{ config('app.url') . '/owner' }}" class="btn">
            Modifier et resoumettre mon annonce
        </a>
    </div>

    <p class="text" style="margin-top: 32px;">
        Si vous pensez qu'il s'agit d'une erreur ou si vous avez des questions,
        n'hésitez pas à nous contacter en répondant à cet email.
        Notre équipe se fera un plaisir de vous aider.
    </p>

@endsection
