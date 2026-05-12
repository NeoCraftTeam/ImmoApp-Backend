@extends('emails.layout')

@section('title', __('emails.post_viewing_feedback.subject', ['app' => config('app.name')]))

@section('content')

    <h1>{{ __('emails.post_viewing_feedback.heading') }}</h1>

    <p class="text" style="margin-top: 16px;">
        {!! __('emails.post_viewing_feedback.intro', ['name' => $user->firstname, 'property' => $propertyTitle]) !!}
    </p>

    <div class="btn-wrapper">
        <a href="{{ $feedbackUrl }}" class="btn">{{ __('emails.post_viewing_feedback.cta') }}</a>
    </div>

    <p class="text" style="margin-top: 24px; color: #64748b;">
        {{ __('emails.post_viewing_feedback.alternative') }}
    </p>

    <div class="btn-wrapper">
        <a href="{{ $browseUrl }}" class="btn" style="background: #64748b;">{{ __('emails.post_viewing_feedback.browse_cta') }}</a>
    </div>

@endsection
