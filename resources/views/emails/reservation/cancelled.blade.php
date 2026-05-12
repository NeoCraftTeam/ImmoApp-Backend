@extends('emails.layout')

@section('title', 'Visite annulée')

@section('content')

    <h1>Visite annulée</h1>

    <p class="text">
        Bonjour <strong>{{ $notifiable->firstname }}</strong>,
    </p>

    <p class="text">
        La visite pour <strong>« {{ $reservation->ad->title }} »</strong> prévue le
        <strong>{{ $reservation->slot_date->translatedFormat('l d F Y') }}</strong>
        de <strong>{{ $reservation->slot_starts_at }} à {{ $reservation->slot_ends_at }}</strong>
        a été annulée
        @if($cancelledByLabel)
            par <strong>{{ $cancelledByLabel }}</strong>.
        @else
            .
        @endif
    </p>

    {{-- Status badge --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-collapse: collapse;">
        <tr>
            <td align="center">
                <span style="
                    display: inline-block;
                    background-color: #fef2f2;
                    color: #b91c1c;
                    border: 1px solid #fca5a5;
                    border-radius: 20px;
                    padding: 6px 20px;
                    font-size: 13px;
                    font-weight: 700;
                    letter-spacing: 0.3px;
                ">Visite annulée</span>
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
                    Visite annulée
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
                        <td style="padding: 9px 0; font-size: 14px; color: #64748b;">Horaire</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600; color: #0f172a;">
                            {{ $reservation->slot_starts_at }} – {{ $reservation->slot_ends_at }}
                        </td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if($reservation->cancellation_reason)
    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 16px;
        border-collapse: collapse;
        background-color: #fef2f2;
        border-left: 3px solid #ef4444;
        border-radius: 0 8px 8px 0;
    ">
        <tr>
            <td style="padding: 14px 18px;">
                <p style="margin: 0 0 4px 0; font-size: 11px; font-weight: 700;
                    text-transform: uppercase; letter-spacing: 1px; color: #7f1d1d;">
                    Motif d'annulation
                </p>
                <p style="margin: 0; font-size: 14px; color: #7f1d1d; font-style: italic;">
                    « {{ $reservation->cancellation_reason }} »
                </p>
            </td>
        </tr>
    </table>
    @endif

    <div class="btn-wrapper">
        <a href="{{ config('app.frontend_url') }}" class="btn">
            Voir les annonces disponibles
        </a>
    </div>

    <p class="text">
        Vous pouvez chercher d'autres biens disponibles sur KeyHome et réserver un nouveau créneau de visite.
    </p>

    <p class="text">Merci de faire confiance à KeyHome !</p>

@endsection
