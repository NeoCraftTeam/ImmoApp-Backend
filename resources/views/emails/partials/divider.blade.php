{{--
    A hairline between two ideas.

    Templates were drawing this with `<hr>`, which Outlook renders as a thick
    3D-bevelled rule in its own grey, ignoring any colour set on it. A one-cell
    table with a background is the only rule that looks the same everywhere.

    Variables:
      $theme   — tokens from App\Support\MailTheme (defaults to the client palette)
      $spacing — vertical breathing room in px (defaults to 28)
--}}
@php
    $t = $theme ?? \App\Support\MailTheme::client();
    $gap = $spacing ?? 28;
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse;margin:{{ $gap }}px 0;
              mso-table-lspace:0;mso-table-rspace:0;">
    <tr>
        <td height="1"
            style="height:1px;line-height:1px;font-size:0;
                   background-color:{{ $t['border'] }};">&nbsp;</td>
    </tr>
</table>
