@extends('emails.layout')

@section('title', 'Litige ' . $dispute->reference . ' — ' . $dispute->status->getLabel() . ' — ' . config('app.name'))

@section('preheader', 'Mise à jour du litige ' . $dispute->reference . ' : ' . $dispute->status->getLabel() . '.')

@php
    $isResolved = $dispute->is_resolved ?? in_array($dispute->status->value, ['resolved_initiator', 'resolved_respondent', 'resolved_amicably']);
    $isRejected = $dispute->status->value === 'rejected';
    $heroBg     = $isResolved
        ? 'linear-gradient(135deg, #14532d 0%, #166534 100%)'
        : ($isRejected
            ? 'linear-gradient(135deg, #1e293b 0%, #7f1d1d 100%)'
            : 'linear-gradient(135deg, #1e293b 0%, #78350f 100%)');
@endphp

@section('hero')
    @include('emails.partials.hero', [
        'heroBg'      => $heroBg,
        'heroEyebrow' => 'Litige · ' . $dispute->reference,
        'heroText'    => $dispute->status->getLabel(),
        'heroSub'     => 'Mise à jour du statut de votre litige sur ' . config('app.name'),
    ])
@endsection

@section('content')

    <span class="eyebrow">Mise à jour du litige</span>
    <h1>{{ $dispute->title }}</h1>

    <p class="text" style="margin-top: 16px;">
        Bonjour <strong>{{ $recipient->firstname }}</strong>,<br>
        le statut de votre litige <strong>{{ $dispute->reference }}</strong> a été mis à jour sur
        <strong>{{ config('app.name') }}</strong>.
    </p>

    {{-- New status badge --}}
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
        border-collapse: collapse;
        margin-top: 24px;
        background: {{ $heroBg }};
        border-radius: 10px;
    ">
        <tr>
            <td align="center" style="padding: 20px 24px;">
                <p style="margin: 0 0 4px 0; font-size: 12px; color: rgba(255,255,255,0.65); font-weight: 700; text-transform: uppercase; letter-spacing: 1px;">
                    Nouveau statut
                </p>
                <p style="margin: 0; font-size: 22px; font-weight: 800; color: #ffffff; line-height: 1.2;">
                    {{ $dispute->status->getLabel() }}
                </p>
            </td>
        </tr>
    </table>

    @if($dispute->resolution_note)
    <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="
        border-collapse: collapse;
        margin-top: 20px;
        background-color: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 10px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 8px 0; font-size: 12px; font-weight: 700; color: #166534; text-transform: uppercase; letter-spacing: 0.7px;">
                    Note de résolution
                </p>
                <p style="margin: 0; font-size: 14px; color: #374151; line-height: 1.7;">
                    {{ $dispute->resolution_note }}
                </p>
            </td>
        </tr>
    </table>
    @endif

    @include('emails.partials.button', [
        'url'   => $disputeUrl,
        'label' => 'Voir le détail du litige',
        'color' => $isResolved ? '#166534' : '#C73B52',
        'width' => 260,
    ])

    <p class="text" style="margin-top: 24px; font-size: 13px; color: #6b7280;">
        @if($isResolved)
            Ce litige est désormais clos. Si vous avez des questions, contactez notre support.
        @elseif($isRejected)
            Ce litige a été rejeté. Si vous pensez que cette décision est erronée, contactez notre support.
        @else
            Notre équipe continue de traiter votre litige. Vous serez notifié à chaque étape.
        @endif
    </p>

@endsection
