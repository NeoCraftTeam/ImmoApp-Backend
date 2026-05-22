{{--
    Outlook-safe CTA button partial.

    Variables:
      $url   (required) — href destination
      $label (required) — button text
      $color (optional) — hex background, defaults to KeyHome crimson #F6475F
      $width (optional) — MSO pixel width, defaults to 240

    Usage:
      @include('emails.partials.button', ['url' => $ctaUrl, 'label' => 'Explorer les annonces'])
      @include('emails.partials.button', ['url' => $ctaUrl, 'label' => '...', 'color' => '#0d9488', 'width' => 260])
--}}
@php
    $btnColor = $color ?? '#F6475F';
    $btnWidth = $width ?? 240;
@endphp
<div class="btn-wrapper">
    <!--[if mso]>
    <v:roundrect xmlns:v="urn:schemas-microsoft-com:vml" xmlns:w="urn:schemas-microsoft-com:office:word"
        href="{{ $url }}"
        style="height:48px;v-text-anchor:middle;width:{{ $btnWidth }}px;"
        arcsize="17%"
        stroke="f"
        fillcolor="{{ $btnColor }}">
        <w:anchorlock/>
        <center style="color:#ffffff;font-family:Helvetica,Arial,sans-serif;font-size:15px;font-weight:700;">{{ $label }}</center>
    </v:roundrect>
    <![endif]-->
    <!--[if !mso]><!-->
    <a href="{{ $url }}"
       class="btn"
       style="display:inline-block;background-color:{{ $btnColor }};color:#ffffff!important;font-size:15px;font-weight:600;text-decoration:none;padding:14px 28px;border-radius:8px;line-height:1;">{{ $label }}</a>
    <!--<![endif]-->
</div>
