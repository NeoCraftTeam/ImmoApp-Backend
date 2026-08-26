<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Pancarte — {{ $user->firstname }} {{ $user->lastname }}</title>
    <style>
        @page { size: A5 portrait; margin: 0; }
        * { box-sizing: border-box; margin: 0; padding: 0; }

        body {
            font-family: 'DejaVu Sans', sans-serif;
            color: #0F172A;
            background: #FFFFFF;
        }

        .placarde {
            width: 148mm;
            height: 210mm;
            position: relative;
            background: #FFFFFF;
        }

        .top-bar {
            position: absolute;
            top: 0; left: 0; right: 0;
            height: 8mm;
            background: #0D9488;
            color: #FFFFFF;
        }
        .top-bar .brand {
            position: absolute;
            top: 1.8mm; left: 8mm;
            font-size: 9pt; font-weight: 800; letter-spacing: 2.2px;
        }
        .top-bar .tagline {
            position: absolute;
            top: 2.2mm; right: 8mm;
            font-size: 7.5pt; font-style: italic; opacity: 0.95;
        }

        .bottom-bar {
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 6mm;
            background: #0D9488;
            color: #FFFFFF;
            text-align: center;
            font-size: 7pt; font-weight: 600; letter-spacing: 1.5px;
            padding-top: 1.7mm;
            text-transform: uppercase;
        }
        .bottom-bar strong { font-weight: 800; letter-spacing: 2px; }

        .hero {
            position: absolute;
            top: 14mm; left: 8mm; right: 8mm;
            height: 52mm;
            background: #F0FDFA;
            border: 0.5pt solid #99F6E4;
            border-radius: 2mm;
            overflow: hidden;
        }

        .avatar {
            position: absolute;
            top: 8mm; left: 8mm;
            width: 36mm; height: 36mm;
            border-radius: 50%;
            background: #FFFFFF;
            border: 1pt solid #5EEAD4;
            overflow: hidden;
            text-align: center;
        }
        .avatar img { width: 100%; height: 100%; object-fit: cover; display: block; }
        .avatar .initials {
            display: block;
            font-size: 22pt; font-weight: 800;
            color: #0D9488;
            padding-top: 10mm;
        }

        .profile-block {
            position: absolute;
            top: 10mm; left: 50mm; right: 6mm;
        }
        .name {
            font-size: 16pt; font-weight: 800;
            line-height: 1.15; color: #0F172A;
            margin-bottom: 2mm;
        }
        .role {
            font-size: 9pt; color: #0D9488;
            font-weight: 700; text-transform: uppercase;
            letter-spacing: 1.2px;
            margin-bottom: 2mm;
        }
        .meta {
            font-size: 8.5pt; color: #475569;
            line-height: 1.45;
        }
        .meta strong { color: #0F172A; font-weight: 700; }

        .pitch {
            position: absolute;
            top: 72mm; left: 8mm; right: 8mm;
            height: 28mm;
            padding: 4mm 5mm;
            background: #FFFFFF;
            border: 0.5pt dashed #99F6E4;
            border-radius: 2mm;
        }
        .pitch-title {
            font-size: 11pt; font-weight: 800;
            color: #0D9488;
            margin-bottom: 2mm;
        }
        .pitch-text {
            font-size: 8.5pt; color: #475569;
            line-height: 1.55;
        }

        .qr-block {
            position: absolute;
            bottom: 6mm;
            left: 8mm; right: 8mm;
            height: 58mm;
            border-top: 0.5pt solid #CCFBF1;
            padding-top: 3mm;
        }
        .qr-row { width: 100%; border-collapse: collapse; }
        .qr-row td { vertical-align: middle; padding: 0; }
        .qr-cell { width: 52mm; text-align: center; }
        .qr-img { height: 50mm; width: auto; display: block; margin: 0 auto; }
        .qr-text { text-align: left; padding-left: 4mm !important; }
        .qr-cta {
            font-size: 13pt; font-weight: 800;
            color: #0F172A; line-height: 1.15;
            margin-bottom: 1.5mm;
        }
        .qr-cta .accent { color: #0D9488; }
        .qr-instruction {
            font-size: 8pt; color: #64748B; line-height: 1.55;
        }
        .qr-instruction strong { color: #0D9488; font-weight: 800; }
    </style>
</head>
<body>
<div class="placarde">

    <div class="top-bar">
        <span class="brand">KEYHOME</span>
        <span class="tagline">Profil bailleur vérifié</span>
    </div>

    <div class="hero">
        <div class="avatar">
            @if(!empty($avatarDataUri))
                <img src="{{ $avatarDataUri }}" alt="">
            @else
                <span class="initials">{{ strtoupper(substr($user->firstname, 0, 1)) }}{{ strtoupper(substr($user->lastname, 0, 1)) }}</span>
            @endif
        </div>

        <div class="profile-block">
            <div class="name">{{ \Illuminate\Support\Str::limit($user->firstname.' '.$user->lastname, 40) }}</div>
            <div class="role">{{ $roleLabel }}</div>
            <div class="meta">
                @if(!empty($user->phone_number))
                    <strong>{{ $user->phone_number }}</strong><br>
                @endif
                keyhome.app/<strong>{{ \Illuminate\Support\Str::limit($user->username ?: substr($user->id, 0, 8), 18, '…') }}</strong>
                @if($adsCount > 0)
                    <br>{{ $adsCount }} annonce{{ $adsCount > 1 ? 's' : '' }} active{{ $adsCount > 1 ? 's' : '' }}
                @endif
            </div>
        </div>
    </div>

    <div class="pitch">
        <div class="pitch-title">Scannez pour découvrir mes biens</div>
        <div class="pitch-text">
            Accédez à toutes mes annonces, photos HD et contact direct sur KeyHome.
            Idéal en vitrine, lors d'une visite ou sur votre stand.
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
                    <div class="qr-instruction">
                        Annonces à jour · Contact direct<br>
                        Bailleur vérifié KeyHome<br>
                        sur <strong>keyhome.app</strong>
                    </div>
                </td>
            </tr>
        </table>
    </div>

    <div class="bottom-bar">
        Profil bailleur &nbsp;·&nbsp; <strong>KEYHOME.APP</strong>
    </div>
</div>
</body>
</html>
