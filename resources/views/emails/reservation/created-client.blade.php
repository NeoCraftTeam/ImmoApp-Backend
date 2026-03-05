@extends('emails.layout')

@section('title', 'Votre demande de visite a bien été reçue')

@section('content')

    <h1>Demande de visite envoyée ✓</h1>

    <p class="text">
        Bonjour <strong>{{ $notifiable->firstname }}</strong>,
    </p>

    <p class="text">
        Votre demande de visite pour <strong>« {{ $reservation->ad->title }} »</strong> a bien été transmise au propriétaire.
        Vous serez notifié dès qu'il aura confirmé ou refusé.
    </p>

    {{-- Status badge --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-collapse: collapse;">
        <tr>
            <td align="center">
                <span style="
                    display: inline-block;
                    background-color: #fffbeb;
                    color: #b45309;
                    border: 1px solid #fcd34d;
                    border-radius: 20px;
                    padding: 6px 20px;
                    font-size: 13px;
                    font-weight: 700;
                    letter-spacing: 0.3px;
                ">En attente de confirmation</span>
            </td>
        </tr>
    </table>

    {{-- Info card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 24px;
        border-collapse: collapse;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 10px;
    ">
        <tr>
            <td style="padding: 20px 24px;">
                <p style="margin: 0 0 14px 0; font-size: 11px; font-weight: 700;
                    text-transform: uppercase; letter-spacing: 1px; color: #64748b;">
                    Détails de la visite
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td style="padding: 9px 0; font-size: 14px; color: #64748b;
                            border-bottom: 1px solid #f1f5f9; width: 110px;"> Annonce</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600;
                            color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $reservation->ad->title }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 0; font-size: 14px; color: #64748b;
                            border-bottom: 1px solid #f1f5f9;">Date</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600;
                            color: #0f172a; border-bottom: 1px solid #f1f5f9;">
                            {{ $reservation->slot_date->translatedFormat('l d F Y') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 0; font-size: 14px; color: #64748b;
                            border-bottom: 1px solid #f1f5f9;">Horaire</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600;
                            color: #0f172a; border-bottom: 1px solid #f1f5f9;">
                            {{ $reservation->slot_starts_at }} – {{ $reservation->slot_ends_at }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 0; font-size: 14px; color: #64748b;"> Expire le</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600; color: #0f172a;">
                            {{ $reservation->expires_at->translatedFormat('l d F Y à H:i') }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($reservation->client_message)
    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 16px;
        border-collapse: collapse;
        background-color: #f8fafc;
        border-left: 3px solid #F6475F;
        border-radius: 0 8px 8px 0;
    ">
        <tr>
            <td style="padding: 14px 18px;">
                <p style="margin: 0 0 4px 0; font-size: 11px; font-weight: 700;
                    text-transform: uppercase; letter-spacing: 1px; color: #64748b;">
                    Votre message
                </p>
                <p style="margin: 0; font-size: 14px; color: #475569; font-style: italic;">
                    « {{ $reservation->client_message }} »
                </p>
            </td>
        </tr>
    </table>
    @endif

    <div class="btn-wrapper">
        <a href="{{ config('app.frontend_url') }}/my/reservations" class="btn">
            Voir mes visites
        </a>
    </div>

    <p class="text">
        Le propriétaire dispose de <strong>24 heures</strong> pour confirmer votre demande.
        Pensez à rester disponible sur ce créneau.
    </p>

    <p class="text">Merci de faire confiance à KeyHome !</p>

@endsection
