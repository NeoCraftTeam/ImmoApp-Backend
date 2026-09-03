<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Pancarte profil — {{ $user->firstname }} {{ $user->lastname }}</title>
    {{--
        A5 profile sign — épuré, quasi-monochrome, discreet teal accent.
        Avatar uses `background-size: cover` (DomPDF ignores `object-fit`).
    --}}
    <style>
        @page { size: A5 portrait; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body { font-family: 'DejaVu Sans', sans-serif; color: #111827; background: #FFFFFF; }

        .placarde { width: 148mm; height: 210mm; position: relative; background: #FFFFFF; }

        .header { position: absolute; top: 13mm; left: 12mm; right: 12mm; height: 8mm; }
        .brand { font-size: 12pt; font-weight: 800; letter-spacing: 3px; color: #111827; }
        .brand-rule { width: 16mm; height: 1.1mm; background: #0D9488; margin-top: 1.4mm; }
        .verified {
            position: absolute; top: 1mm; right: 0;
            font-size: 8.5pt; font-weight: 800; letter-spacing: 2.5px;
            color: #0D9488; text-transform: uppercase;
        }

        .avatar {
            position: absolute; top: 30mm; left: 53mm; width: 42mm; height: 42mm;
            border-radius: 50%;
            background-color: #F3F4F6; background-position: center; background-size: cover;
            background-repeat: no-repeat;
            border: 0.75pt solid #E5E7EB; overflow: hidden; text-align: center;
        }
        .avatar .initials { display: block; font-size: 24pt; font-weight: 800; color: #0D9488; padding-top: 11mm; }

        .identity { position: absolute; top: 78mm; left: 12mm; right: 12mm; text-align: center; }
        .name { font-size: 18pt; font-weight: 800; line-height: 1.15; color: #111827; margin-bottom: 1.5mm; }
        .role { font-size: 8.5pt; color: #0D9488; font-weight: 800; text-transform: uppercase; letter-spacing: 2px; }

        .meta {
            position: absolute; top: 96mm; left: 20mm; right: 20mm; text-align: center;
            font-size: 9pt; color: #6B7280; line-height: 1.6;
            padding-bottom: 5mm; border-bottom: 0.5pt solid #E5E7EB;
        }
        .meta strong { color: #111827; font-weight: 700; }

        .pitch { position: absolute; top: 118mm; left: 18mm; right: 18mm; text-align: center; }
        .pitch-text { font-size: 9.5pt; color: #6B7280; line-height: 1.6; }

        .qr-block { position: absolute; bottom: 12mm; left: 12mm; right: 12mm; height: 42mm; border-top: 0.5pt solid #E5E7EB; padding-top: 5mm; }
        .qr-row { width: 100%; border-collapse: collapse; }
        .qr-row td { vertical-align: middle; padding: 0; }
        .qr-cell { width: 38mm; }
        .qr-img { width: 36mm; height: 36mm; display: block; }
        .qr-text { text-align: left; padding-left: 6mm; }
        .qr-cta { font-size: 13pt; font-weight: 700; color: #111827; line-height: 1.2; margin-bottom: 1.5mm; }
        .qr-cta .accent { color: #0D9488; }
        .qr-instruction { font-size: 8pt; color: #6B7280; line-height: 1.5; }

        .footer { position: absolute; bottom: 5mm; left: 12mm; right: 12mm; font-size: 6.5pt; color: #9CA3AF; letter-spacing: 0.5px; text-align: center; }
        .footer strong { font-weight: 700; color: #6B7280; }
    </style>
</head>
<body>
<div class="placarde">

    <div class="header">
        <span class="verified">Profil vérifié</span>
        <div class="brand">KEYHOME</div>
        <div class="brand-rule"></div>
    </div>

    @if(!empty($avatarDataUri))
        <div class="avatar" style="background-image: url('{{ $avatarDataUri }}');"></div>
    @else
        <div class="avatar"><span class="initials">{{ strtoupper(substr($user->firstname, 0, 1)) }}{{ strtoupper(substr($user->lastname, 0, 1)) }}</span></div>
    @endif

    <div class="identity">
        <div class="name">{{ \Illuminate\Support\Str::limit($user->firstname.' '.$user->lastname, 40) }}</div>
        <div class="role">{{ $roleLabel }}</div>
    </div>

    <div class="meta">
        @if(!empty($user->phone_number))
            <strong>{{ $user->phone_number }}</strong><br>
        @endif
        keyhome.app/<strong>{{ \Illuminate\Support\Str::limit($user->username ?: substr($user->id, 0, 8), 18, '…') }}</strong>
        @if($adsCount > 0)
            <br>{{ $adsCount }} annonce{{ $adsCount > 1 ? 's' : '' }} active{{ $adsCount > 1 ? 's' : '' }}
        @endif
    </div>

    <div class="pitch">
        <div class="pitch-text">
            Scannez pour accéder à toutes mes annonces, photos et contact direct sur KeyHome.
        </div>
    </div>

    <div class="qr-block">
        <table class="qr-row">
            <tr>
                <td class="qr-cell">
                    <img class="qr-img" src="{{ $qrDataUri }}" alt="QR Code">
                </td>
                <td class="qr-text">
                    <div class="qr-cta">Scannez mon <span class="accent">profil</span></div>
                    <div class="qr-instruction">Annonces à jour et contact direct sur keyhome.app</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="footer">Bailleur vérifié · <strong>keyhome.app</strong></div>
</div>
</body>
</html>
