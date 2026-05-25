{{--
    Full-width gradient hero banner — sits between the header and the content block.

    Variables:
      $heroBg       (optional) — CSS gradient, defaults to dark+crimson
      $heroEyebrow  (optional) — small uppercase label (e.g. "Bienvenue" / "Sécurité")
      $heroText     (optional) — large white headline
      $heroSub      (optional) — subtitle / tagline in muted white
      $heroAlign    (optional) — 'left' | 'center' (default: 'center')

    Usage:
      @section('hero')
          @include('emails.partials.hero', [
              'heroBg'      => 'linear-gradient(135deg, #042f2e 0%, #0d9488 100%)',
              'heroEyebrow' => 'Espace Bailleur',
              'heroText'    => 'Votre tableau de bord est prêt',
              'heroSub'     => 'Publiez, gérez et développez votre patrimoine',
          ])
      @endsection
--}}
@php
    $bg    = $heroBg    ?? 'linear-gradient(135deg, #1e293b 0%, #C73B52 100%)';
    $align = $heroAlign ?? 'center';
@endphp
<table role="presentation" width="100%" cellpadding="0" cellspacing="0"
       style="border-collapse:collapse;mso-table-lspace:0;mso-table-rspace:0;">
    <tr>
        <td align="{{ $align }}"
            style="background: {{ $bg }}; padding: 32px 32px 28px 32px; mso-padding-alt: 32px 32px 28px 32px;">
            @isset($heroEyebrow)
            <p style="margin: 0 0 8px 0;
                      font-size: 11px;
                      font-weight: 700;
                      color: rgba(255,255,255,0.65);
                      text-transform: uppercase;
                      letter-spacing: 1.5px;
                      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
                      text-align: {{ $align }};">
                {{ $heroEyebrow }}
            </p>
            @endisset

            @isset($heroText)
            <p style="margin: 0;
                      font-size: 26px;
                      font-weight: 800;
                      color: #ffffff;
                      line-height: 1.2;
                      letter-spacing: -0.3px;
                      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
                      text-align: {{ $align }};">
                {!! $heroText !!}
            </p>
            @endisset

            @isset($heroSub)
            <p style="margin: 8px 0 0 0;
                      font-size: 14px;
                      color: rgba(255,255,255,0.70);
                      line-height: 1.55;
                      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Helvetica, Arial, sans-serif;
                      text-align: {{ $align }};">
                {{ $heroSub }}
            </p>
            @endisset
        </td>
    </tr>
</table>
