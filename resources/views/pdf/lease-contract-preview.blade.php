<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <title>Contrat de bail — {{ $contract_number }}</title>
    {{--
        Browser-friendly preview of the lease contract. Same content as the
        printable @see pdf.lease-contract view, but reflowed for on-screen
        reading (fluid "paper" column, system font, responsive type) instead of
        a scaled fixed A4 sheet. Rendered as HTML so it displays on iOS Safari /
        WebKit, where a blob: PDF in an iframe shows blank.
    --}}
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        html, body {
            background: #E7EAEE;
            font-family: -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            color: #1a1a1a;
            -webkit-text-size-adjust: 100%;
        }
        body { padding: clamp(12px, 4vw, 32px) clamp(8px, 3vw, 24px); }

        .lease-contract {
            max-width: 720px; margin: 0 auto; background: #FFFFFF;
            border: 1px solid #E5E7EB; border-radius: 14px;
            box-shadow: 0 18px 48px -20px rgba(17, 24, 39, 0.22);
            padding: clamp(20px, 5vw, 48px);
            font-size: clamp(13px, 2.6vw, 15px); line-height: 1.65;
        }

        .header { text-align: center; border-bottom: 2px solid #0D9488; padding-bottom: 16px; margin-bottom: 24px; }
        .header h1 { font-size: clamp(18px, 4.6vw, 24px); color: #0D9488; margin-bottom: 4px; letter-spacing: 0.3px; }
        .header p { font-size: 12px; color: #6B7280; }
        .header .contract-number { font-size: 11px; color: #9CA3AF; margin-top: 6px; }

        h2 { font-size: clamp(14px, 3.4vw, 16px); color: #0D9488; margin: 26px 0 12px; border-bottom: 1px solid #E5E7EB; padding-bottom: 6px; }

        .parties { display: flex; flex-wrap: wrap; gap: 14px; margin-bottom: 8px; }
        .party { flex: 1 1 240px; padding: 14px; background: #F9FAFB; border: 1px solid #F0F1F3; border-radius: 8px; }
        .party strong.title { display: block; font-size: 13px; color: #0D9488; margin-bottom: 6px; letter-spacing: 0.4px; }
        .party p { margin: 3px 0; word-break: break-word; }

        .property-details { background: #F0FDFA; padding: 14px; border-radius: 8px; }
        .property-details .row { display: flex; gap: 10px; padding: 4px 0; border-bottom: 1px solid rgba(13,148,136,0.08); }
        .property-details .row:last-child { border-bottom: 0; }
        .property-details .row .k { flex: 0 0 42%; font-weight: 700; color: #374151; }
        .property-details .row .v { flex: 1; }

        .financial table { width: 100%; border-collapse: collapse; margin-top: 4px; }
        .financial th, .financial td { padding: 8px 12px; text-align: left; border-bottom: 1px solid #E5E7EB; }
        .financial th { background: #0D9488; color: #FFFFFF; font-size: 12px; }
        .financial th.amount, .financial td.amount { text-align: right; }
        .financial td.amount { font-weight: 700; }

        .article { margin: 10px 0; }
        .article p { text-align: justify; }

        .signatures { display: flex; flex-wrap: wrap; gap: 24px; margin-top: 36px; }
        .signature { flex: 1 1 200px; text-align: center; padding-top: 48px; border-top: 1px solid #333; }
        .signature p { font-size: 12px; color: #6B7280; margin: 2px 0; }
        .signature .who { color: #111827; font-weight: 700; }

        .footer { text-align: center; margin-top: 28px; padding-top: 14px; border-top: 1px solid #E5E7EB; font-size: 11px; color: #9CA3AF; }
    </style>
</head>
<body>
<article class="lease-contract">
    <div class="header">
        <h1>CONTRAT DE BAIL D'HABITATION</h1>
        <p>Généré via KeyHome — Plateforme immobilière</p>
        <p class="contract-number">Réf. {{ $contract_number }}</p>
    </div>

    <h2>Article 1 — Les Parties</h2>
    <div class="parties">
        <div class="party">
            <strong class="title">LE BAILLEUR</strong>
            <p>{{ $landlord_name }}</p>
            <p>Tél : {{ $landlord_phone }}</p>
            <p>Email : {{ $landlord_email }}</p>
        </div>
        <div class="party">
            <strong class="title">LE LOCATAIRE</strong>
            <p>{{ $tenant_name }}</p>
            <p>Tél : {{ $tenant_phone }}</p>
            @if($tenant_email)<p>Email : {{ $tenant_email }}</p>@endif
            @if($tenant_id_number)<p>CNI/Passeport : {{ $tenant_id_number }}</p>@endif
        </div>
    </div>

    <h2>Article 2 — Désignation du bien</h2>
    <div class="property-details">
        <div class="row"><span class="k">Désignation</span><span class="v">{{ $property_title }}</span></div>
        @if($unit_reference)<div class="row"><span class="k">Référence</span><span class="v">{{ $unit_reference }}</span></div>@endif
        <div class="row"><span class="k">Type</span><span class="v">{{ $property_type }}</span></div>
        <div class="row"><span class="k">Adresse</span><span class="v">{{ $property_address }}</span></div>
        <div class="row"><span class="k">Quartier / Ville</span><span class="v">{{ $quarter }}, {{ $city }}</span></div>
        <div class="row"><span class="k">Chambres / SDB</span><span class="v">{{ $bedrooms }} chambre{{ (int) $bedrooms > 1 ? 's' : '' }}, {{ $bathrooms }} salle{{ (int) $bathrooms > 1 ? 's' : '' }} de bain</span></div>
        @if($surface_area)<div class="row"><span class="k">Surface</span><span class="v">{{ $surface_area }} m²</span></div>@endif
    </div>

    <h2>Article 3 — Durée du bail</h2>
    <div class="article">
        <p>Le présent bail est conclu pour une durée de <strong>{{ $lease_duration_months }} mois</strong>, du <strong>{{ $lease_start }}</strong> au <strong>{{ $lease_end }}</strong>, renouvelable par tacite reconduction sauf dénonciation par l'une des parties avec un préavis de trois (3) mois.</p>
    </div>

    <h2>Article 4 — Conditions financières</h2>
    <div class="financial">
        <table>
            <thead>
                <tr><th>Désignation</th><th class="amount">Montant (FCFA)</th></tr>
            </thead>
            <tbody>
                <tr><td>Loyer mensuel</td><td class="amount">{{ number_format($monthly_rent) }}</td></tr>
                <tr><td>Dépôt de garantie</td><td class="amount">{{ number_format($deposit_amount) }}</td></tr>
                @if($charges_forfaitaires && $charges_montant_forfait)
                    <tr><td>Charges forfaitaires</td><td class="amount">{{ number_format($charges_montant_forfait) }}</td></tr>
                @else
                    @if($charges_eau)<tr><td>Charges eau</td><td class="amount">{{ number_format($charges_eau) }}</td></tr>@endif
                    @if($charges_electricite)<tr><td>Charges électricité</td><td class="amount">{{ number_format($charges_electricite) }}</td></tr>@endif
                @endif
            </tbody>
        </table>
    </div>

    <div class="article">
        <p>Le loyer est payable d'avance, au plus tard le 5 de chaque mois. Tout retard de paiement supérieur à 15 jours entraînera une pénalité de 10% du montant du loyer.</p>
    </div>

    <h2>Article 5 — Obligations du bailleur</h2>
    <div class="article">
        <p>Le bailleur s'engage à : délivrer le bien en bon état d'habitabilité ; assurer la jouissance paisible du locataire ; effectuer les réparations autres que locatives ; remettre les quittances de loyer.</p>
    </div>

    <h2>Article 6 — Obligations du locataire</h2>
    <div class="article">
        <p>Le locataire s'engage à : payer le loyer et les charges aux termes convenus ; user du bien en bon père de famille ; répondre des dégradations survenues pendant la durée du bail ; ne pas sous-louer sans accord écrit du bailleur ; restituer le bien en bon état à la fin du bail.</p>
    </div>

    <h2>Article 7 — Résiliation</h2>
    <div class="article">
        <p>Le bail pourra être résilié de plein droit en cas de non-paiement du loyer pendant deux mois consécutifs, après mise en demeure restée sans effet pendant 30 jours. Chaque partie peut résilier le bail avec un préavis de trois mois.</p>
    </div>

    @if($special_conditions)
    <h2>Article 8 — Conditions particulières</h2>
    <div class="article">
        <p>{{ $special_conditions }}</p>
    </div>
    @endif

    <div class="signatures">
        <div class="signature">
            <p class="who">Le Bailleur</p>
            <p>{{ $landlord_name }}</p>
            <p>Fait à {{ $city }}, le {{ $lease_start }}</p>
        </div>
        <div class="signature">
            <p class="who">Le Locataire</p>
            <p>{{ $tenant_name }}</p>
            <p>Fait à {{ $city }}, le {{ $lease_start }}</p>
        </div>
    </div>

    <div class="footer">
        <p>Document généré par KeyHome le {{ $generated_at }} — Réf. {{ $contract_number }}</p>
        <p>Ce contrat est un modèle indicatif. Il est recommandé de le faire valider par un professionnel du droit.</p>
    </div>
</article>
</body>
</html>
