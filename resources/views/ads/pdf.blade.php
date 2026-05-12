<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>{{ $ad->title }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: DejaVu Sans, sans-serif;
            font-size: 10pt;
            color: #1e293b;
            background: #ffffff;
            line-height: 1.5;
        }

        .accent-bar { width: 100%; height: 5px; background: #F6475F; }

        .header {
            padding: 20px 40px 16px;
            border-bottom: 1px solid #e2e8f0;
        }
        .header-table { width: 100%; border-collapse: collapse; }
        .logo-cell { width: 140px; vertical-align: middle; }
        .logo-cell img { height: 38px; width: auto; }
        .brand-cell { vertical-align: middle; text-align: right; }
        .brand-name { font-size: 18pt; font-weight: 700; color: #F6475F; }
        .brand-tag  { font-size: 8pt; color: #94a3b8; }

        .body { padding: 24px 40px; }

        .badge {
            display: inline-block;
            background: #F6475F;
            color: #fff;
            font-size: 8pt;
            font-weight: 700;
            padding: 2px 10px;
            border-radius: 20px;
            letter-spacing: 0.5px;
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        h1 { font-size: 15pt; font-weight: 800; color: #0f172a; margin-bottom: 4px; }

        .price { font-size: 18pt; font-weight: 800; color: #F6475F; }
        .price-suffix { font-size: 9pt; color: #64748b; margin-left: 4px; }

        .location { font-size: 9pt; color: #64748b; margin-top: 4px; }

        .divider { border: none; border-top: 1px solid #e2e8f0; margin: 16px 0; }

        .stats-table { width: 100%; border-collapse: collapse; margin-top: 12px; }
        .stat-cell { width: 25%; padding: 8px 12px; text-align: center; border: 1px solid #e2e8f0; border-radius: 4px; }
        .stat-label { font-size: 7pt; color: #94a3b8; text-transform: uppercase; letter-spacing: 0.5px; }
        .stat-value { font-size: 11pt; font-weight: 700; color: #0f172a; margin-top: 2px; }

        .section-title {
            font-size: 9pt;
            font-weight: 700;
            color: #475569;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            margin-bottom: 6px;
            margin-top: 16px;
        }

        .description { font-size: 9pt; color: #475569; line-height: 1.6; }

        .photo { width: 100%; max-height: 240px; object-fit: cover; border-radius: 6px; margin: 12px 0; }

        .attributes-table { width: 100%; border-collapse: collapse; margin-top: 6px; }
        .attribute-cell {
            width: 33.33%;
            padding: 4px 8px;
            font-size: 8.5pt;
            color: #334155;
        }
        .attribute-dot {
            display: inline-block;
            width: 6px;
            height: 6px;
            background: #F6475F;
            border-radius: 50%;
            margin-right: 5px;
        }

        .contact-box {
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 6px;
            padding: 14px 18px;
            margin-top: 16px;
        }
        .contact-title { font-size: 9pt; font-weight: 700; color: #0f172a; margin-bottom: 8px; }
        .contact-row { font-size: 9pt; color: #475569; margin-bottom: 4px; }
        .contact-label { font-weight: 600; color: #334155; }

        .footer {
            position: fixed;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 10px 40px;
            border-top: 1px solid #e2e8f0;
            font-size: 7.5pt;
            color: #94a3b8;
        }
        .footer-table { width: 100%; border-collapse: collapse; }
        .footer-left  { text-align: left; }
        .footer-right { text-align: right; }
    </style>
</head>
<body>

    <div class="accent-bar"></div>

    <div class="header">
        <table class="header-table">
            <tr>
                <td class="logo-cell">
                    @if($logoBase64)
                        <img src="{{ $logoBase64 }}" alt="KeyHome">
                    @else
                        <span class="brand-name">KeyHome</span>
                    @endif
                </td>
                <td class="brand-cell">
                    <div class="brand-name">KeyHome</div>
                    <div class="brand-tag">Immobilier en Afrique</div>
                </td>
            </tr>
        </table>
    </div>

    <div class="body">

        <div class="badge">Annonce immobilière</div>
        <h1>{{ $ad->title }}</h1>

        <div style="margin-top: 6px;">
            <span class="price">{{ number_format((float)($ad->price ?? 0), 0, ',', ' ') }} FCFA</span>
            <span class="price-suffix">/ mois</span>
        </div>

        <div class="location">
            📍
            @if($ad->quarter && $ad->quarter->city)
                {{ $ad->quarter->name }}, {{ $ad->quarter->city->name }}
            @else
                {{ $ad->adresse }}
            @endif
            @if($ad->ad_type)
                &nbsp;·&nbsp; {{ $ad->ad_type->name }}
            @endif
        </div>

        @if($primaryImage)
            <img class="photo" src="{{ $primaryImage }}" alt="{{ $ad->title }}">
        @endif

        <hr class="divider">

        <!-- Key stats -->
        <table class="stats-table">
            <tr>
                @if($ad->surface_area)
                <td class="stat-cell">
                    <div class="stat-label">Surface</div>
                    <div class="stat-value">{{ $ad->surface_area }} m²</div>
                </td>
                @endif
                @if($ad->bedrooms)
                <td class="stat-cell">
                    <div class="stat-label">Chambres</div>
                    <div class="stat-value">{{ $ad->bedrooms }}</div>
                </td>
                @endif
                @if($ad->bathrooms)
                <td class="stat-cell">
                    <div class="stat-label">Salles de bain</div>
                    <div class="stat-value">{{ $ad->bathrooms }}</div>
                </td>
                @endif
                <td class="stat-cell">
                    <div class="stat-label">Parking</div>
                    <div class="stat-value">{{ $ad->has_parking ? 'Oui' : 'Non' }}</div>
                </td>
            </tr>
        </table>

        <!-- Description -->
        @if($ad->description)
        <div class="section-title">Description</div>
        <div class="description">{{ $ad->description }}</div>
        @endif

        <!-- Amenities -->
        @php $attrs = $ad->getAttribute('attributes') ?? []; @endphp
        @if(count($attrs) > 0)
        <div class="section-title">Équipements</div>
        <table class="attributes-table">
            @foreach(array_chunk($attrs, 3) as $row)
            <tr>
                @foreach($row as $attr)
                <td class="attribute-cell">
                    <span class="attribute-dot"></span>{{ \Illuminate\Support\Str::title(str_replace(['-', '_'], ' ', $attr)) }}
                </td>
                @endforeach
                @for($i = count($row); $i < 3; $i++)
                <td class="attribute-cell"></td>
                @endfor
            </tr>
            @endforeach
        </table>
        @endif

        <!-- Publisher contact (always shown — PDF is informational) -->
        @if($ad->user)
        <div class="contact-box">
            <div class="contact-title">Publié par</div>
            @if($ad->user->agency)
            <div class="contact-row"><span class="contact-label">Agence :</span> {{ $ad->user->agency->name }}</div>
            @endif
            <div class="contact-row"><span class="contact-label">Nom :</span> {{ $ad->user->fullname }}</div>
            <div class="contact-row" style="margin-top: 8px; font-size: 8pt; color: #94a3b8;">
                Pour obtenir les coordonnées complètes, déverrouillez cette annonce sur keyhome.app
            </div>
        </div>
        @endif

    </div>

    <div class="footer">
        <table class="footer-table">
            <tr>
                <td class="footer-left">keyhome.app · Immobilier en Afrique</td>
                <td class="footer-right">Généré le {{ now()->format('d/m/Y') }} · Réf. {{ substr($ad->id, 0, 8) }}</td>
            </tr>
        </table>
    </div>

</body>
</html>
