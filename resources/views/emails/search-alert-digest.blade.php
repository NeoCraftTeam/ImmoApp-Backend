@extends('emails.layout')

@section('title', 'Vos alertes immobilières — KeyHome')

@section('content')

    <h1>Bonjour {{ $recipientFirstname }} 👋</h1>

    <p class="text">
        @if($totalAds === 1)
            <strong>1 nouvelle annonce correspond</strong> à vos alertes de recherche.
        @else
            <strong>{{ $totalAds }} nouvelles annonces correspondent</strong> à vos alertes de recherche.
        @endif
    </p>

    @foreach($enrichedGroups as $group)
        @php
            /** @var \App\Models\SearchAlert $alert */
            $alert = $group['alert'];
            $alertLabel = $alert->label ?? trim(implode(' à ', array_filter([
                $alert->type_name,
                $alert->city_name,
                $alert->price_max ? 'max ' . number_format($alert->price_max, 0, ',', ' ') . ' FCFA' : null,
            ])));
            if (!$alertLabel) $alertLabel = 'Alerte immobilière';
        @endphp

        {{-- Alert section header --}}
        <table style="width:100%;border-collapse:collapse;margin-top:32px;">
            <tr>
                <td style="padding:0 0 10px 0;">
                    <span style="display:inline-block;background:#F6475F;color:#fff;font-size:11px;font-weight:700;padding:3px 12px;border-radius:20px;letter-spacing:0.5px;text-transform:uppercase;">
                        Alerte
                    </span>
                    <span style="font-size:16px;font-weight:800;color:#0f172a;margin-left:10px;">
                        {{ $alertLabel }}
                    </span>
                </td>
            </tr>
            @if($group['summary'])
            <tr>
                <td style="padding:0 0 16px 0;">
                    <p style="margin:0;font-size:14px;color:#475569;font-style:italic;line-height:1.6;">
                        {{ $group['summary'] }}
                    </p>
                </td>
            </tr>
            @endif
        </table>

        {{-- Ad cards for this alert --}}
        @foreach($group['formattedAds'] as $ad)
        <table style="width:100%;border-collapse:collapse;margin-bottom:12px;background:#f8fafc;border-radius:10px;overflow:hidden;border:1px solid #e2e8f0;">
            <tr>
                <td style="padding:18px 20px;">
                    <table style="width:100%;border-collapse:collapse;">
                        <tr>
                            <td>
                                <span style="font-size:16px;font-weight:700;color:#0f172a;line-height:1.4;">
                                    {{ $ad['title'] }}
                                </span>
                            </td>
                            <td style="text-align:right;white-space:nowrap;padding-left:12px;">
                                <span style="font-size:17px;font-weight:800;color:#F6475F;">
                                    {{ $ad['formattedPrice'] }}
                                </span>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-top:8px;">
                                <table style="border-collapse:collapse;">
                                    <tr>
                                        @if($ad['surface'])
                                        <td style="padding-right:14px;">
                                            <span style="font-size:12px;color:#64748b;">Surface</span><br>
                                            <span style="font-size:13px;font-weight:700;color:#1e293b;">{{ $ad['surface'] }} m²</span>
                                        </td>
                                        @endif
                                        @if($ad['bedrooms'])
                                        <td style="padding-right:14px;">
                                            <span style="font-size:12px;color:#64748b;">Chambres</span><br>
                                            <span style="font-size:13px;font-weight:700;color:#1e293b;">{{ $ad['bedrooms'] }}</span>
                                        </td>
                                        @endif
                                        @if($ad['city'])
                                        <td>
                                            <span style="font-size:12px;color:#64748b;">Localisation</span><br>
                                            <span style="font-size:13px;font-weight:700;color:#1e293b;">
                                                📍 {{ $ad['quarter'] ? $ad['quarter'].', ' : '' }}{{ $ad['city'] }}
                                            </span>
                                        </td>
                                        @endif
                                    </tr>
                                </table>
                            </td>
                        </tr>
                        <tr>
                            <td colspan="2" style="padding-top:14px;">
                                <a href="{{ $ad['url'] }}"
                                   style="display:inline-block;background:#F6475F;color:#fff;font-size:13px;font-weight:700;padding:8px 20px;border-radius:8px;text-decoration:none;">
                                    Voir l'annonce →
                                </a>
                            </td>
                        </tr>
                    </table>
                </td>
            </tr>
        </table>
        @endforeach

        @if($group['extraCount'] > 0)
        <p style="font-size:13px;color:#64748b;margin:4px 0 0 0;">
            + {{ $group['extraCount'] }} autre{{ $group['extraCount'] > 1 ? 's' : '' }} annonce{{ $group['extraCount'] > 1 ? 's' : '' }} pour cette alerte.
        </p>
        @endif

    @endforeach

    {{-- Global CTA --}}
    <div class="btn-wrapper" style="margin-top:32px;">
        <a href="{{ config('app.frontend_url') }}/search-alerts" class="btn">
            Gérer mes alertes
        </a>
    </div>

    <p class="text" style="margin-top:24px;font-size:12px;color:#94a3b8;text-align:center;">
        Vous recevez ce résumé car vous avez des alertes de recherche actives sur KeyHome.<br>
        Vous pouvez modifier la fréquence ou désactiver vos alertes dans vos paramètres.
    </p>

@endsection
