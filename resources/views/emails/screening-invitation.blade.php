@extends('emails.layout')

@section('title', 'Votre dossier locataire sur ' . config('app.name'))

@section('preheader', 'Votre bailleur vous invite à constituer votre dossier locataire en ligne — quelques documents à téléverser en toute sécurité.')

@section('content')

    <h1>Constituez votre dossier locataire</h1>

    <p class="text">
        Bonjour {{ $tenantName }},
    </p>

    <p class="text">
        Votre bailleur vous invite à constituer votre dossier locataire sur
        {{ config('app.name') }}. Téléversez les documents demandés en toute
        sécurité depuis la page dédiée, puis soumettez votre dossier en un clic.
    </p>

    @if (!empty($requiredDocumentLabels))
        <p class="text" style="margin-top: 24px; font-weight: 600; color: #0f172a;">
            Documents demandés
        </p>
        <table style="width: 100%; border-collapse: collapse; margin-top: 8px;">
            @foreach ($requiredDocumentLabels as $label)
                <tr>
                    <td style="padding: 10px 0; border-bottom: 1px solid #f1f5f9; vertical-align: top; width: 28px;">
                        <span style="color: #F6475F; font-weight: 700;">→</span>
                    </td>
                    <td style="padding: 10px 0 10px 12px; border-bottom: 1px solid #f1f5f9; font-size: 14px; color: #475569;">
                        {{ $label }}
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    @if (!empty($landlordNotes))
        <p class="text" style="margin-top: 24px; padding: 16px; background-color: #f8fafc; border-radius: 8px; border-left: 3px solid #F6475F; font-size: 14px;">
            <strong style="color: #0f172a;">Note de votre bailleur :</strong><br>
            {{ $landlordNotes }}
        </p>
    @endif

    @include('emails.partials.button', [
        'url'   => $actionUrl,
        'label' => 'Constituer mon dossier',
        'width' => 260,
    ])

    <p class="fallback">
        Si le bouton ne fonctionne pas,
        <a href="{{ $actionUrl }}" class="link">accédez à votre dossier directement</a>.
    </p>

    <p class="text" style="margin-top: 28px; font-size: 13px; color: #94a3b8;">
        Ce lien expirera dans <strong style="color: #64748b;">{{ $expiresInDays }} jours</strong>.
        Vos documents sont transmis de façon sécurisée et ne servent qu'à
        l'étude de votre dossier.
    </p>

@endsection
