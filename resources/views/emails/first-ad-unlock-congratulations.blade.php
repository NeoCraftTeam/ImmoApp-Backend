@extends('emails.layout')

@section('title', 'Bravo pour votre premier déblocage')

@section('content')

    <h1>Félicitations, {{ $firstName }} !</h1>

    <p class="text">
        Vous venez de débloquer les coordonnées complètes de votre <strong>première annonce</strong> sur
        {{ config('app.name') }} — une belle étape pour avancer dans votre recherche immobilière.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="
            margin-top: 24px;
            border-collapse: collapse;
            background-color: #fff1f2;
            border: 1px solid #fecdd3;
            border-radius: 10px;
        ">
        <tr>
            <td style="padding: 18px 20px;">
                <p style="margin: 0 0 6px 0; font-size: 11px; font-weight: 700; text-transform: uppercase;
                    letter-spacing: 0.8px; color: #be123c;">Annonce débloquée</p>
                <p style="margin: 0; font-size: 16px; font-weight: 700; color: #0f172a;">{{ $adTitle }}</p>
            </td>
        </tr>
    </table>

    <p class="text" style="margin-top: 20px;">
        Vous pouvez maintenant <strong>contacter le propriétaire ou l’agence</strong>, planifier une visite et poser vos
        questions directement. Pensez à préparer vos critères et vos questions avant l’échange pour gagner du temps.
    </p>

    <div class="btn-wrapper">
        <a href="{{ $adUrl }}" class="btn">Revoir l’annonce</a>
    </div>

    <p class="text" style="margin-top: 8px;">
        <a href="{{ $searchUrl }}" style="color: #F6475F; font-weight: 600;">Continuer à explorer les annonces →</a>
    </p>

    <p class="text" style="margin-top: 24px;">
        Merci de faire confiance à <strong>{{ config('app.name') }}</strong>. Bonne recherche !
    </p>

@endsection
