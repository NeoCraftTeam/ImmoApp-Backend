@extends('emails.layout')

@section('title', 'Nouvelle annonce à valider')

@section('content')

    <h1>Nouvelle annonce à valider</h1>

    <p class="text">Bonjour,</p>

    <p class="text">
        Une nouvelle annonce vient d'être soumise sur la plateforme et attend votre validation.
    </p>

    {{-- Author card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
            margin-top: 24px;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 10px 0; font-size: 11px; font-weight: 700;
                               text-transform: uppercase; letter-spacing: 1px; color: #64748b;">
                    Annonceur
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #64748b;
                                       border-bottom: 1px solid #f1f5f9; width: 90px;">Nom</td>
                        <td style="padding: 6px 0; font-size: 14px; font-weight: 600;
                                       color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $authorName }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #64748b;
                                       border-bottom: 1px solid #f1f5f9;">Email</td>
                        <td style="padding: 6px 0; font-size: 14px; color: #0f172a;
                                       border-bottom: 1px solid #f1f5f9;">{{ $authorEmail }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #64748b;
                                       border-bottom: 1px solid #f1f5f9;">Rôle</td>
                        <td style="padding: 6px 0; font-size: 14px; color: #0f172a;
                                       border-bottom: 1px solid #f1f5f9;">{{ $authorRole }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Type</td>
                        <td style="padding: 6px 0; font-size: 14px; color: #0f172a;">{{ $authorType }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    {{-- Ad card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
            margin-top: 16px;
            border-collapse: collapse;
            background-color: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
        ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 10px 0; font-size: 11px; font-weight: 700;
                               text-transform: uppercase; letter-spacing: 1px; color: #64748b;">
                    Annonce
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #64748b;
                                       border-bottom: 1px solid #f1f5f9; width: 90px;">Titre</td>
                        <td style="padding: 6px 0; font-size: 14px; font-weight: 600;
                                       color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $adTitle }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #64748b;
                                       border-bottom: 1px solid #f1f5f9;">Prix</td>
                        <td style="padding: 6px 0; font-size: 14px; font-weight: 700;
                                       color: #F6475F; border-bottom: 1px solid #f1f5f9;">{{ $adPrice }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #64748b;
                                       border-bottom: 1px solid #f1f5f9;">Type</td>
                        <td style="padding: 6px 0; font-size: 14px; color: #0f172a;
                                       border-bottom: 1px solid #f1f5f9;">{{ $adType }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 6px 0; font-size: 14px; color: #64748b;">Quartier</td>
                        <td style="padding: 6px 0; font-size: 14px; color: #0f172a;">{{ $adQuarter }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    <div class="btn-wrapper">
        <a href="{{ $url }}" class="btn">
            Valider l'annonce dans le panneau admin
        </a>
    </div>

    <p class="text" style="font-size: 12px; color: #64748b;">
        Cet email est une notification automatique réservée aux administrateurs.
    </p>

@endsection
