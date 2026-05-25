@extends('emails.owner-layout')

@section('title', 'Visite confirmée')

@section('preheader', 'Vous avez confirmé la visite — ajoutez-la à votre calendrier et préparez votre bien.')

@section('content')

    <h1>Vous avez confirmé la visite </h1>

    <p class="text">
        Bonjour <strong>{{ $notifiable->firstname }}</strong>,
    </p>

    <p class="text">
        Vous avez confirmé la visite de <strong>{{ $reservation->client->firstname }} {{ $reservation->client->lastname }}</strong>
        pour <strong>« {{ $reservation->ad->title }} »</strong>.
        Le locataire a été notifié.
    </p>

    {{-- Status badge --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-collapse: collapse;">
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
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
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
                    Récapitulatif
                </p>
                <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td style="padding: 9px 0; font-size: 14px; color: #166534;
                            border-bottom: 1px solid #dcfce7; width: 110px;">Annonce</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600;
                            color: #14532d; border-bottom: 1px solid #dcfce7;">{{ $reservation->ad->title }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 9px 0; font-size: 14px; color: #166534;
                            border-bottom: 1px solid #dcfce7;">Visiteur</td>
                        <td style="padding: 9px 0; font-size: 14px; font-weight: 600;
                            color: #14532d; border-bottom: 1px solid #dcfce7;">
                            {{ $reservation->client->firstname }} {{ $reservation->client->lastname }}
                        </td>
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

    @if($reservation->client->phone_number)
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 16px;
        border-collapse: collapse;
        background-color: #f8fafc;
        border-left: 3px solid #0d9488;
        border-radius: 0 8px 8px 0;
    ">
        <tr>
            <td style="padding: 14px 18px;">
                <p style="margin: 0 0 4px 0; font-size: 11px; font-weight: 700;
                    text-transform: uppercase; letter-spacing: 1px; color: #64748b;">
                    Contact du visiteur
                </p>
                <p style="margin: 0; font-size: 14px; color: #0f172a; font-weight: 600;">
                    📞 {{ $reservation->client->phone_number }}
                    @if($reservation->client->email)
                        &nbsp;·&nbsp; ✉️ {{ $reservation->client->email }}
                    @endif
                </p>
            </td>
        </tr>
    </table>
    @endif

    {{-- Calendar links block --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 24px;
        border-collapse: collapse;
        background-color: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 10px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 12px 0; font-size: 13px; font-weight: 700; color: #1d4ed8;">
                    📅 Ajouter à votre calendrier
                </p>
                <p style="margin: 0 0 12px 0; font-size: 13px; color: #1e40af;">
                    Ne manquez pas ce rendez-vous — ajoutez-le à votre agenda en un clic.
                </p>
                @php
                    $slotDate  = $reservation->slot_date->format('Y-m-d');
                    $startTime = str_replace(':', '', substr((string)$reservation->slot_starts_at, 0, 5));
                    $endTime   = str_replace(':', '', substr((string)$reservation->slot_ends_at,   0, 5));
                    $title     = rawurlencode('Visite — '.$reservation->ad->title);
                    $location  = rawurlencode(implode(', ', array_filter([
                        $reservation->ad->quarter?->name,
                        $reservation->ad->quarter?->city?->name,
                        'Cameroun',
                    ])));
                    $details   = rawurlencode('Visiteur : '.$reservation->client->firstname.' '.$reservation->client->lastname."\nKeyHome — keyhome.app");
                    $gcalStart = str_replace('-', '', $slotDate).'T'.$startTime.'00';
                    $gcalEnd   = str_replace('-', '', $slotDate).'T'.$endTime.'00';
                    $gcalUrl   = "https://calendar.google.com/calendar/render?action=TEMPLATE&text={$title}&dates={$gcalStart}/{$gcalEnd}&details={$details}&location={$location}";
                    $outlookUrl = "https://outlook.live.com/calendar/0/action/compose?subject={$title}&startdt={$slotDate}T".substr((string)$reservation->slot_starts_at,0,5).":00&enddt={$slotDate}T".substr((string)$reservation->slot_ends_at,0,5).":00&body={$details}&location={$location}";
                @endphp
                <table role="presentation" cellpadding="0" cellspacing="0" style="border-collapse:collapse;">
                    <tr>
                        <td style="padding-right: 8px; padding-bottom: 8px;">
                            <a href="{{ $gcalUrl }}" target="_blank" rel="noopener" style="
                                display: inline-block;
                                padding: 8px 16px;
                                background: #fff;
                                border: 1px solid #93c5fd;
                                border-radius: 6px;
                                font-size: 13px;
                                font-weight: 600;
                                color: #1d4ed8;
                                text-decoration: none;
                            ">Google Calendar</a>
                        </td>
                        <td style="padding-right: 8px; padding-bottom: 8px;">
                            <a href="{{ $outlookUrl }}" target="_blank" rel="noopener" style="
                                display: inline-block;
                                padding: 8px 16px;
                                background: #fff;
                                border: 1px solid #93c5fd;
                                border-radius: 6px;
                                font-size: 13px;
                                font-weight: 600;
                                color: #1d4ed8;
                                text-decoration: none;
                            ">Outlook</a>
                        </td>
                    </tr>
                </table>
                <p style="margin: 8px 0 0 0; font-size: 12px; color: #64748b;">
                    Pour Apple Calendar : gérez vos rendez-vous depuis votre espace bailleur KeyHome.
                </p>
            </td>
        </tr>
    </table>

    {{-- Tips --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 24px;
        border-collapse: collapse;
        background-color: #fefce8;
        border: 1px solid #fde68a;
        border-radius: 10px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 8px 0; font-size: 13px; font-weight: 700; color: #854d0e;">
                     Préparez votre bien
                </p>
                <ul style="margin: 0; padding-left: 18px; font-size: 13px; color: #713f12; line-height: 1.8;">
                    <li>Assurez-vous que le bien est propre et accessible.</li>
                    <li>Prévoyez les documents nécessaires (titre de propriété, diagnostics…).</li>
                    <li>Notez le numéro du visiteur en cas d'imprévu.</li>
                </ul>
            </td>
        </tr>
    </table>

    @include('emails.partials.button', [
        'url'   => rtrim(config('app.frontend_url', config('app.url')), '/') . '/owner/viewings',
        'label' => 'Gérer mes visites',
        'color' => '#0d9488',
        'width' => 200,
    ])

    <p class="text">Merci d'utiliser KeyHome !</p>

@endsection
