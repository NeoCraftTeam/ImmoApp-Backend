@extends('emails.layout')

@section('title', 'Merci pour votre participation')

@section('content')

    <h1>Merci d'avoir répondu au sondage&nbsp;!</h1>

    <p class="text">
        Bonjour <strong>{{ $userName }}</strong>,
    </p>

    <p class="text">
        Nous avons bien reçu vos réponses au sondage
        <strong>« {{ $surveyTitle }} »</strong>.
        Votre avis nous aide à améliorer notre service et nous vous en remercions chaleureusement.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-collapse: collapse;">
        <tr>
            <td align="center">
                <span style="
                    display: inline-block;
                    background-color: #f0fdf4;
                    color: #15803d;
                    border: 1px solid #86efac;
                    border-radius: 20px;
                    padding: 6px 22px;
                    font-size: 13px;
                    font-weight: 700;
                    letter-spacing: 0.3px;
                ">✓ Participation enregistrée</span>
            </td>
        </tr>
    </table>

    <p class="text" style="margin-top: 28px;">
        L'équipe <strong>{{ config('app.name') }}</strong>
    </p>

@endsection
