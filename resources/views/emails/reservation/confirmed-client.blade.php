@extends('emails.layout')

@section('title', 'Votre visite est confirmée')

@section('content')

    <h1>Votre visite est confirmée </h1>

    <p class="text">
        Bonjour <strong>{{ $notifiable->firstname }}</strong>,
    </p>

    <p class="text">
        Bonne nouvelle ! <strong>{{ $reservation->ad->user->firstname ?? 'Le propriétaire' }}</strong> a confirmé votre visite pour
        <strong>« {{ $reservation->ad->title }} »</strong>.
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
                ">Visite confirmée</span>
            </td>
        </tr>
    </table>

    {{-- Info card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 24px;
        border-collapse: collapse;
        background-color: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 10px;
    ">
        <tr>
            <td style="padding: 20px 24px;">
                <p style="margin: 0 0 14px 0; font-size: 11px; font-weight: 700;
                    text-transform: uppercase; letter-spacing: 1px; color: #166534;">
                    Récapitulatif confirmé
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td style="padding: 9px 0; font-size: 14px; color: #166534;
                            border-bottom: 1px solid #dcfce7; width: 110px;">Annonce</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600;
                            color: #14532d; border-bottom: 1px solid #dcfce7;">{{ $reservation->ad->title }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 0; font-size: 14px; color: #166534;
                            border-bottom: 1px solid #dcfce7;">Date</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600;
                            color: #14532d; border-bottom: 1px solid #dcfce7;">
                            {{ $reservation->slot_date->translatedFormat('l d F Y') }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 0; font-size: 14px; color: #166534;">Horaire</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600; color: #14532d;">
                            {{ $reservation->slot_starts_at }} – {{ $reservation->slot_ends_at }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($reservation->landlord_notes)
    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 16px;
        border-collapse: collapse;
        background-color: #f8fafc;
        border-left: 3px solid #0D9488;
        border-radius: 0 8px 8px 0;
    ">
        <tr>
            <td style="padding: 14px 18px;">
                <p style="margin: 0 0 4px 0; font-size: 11px; font-weight: 700;
                    text-transform: uppercase; letter-spacing: 1px; color: #64748b;">
                    Note du propriétaire
                </p>
                <p style="margin: 0; font-size: 14px; color: #475569; font-style: italic;">
                    « {{ $reservation->landlord_notes }} »
                </p>
            </td>
        </tr>
    </table>
    @endif

    {{-- Tips block --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 24px;
        border-collapse: collapse;
        background-color: #fefce8;
        border: 1px solid #fde68a;
        border-radius: 10px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 700; color: #854d0e;">
                     Conseils pour votre visite
                </p>
                <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #713f12; line-height: 1.8;">
                    <li>Présentez-vous à l'heure convenue.</li>
                    <li>Apportez une pièce d'identité valide.</li>
                    <li>N'hésitez pas à poser vos questions au propriétaire.</li>
                </ul>
            </td>
        </tr>
    </table>

    <div class="btn-wrapper">
        <a href="{{ config('app.frontend_url') }}/my/reservations" class="btn">
            Voir mes réservations
        </a>
    </div>

    <p class="text">Merci de faire confiance à KeyHome !</p>

@endsection
