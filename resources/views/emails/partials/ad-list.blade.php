{{--
    A run of property cards under one heading, or nothing at all.

    The guard matters: a retention mail whose whole point is "here are the flats
    you looked at" must not ship an empty heading over blank space when the ads
    could not be resolved (deleted, unpublished, or the query came back short).
    Callers can then always `@include` this and let the data decide.

    Variables:
      $ads       — list of the plain arrays `ad-card` expects (see that partial)
      $listTitle — optional heading above the run
      $theme     — tokens from App\Support\MailTheme (defaults to the client palette)
      $cardCta   — optional override for the per-card link label
--}}
@php
    $t = $theme ?? \App\Support\MailTheme::client();
    $sans = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif";
    $items = collect($ads ?? [])->filter()->all();
@endphp
@if(! empty($items))
    @if(! empty($listTitle))
    <p style="margin:28px 0 14px 0;font-family:{{ $sans }};font-size:16px;
              font-weight:800;color:#0f172a;line-height:1.4;">
        {{ $listTitle }}
    </p>
    @endif
    @foreach($items as $item)
        @include('emails.partials.ad-card', [
            'ad' => $item,
            'theme' => $t,
            'cardCta' => $cardCta ?? null,
        ])
    @endforeach
@endif
