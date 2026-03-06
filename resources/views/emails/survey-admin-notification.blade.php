@extends('emails.layout')

@section('title', 'Nouveau sondage reçu')

@section('content')

    <h1>Nouveau sondage reçu</h1>

    <p class="text">
        Un client a répondu au sondage <strong>« {{ $surveyTitle }} »</strong>.
    </p>

    {{-- Respondent card --}}
    <table width="100%" cellpadding="0" cellspacing="0" style="
        margin-top: 20px;
        border-collapse: collapse;
        background-color: #f8fafc;
        border: 1px solid #e2e8f0;
        border-radius: 8px;
    ">
        <tr>
            <td style="padding: 16px 20px;">
                <p style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin-bottom: 6px;">Répondant</p>
                <p style="font-size: 15px; font-weight: 700; color: #1e293b; margin-bottom: 2px;">{{ $respondentName }}</p>
                <p style="font-size: 13px; color: #64748b;">{{ $respondentEmail }}</p>
            </td>
        </tr>
    </table>

    {{-- Answers recap --}}
    @if (!empty($formattedAnswers))
        <table width="100%" cellpadding="0" cellspacing="0" style="
            margin-top: 20px;
            border-collapse: collapse;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            overflow: hidden;
        ">
            <tr>
                <td style="padding: 12px 20px; background-color: #f1f5f9; border-bottom: 1px solid #e2e8f0;">
                    <p style="font-size: 11px; font-weight: 700; text-transform: uppercase; letter-spacing: 0.8px; color: #64748b; margin: 0;">Réponses</p>
                </td>
            </tr>
            @foreach ($formattedAnswers as $qa)
                <tr>
                    <td style="padding: 12px 20px; {{ !$loop->last ? 'border-bottom: 1px solid #f1f5f9;' : '' }} background-color: #ffffff;">
                        <p style="font-size: 12px; color: #64748b; margin-bottom: 4px;">{{ $qa['question'] }}</p>
                        <p style="font-size: 14px; font-weight: 600; color: #1e293b;">{{ $qa['answer'] }}</p>
                    </td>
                </tr>
            @endforeach
        </table>
    @endif

    <p class="text" style="margin-top: 28px;">
        Connectez-vous sur le panneau d'administration pour consulter l'ensemble des réponses.
    </p>

@endsection
