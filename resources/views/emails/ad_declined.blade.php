@extends('emails.owner-layout')

@section('title', __('emails.ad_declined.heading'))

@section('preheader', 'Votre annonce nécessite des corrections avant publication — consultez le motif et resoumettez-la depuis votre espace bailleur.')

@section('content')

    <h1>{!! __('emails.ad_declined.heading') !!}</h1>

    <p class="text">{!! __('emails.ad_declined.greeting', ['name' => $authorName]) !!}</p>

    <p class="text">{!! __('emails.ad_declined.intro', ['title' => $adTitle]) !!}</p>

    @if($reasonHtml)
        {{-- Rejection reason box rendered from Markdown --}}
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0"
            style="margin-top: 24px; margin-bottom: 24px; border-collapse: collapse;">
            <tr>
                <td style="
                            background-color: #fffbeb;
                            border: 1px solid #fcd34d;
                            border-left: 4px solid #f59e0b;
                            border-radius: 8px;
                            padding: 20px 24px;
                            font-size: 14px;
                            color: #1e293b;
                            line-height: 1.7;
                        ">
                    <p style="margin: 0 0 10px 0; font-size: 12px; font-weight: 700;
                                       text-transform: uppercase; letter-spacing: 0.8px; color: #92400e;">
                        {{ __('emails.ad_declined.reason_label') }}
                    </p>
                    {!! $reasonHtml !!}
                </td>
            </tr>
        </table>
    @endif

    <p class="text">{{ __('emails.ad_declined.instructions') }}</p>

    @include('emails.partials.button', [
        'url'   => rtrim(config('app.frontend_url', config('app.url')), '/') . '/owner/ads',
        'label' => __('emails.ad_declined.cta'),
        'color' => '#0d9488',
        'width' => 280,
    ])

    <p class="text" style="margin-top: 32px; font-size: 13px; color: #64748b;">
        {{ __('emails.ad_declined.support_note') }}
    </p>

@endsection
