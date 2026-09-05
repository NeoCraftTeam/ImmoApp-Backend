{{--
    A number worth looking at, boxed in the audience's tint.

    Extracted from `engagement/abandoned-search.blade.php`, which had it inlined
    with hard-coded green — so a landlord mail and a client mail showed the same
    colour and the two spaces bled into each other.

    Variables:
      $value — the figure itself (already formatted)
      $label — one short line under it
      $theme — tokens from App\Support\MailTheme (defaults to the client palette)
--}}
@php
    $t = $theme ?? \App\Support\MailTheme::client();
    $sans = "-apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif";
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:separate;border-spacing:0;margin:24px 0;
              mso-table-lspace:0;mso-table-rspace:0;">
    <tr>
        <td align="center"
            style="background-color:{{ $t['tintBg'] }};border:1px solid {{ $t['tintBorder'] }};
                   border-radius:12px;padding:20px 24px;mso-padding-alt:20px 24px;">
            <p style="margin:0;font-family:{{ $sans }};font-size:32px;font-weight:800;
                      color:{{ $t['tintText'] }};line-height:1.1;">
                {{ $value }}
            </p>
            <p style="margin:6px 0 0 0;font-family:{{ $sans }};font-size:14px;
                      font-weight:600;color:{{ $t['tintText'] }};line-height:1.45;">
                {{ $label }}
            </p>
        </td>
    </tr>
</table>
