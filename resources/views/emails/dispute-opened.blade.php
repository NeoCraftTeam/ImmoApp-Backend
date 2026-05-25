@extends('emails.layout')

@section('title', 'Litige ouvert — ' . $dispute->reference . ' — ' . config('app.name'))

@section('preheader', 'Un litige vous concernant a été ouvert sur ' . config('app.name') . ' — répondez dans les meilleurs délais.')

@section('hero')
    @include('emails.partials.hero', [
        'heroBg'      => 'linear-gradient(135deg, #1e293b 0%, #7f1d1d 100%)',
        'heroEyebrow' => 'Litige · ' . $dispute->reference,
        'heroText'    => 'Un litige a été ouvert',
        'heroSub'     => config('app.name') . ' — votre réponse est attendue',
    ])
@endsection

@section('content')

    <span class="eyebrow">Nouveau litige</span>
    <h1>{{ $dispute->title }}</h1>

    <p class="text" style="margin-top: 16px;">
        Bonjour <strong>{{ $recipient->firstname }}</strong>,<br>
        un litige vous concernant a été enregistré sur <strong>{{ config('app.name') }}</strong>.
        Consultez les détails ci-dessous et répondez dans les meilleurs délais.
    </p>

    {{-- Dispute summary card --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
        border-collapse: collapse;
        margin-top: 24px;
        background-color: #fff1f2;
        border: 1px solid #fecdd3;
        border-radius: 10px;
    ">
        <tr>
            <td style="padding: 20px 24px;">
                <table style="width: 100%; border-collapse: collapse;">
                    <tr>
                        <td style="padding: 5px 0; font-size: 12px; font-weight: 700; color: #C73B52; text-transform: uppercase; letter-spacing: 0.7px; width: 120px; vertical-align: top;">
                            Référence
                        </td>
                        <td style="padding: 5px 0; font-size: 14px; color: #1e293b; font-weight: 600;">
                            {{ $dispute->reference }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; font-size: 12px; font-weight: 700; color: #C73B52; text-transform: uppercase; letter-spacing: 0.7px; vertical-align: top;">
                            Type
                        </td>
                        <td style="padding: 5px 0; font-size: 14px; color: #1e293b;">
                            {{ $dispute->type_label ?? $dispute->type->getLabel() }}
                        </td>
                    </tr>
                    <tr>
                        <td style="padding: 5px 0; font-size: 12px; font-weight: 700; color: #C73B52; text-transform: uppercase; letter-spacing: 0.7px; vertical-align: top;">
                            Ouvert le
                        </td>
                        <td style="padding: 5px 0; font-size: 14px; color: #1e293b;">
                            {{ $dispute->created_at->translatedFormat('d F Y') }}
                        </td>
                    </tr>
                    @if($dispute->amount_claimed)
                    <tr>
                        <td style="padding: 5px 0; font-size: 12px; font-weight: 700; color: #C73B52; text-transform: uppercase; letter-spacing: 0.7px; vertical-align: top;">
                            Montant réclamé
                        </td>
                        <td style="padding: 5px 0; font-size: 14px; color: #1e293b; font-weight: 600;">
                            {{ number_format($dispute->amount_claimed, 0, ',', ' ') }} FCFA
                        </td>
                    </tr>
                    @endif
                </table>

                <p style="margin: 16px 0 0 0; font-size: 14px; color: #374151; line-height: 1.7; border-top: 1px solid #fecdd3; padding-top: 14px;">
                    {{ \Illuminate\Support\Str::limit($dispute->description, 300) }}
                </p>
            </td>
        </tr>
    </table>

    @include('emails.partials.button', [
        'url'   => $disputeUrl,
        'label' => 'Consulter le litige',
        'color' => '#C73B52',
        'width' => 240,
    ])

    <p class="text" style="margin-top: 24px; font-size: 13px; color: #6b7280;">
        Notre équipe {{ config('app.name') }} suit l'avancement de ce litige. Le délai de traitement est de
        <strong>7 jours ouvrés</strong>. Si vous avez des preuves à apporter (photos, contrats, reçus),
        déposez-les directement sur la plateforme.
    </p>

@endsection
