{{--
    A single property, as a card: photo, price, title, location, one link.

    Property mail lives or dies on the photo — a text-only reminder gets
    skimmed, a picture of the flat someone already looked at gets clicked. The
    markup is table-based and the image carries width/height attributes so
    Outlook reserves the box before the download finishes.

    The image is the ORIGINAL upload, not the `thumb` conversion: `thumb` is
    WebP, which Outlook desktop and several mobile clients still refuse to
    render. `width="552"` scales it down in place.

    Variables:
      $ad    — plain array, never an Eloquent model (this renders inside a
               queued job; a lazy relation here would mean an N+1 per mail):
               ['title', 'price', 'location', 'url', 'image' => ?string]
      $theme — tokens from App\Support\MailTheme (defaults to the client palette)
--}}
@php
    $t = $theme ?? \App\Support\MailTheme::client();
    $sans = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif";
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:separate;border-spacing:0;margin:0 0 16px 0;
              background-color:#ffffff;border:1px solid {{ $t['border'] }};
              border-radius:12px;overflow:hidden;
              mso-table-lspace:0;mso-table-rspace:0;">
    @if(!empty($ad['image']))
    <tr>
        <td style="padding:0;line-height:0;">
            <a href="{{ $ad['url'] }}" style="text-decoration:none;display:block;">
                <img src="{{ $ad['image'] }}"
                     alt="{{ $ad['title'] }}"
                     width="552" height="184"
                     style="display:block;width:100%;max-width:552px;height:184px;
                            object-fit:cover;border:0;outline:none;text-decoration:none;
                            background-color:{{ $t['surface'] }};" />
            </a>
        </td>
    </tr>
    @endif
    <tr>
        <td style="padding:16px 20px 18px 20px;">
            <p style="margin:0;font-family:{{ $sans }};font-size:19px;font-weight:800;
                      color:{{ $t['link'] }};letter-spacing:-0.2px;">
                {{ $ad['price'] }}
            </p>
            <p style="margin:6px 0 0 0;font-family:{{ $sans }};font-size:15px;
                      font-weight:600;color:#0f172a;line-height:1.4;">
                <a href="{{ $ad['url'] }}" style="color:#0f172a;text-decoration:none;">
                    {{ $ad['title'] }}
                </a>
            </p>
            @if(!empty($ad['location']))
            <p style="margin:4px 0 0 0;font-family:{{ $sans }};font-size:13px;color:#64748b;">
                {{ $ad['location'] }}
            </p>
            @endif
            <p style="margin:14px 0 0 0;font-family:{{ $sans }};font-size:14px;font-weight:600;">
                <a href="{{ $ad['url'] }}" style="color:{{ $t['link'] }};text-decoration:none;">
                    {{ $cardCta ?? __('emails.components.see_listing') }} &rarr;
                </a>
            </p>
        </td>
    </tr>
</table>
