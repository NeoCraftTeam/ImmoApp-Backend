@extends('emails.layout')

@section('title', 'Votre annonce est publiée')

@section('content')

    <h1>Votre annonce est publiée</h1>

    <p class="text">
        Bonjour <strong>{{ $authorName }}</strong>,
    </p>

    <p class="text">
        Excellente nouvelle — votre annonce a été <strong>validée</strong> par notre équipe de
        modération et est désormais <strong>visible par tous les utilisateurs</strong> de la plateforme.
    </p>

    {{-- Status badge --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-collapse: collapse;">
        <tr>
            <td align="center">
                <span style="
                        display: inline-block;
                        background-color: #f0fdf4;
                        color: #15803d;
                        border: 1px solid #86efac;
                        border-radius: 20px;
                        padding: 6px 20px;
                        font-size: 13px;
                        font-weight: 700;
                        letter-spacing: 0.3px;
                    ">Publiée et visible</span>
            </td>
        </tr>
    </table>

    {{-- Ad recap card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
            margin-top: 24px;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 12px 0; font-size: 11px; font-weight: 700;
                               text-transform: uppercase; letter-spacing: 1px; color: #64748b;">
                    Récapitulatif
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #64748b;
                                       border-bottom: 1px solid #f1f5f9; width: 80px;">Titre</td>
                        <td style="padding: 8px 0; font-size: 14px; font-weight: 600;
                                       color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $adTitle }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #64748b;">Prix</td>
                        <td style="padding: 8px 0; font-size: 14px; font-weight: 700;
                                       color: #F6475F;">{{ $adPrice }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="btn-wrapper">
        <a href="{{ config('app.frontend_url') }}" class="btn">
            Voir mon annonce en ligne
        </a>
    </div>

    <p class="text">
        Merci de votre confiance et bonne publication sur KeyHome.
    </p>

@endsection
