@extends('emails.layout')

@section('title', __('emails.post_viewing_feedback.subject', ['app' => config('app.name')]))

@section('preheader', 'Comment s\'est passée votre visite ? Partagez votre avis en 30 secondes.')

@section('content')

    <h1>{{ __('emails.post_viewing_feedback.heading') }}</h1>

    <p class="text" style="margin-top: 16px;">
        {!! __('emails.post_viewing_feedback.intro', ['name' => $user->firstname, 'property' => $propertyTitle]) !!}
    </p>

    @include('emails.partials.button', [
        'url'   => $feedbackUrl,
        'label' => __('emails.post_viewing_feedback.cta'),
        'width' => 220,
    ])

    <p class="text" style="margin-top: 24px; color: #64748b;">
        {{ __('emails.post_viewing_feedback.alternative') }}
    </p>

    @include('emails.partials.button', [
        'url'   => $browseUrl,
        'label' => __('emails.post_viewing_feedback.browse_cta'),
        'color' => '#64748b',
        'width' => 220,
    ])

@endsection
