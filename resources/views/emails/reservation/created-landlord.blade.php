@extends('emails.layout')

@section('title', 'Nouvelle demande de visite')

@section('content')

    <h1>Nouvelle demande de visite</h1>

    <p class="text">
        Bonjour <strong>{{ $notifiable->firstname }}</strong>,
    </p>

    <p class="text">
        <strong>{{ $reservation->client->firstname }} {{ $reservation->client->lastname }}</strong>
        souhaite visiter votre bien <strong>« {{ $reservation->ad->title }} »</strong>.
        Confirmez ou refusez rapidement — la demande expire dans <strong>24 heures</strong>.
    </p>

    {{-- Status badge --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-collapse: collapse;">
        <tr>
            <td align="center">
                <span style="
                    display: inline-block;
                    background-color: #eff6ff;
                    color: #1d4ed8;
                    border: 1px solid #93c5fd;
                    border-radius: 20px;
                    padding: 6px 20px;
                    font-size: 13px;
                    font-weight: 700;
                    letter-spacing: 0.3px;
                ">Nouvelle demande</span>
            </td>
        </tr>
    </table>

    {{-- Visite details card --}}
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
                    Détails de la demande
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
                            border-bottom: 1px solid #f1f5f9;"> Visiteur</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600;
                            color: #0f172a; border-bottom: 1px solid #f1f5f9;">
                            {{ $reservation->client->firstname }} {{ $reservation->client->lastname }}
                        </td>
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
                     Message du visiteur
                </p>
                <p style="margin: 0; font-size: 14px; color: #475569; font-style: italic;">
                    « {{ $reservation->client_message }} »
                </p>
            </td>
        </tr>
    </table>
    @endif

    <div class="btn-wrapper">
        <a href="{{ config('app.url') }}/owner/viewings/viewing-reservations" class="btn">
            Gérer la demande
        </a>
    </div>

    <p class="text">
        Si vous ne répondez pas dans les <strong>24 heures</strong>, la demande expirera automatiquement.
    </p>

    <p class="text">Merci d'utiliser KeyHome !</p>

@endsection
