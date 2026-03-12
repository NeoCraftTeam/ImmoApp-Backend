@extends('emails.layout')

@section('title', 'Signalement recu')

@section('content')
    <p style="
        margin: 0 0 10px 0;
        display: inline-block;
        padding: 6px 10px;
        border-radius: 999px;
        background: #fee2e2;
        color: #be123c;
        font-size: 12px;
        font-weight: 700;
        letter-spacing: .3px;
    ">
        SIGNALEMENT ENREGISTRÉ
    </p>

    <h1 style="margin: 0;">Merci, votre signalement est bien recu</h1>

    <p class="text" style="margin-top: 12px;">
        Nous avons bien enregistre votre demande et notre equipe Trust & Safety va l'examiner rapidement
        pour proteger la communaute KeyHome.
    </p>

    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 22px;
        border-collapse: separate;
        border-spacing: 0;
        background-color: #ffffff;
        border: 1px solid #dbe5f0;
        border-radius: 12px;
    ">
        <tr>
            <td style="padding: 18px 20px 14px 20px; border-bottom: 1px solid #eef2f7;">
                <p style="margin: 0; font-size: 12px; font-weight: 700; text-transform: uppercase; letter-spacing: .9px; color: #64748b;">
                    Reference du dossier
                </p>
                <p style="margin: 8px 0 0 0; font-size: 18px; color: #0f172a; font-weight: 700;">
                    {{ $reportReference }}
                </p>
            </td>
        </tr>
        <tr>
            <td style="padding: 14px 20px 18px 20px;">
                <p style="margin: 0; font-size: 14px; color: #475569;">
                    Motif principal:
                    <span style="color:#0f172a; font-weight: 700;">{{ $report->reason->getLabel() }}</span>
                </p>
            </td>
        </tr>
    </table>

    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 16px;
        border-collapse: separate;
        border-spacing: 0;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 12px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="margin: 0; font-size: 13px; font-weight: 700; color: #334155;">
                    Et maintenant ?
                </p>
                <ul style="margin: 10px 0 0 0; padding-left: 18px; color: #475569; font-size: 14px; line-height: 1.7;">
                    <li>Nous analysons ce signalement avec priorite.</li>
                    <li>Si une information complementaire est necessaire, nous vous ecrirons.</li>
                    <li>Votre identite n'est jamais partagee avec le proprietaire.</li>
                </ul>
            </td>
        </tr>
    </table>

    <div class="btn-wrapper" style="margin-top: 24px;">
        <a href="{{ config('app.url') }}" class="btn">Retourner sur KeyHome</a>
    </div>

    <p class="fallback" style="margin-top: 16px;">
        Besoin d'aide ? Ecrivez-nous a
        <a class="link" href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>.
    </p>
@endsection

