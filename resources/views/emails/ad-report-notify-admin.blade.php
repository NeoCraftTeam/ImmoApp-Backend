@extends('emails.layout')

@section('title', 'Nouveau signalement annonce')

@section('content')
    <h1>Nouveau signalement annonce</h1>

    <p class="text">
        Bonjour {{ $recipient->firstname }}, une annonce a ete signalee par un utilisateur et requiert
        une verification dans le panel administrateur.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 20px;
        border-collapse: collapse;
        background-color: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 8px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin:0; font-size:11px; font-weight:700; text-transform:uppercase; letter-spacing:1px; color:#9a3412;">
                    Signalement
                </p>
                <p style="margin:6px 0 0 0; font-size:14px; color:#0f172a;">
                    Annonce: <strong>{{ $report->ad->title }}</strong>
                </p>
                <p style="margin:6px 0 0 0; font-size:14px; color:#0f172a;">
                    Signale par: <strong>{{ $report->reporter->fullname }}</strong>
                </p>
                <p style="margin:6px 0 0 0; font-size:14px; color:#0f172a;">
                    Motif: <strong>{{ $report->reason->getLabel() }}</strong>
                </p>
                @if($report->description)
                    <p style="margin:10px 0 0 0; font-size:13px; color:#475569;">
                        Note: {{ $report->description }}
                    </p>
                @endif
            </td>
        </tr>
    </table>

    <div class="btn-wrapper">
        <a href="{{ $reviewUrl }}" class="btn">Traiter le signalement</a>
    </div>
@endsection

