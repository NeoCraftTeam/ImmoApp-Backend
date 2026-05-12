@extends('emails.layout')

@section('title', __('emails.appointment_reminder.subject', ['app' => config('app.name')]))

@section('content')

    <h1>{{ __('emails.appointment_reminder.heading') }}</h1>

    <p class="text" style="margin-top: 16px;">
        {!! __('emails.appointment_reminder.intro', ['name' => $user->firstname, 'property' => $propertyTitle]) !!}
    </p>

    <div style="background-color: #f8fafc; border-radius: 10px; padding: 20px 24px; margin: 24px 0; border: 1px solid #e2e8f0;">
        <table style="width: 100%; border-collapse: collapse;">
            <tr>
                <td style="padding: 8px 0; color: #64748b; font-size: 14px;">{{ __('emails.appointment_reminder.date_label') }}</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600; color: #0f172a; font-size: 14px;">{{ $appointmentDate }}</td>
            </tr>
            <tr>
                <td style="padding: 8px 0; color: #64748b; font-size: 14px; border-top: 1px solid #f1f5f9;">{{ __('emails.appointment_reminder.address_label') }}</td>
                <td style="padding: 8px 0; text-align: right; font-weight: 600; color: #0f172a; font-size: 14px; border-top: 1px solid #f1f5f9;">{{ $address }}</td>
            </tr>
        </table>
    </div>

    <div class="btn-wrapper">
        <a href="{{ $detailsUrl }}" class="btn">{{ __('emails.appointment_reminder.cta') }}</a>
    </div>

    <p class="text" style="font-size: 13px; color: #94a3b8; margin-top: 24px;">
        {{ __('emails.appointment_reminder.cancel_note') }}
    </p>

@endsection
