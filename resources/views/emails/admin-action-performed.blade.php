@extends('emails.layout')

@section('title', 'Confirmation de votre action')

@section('content')

    <h1>Action effectuée avec succès</h1>

    <p class="text">
        Bonjour <strong>{{ $actorName }}</strong>,
    </p>

    <p class="text">
        Nous vous confirmons que vous avez effectué l'action suivante sur la plateforme KeyHome :
    </p>

    {{-- Event badge --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="margin-top: 24px; border-collapse: collapse;">
        <tr>
            <td align="center">
                @php
                    $badgeColors = match($event) {
                        'created'  => ['bg' => '#f0fdf4', 'color' => '#15803d', 'border' => '#86efac'],
                        'updated'  => ['bg' => '#fffbeb', 'color' => '#b45309', 'border' => '#fcd34d'],
                        'deleted'  => ['bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fca5a5'],
                        'approved' => ['bg' => '#f0fdf4', 'color' => '#15803d', 'border' => '#86efac'],
                        'rejected' => ['bg' => '#fef2f2', 'color' => '#dc2626', 'border' => '#fca5a5'],
                        default    => ['bg' => '#f8fafc', 'color' => '#64748b', 'border' => '#cbd5e1'],
                    };
                    $eventLabel = match($event) {
                        'created'  => 'Création',
                        'updated'  => 'Modification',
                        'deleted'  => 'Suppression',
                        'approved' => 'Approuvé',
                        'rejected' => 'Rejeté',
                        default    => ucfirst($event),
                    };
                @endphp
                <span style="
                    display: inline-block;
                    background-color: {{ $badgeColors['bg'] }};
                    color: {{ $badgeColors['color'] }};
                    border: 1px solid {{ $badgeColors['border'] }};
                    border-radius: 20px;
                    padding: 6px 20px;
                    font-size: 13px;
                    font-weight: 700;
                    letter-spacing: 0.3px;
                ">{{ $eventLabel }} — {{ $entity }}</span>
            </td>
        </tr>
    </table>

    {{-- Details card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 24px;
        border-collapse: collapse;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0 0 12px 0; font-size: 11px; font-weight: 700;
                    text-transform: uppercase; letter-spacing: 1px; color: #64748b;">
                    Détails de l'action
                </p>
                <table width="100%" cellpadding="0" cellspacing="0" style="border-collapse: collapse;">
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #64748b;
                            border-bottom: 1px solid #f1f5f9; width: 130px;">Action</td>
                        <td style="padding: 8px 0; font-size: 14px; font-weight: 600;
                            color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $description }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #64748b;
                            border-bottom: 1px solid #f1f5f9;">Entité</td>
                        <td style="padding: 8px 0; font-size: 14px; font-weight: 600;
                            color: #0f172a; border-bottom: 1px solid #f1f5f9;">{{ $entity }} — {{ $entityName }}</td>
                    </tr>
                    <tr>
                        <td style="padding: 8px 0; font-size: 14px; color: #64748b;">Date</td>
                        <td style="padding: 8px 0; font-size: 14px; font-weight: 600;
                            color: #0f172a;">{{ $date }}</td>
                    </tr>
                </table>
            </td>
        </tr>
    </table>

    @if(!empty($changes['old']) || !empty($changes['attributes']))
        {{-- Changes diff --}}
        <table width="100%" cellpadding="0" cellspacing="0" style="
            margin-top: 16px;
            border-collapse: collapse;
            background-color: #fffbeb;
            border: 1px solid #fcd34d;
            border-radius: 8px;
        ">
            <tr>
                <td style="padding: 16px 20px;">
                    <p style="margin: 0 0 12px 0; font-size: 11px; font-weight: 700;
                        text-transform: uppercase; letter-spacing: 1px; color: #b45309;">
                        Modifications apportées
                    </p>
                    @if(!empty($changes['old']))
                        @foreach($changes['old'] as $field => $oldValue)
                            <p style="margin: 4px 0; font-size: 13px; color: #475569;">
                                <strong>{{ $field }}</strong> :
                                <span style="color: #dc2626; text-decoration: line-through;">{{ is_array($oldValue) ? json_encode($oldValue) : $oldValue }}</span>
                                →
                                <span style="color: #15803d; font-weight: 600;">{{ is_array($changes['attributes'][$field] ?? '') ? json_encode($changes['attributes'][$field] ?? '') : ($changes['attributes'][$field] ?? '—') }}</span>
                            </p>
                        @endforeach
                    @elseif(!empty($changes['attributes']))
                        @foreach($changes['attributes'] as $field => $newValue)
                            <p style="margin: 4px 0; font-size: 13px; color: #475569;">
                                <strong>{{ $field }}</strong> :
                                <span style="color: #15803d; font-weight: 600;">{{ is_array($newValue) ? json_encode($newValue) : $newValue }}</span>
                            </p>
                        @endforeach
                    @endif
                </td>
            </tr>
        </table>
    @endif

    <p class="text" style="margin-top: 24px; font-size: 13px; color: #94a3b8;">
        Si vous n'êtes pas à l'origine de cette action, contactez immédiatement l'équipe technique.
    </p>

@endsection
