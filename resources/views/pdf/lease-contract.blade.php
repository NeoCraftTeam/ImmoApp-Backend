<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <title>Contrat de Bail — {{ $contract_number }}</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'DejaVu Sans', sans-serif; font-size: 11px; line-height: 1.6; color: #1a1a1a; padding: 40px; }
        .header { text-align: center; margin-bottom: 30px; border-bottom: 2px solid #0D9488; padding-bottom: 15px; }
        .header h1 { font-size: 20px; color: #0D9488; margin-bottom: 4px; }
        .header p { font-size: 10px; color: #666; }
        .contract-number { font-size: 9px; color: #999; margin-top: 5px; }
        h2 { font-size: 13px; color: #0D9488; margin: 20px 0 10px; border-bottom: 1px solid #e5e7eb; padding-bottom: 5px; }
        .parties { display: table; width: 100%; margin-bottom: 15px; }
        .party { display: table-cell; width: 48%; vertical-align: top; padding: 10px; background: #f9fafb; border-radius: 4px; }
        .party-spacer { display: table-cell; width: 4%; }
        .party strong { display: block; font-size: 12px; color: #0D9488; margin-bottom: 5px; }
        .party p { margin: 2px 0; }
        .property-details { background: #f0fdfa; padding: 12px; border-radius: 4px; margin: 10px 0; }
        .property-details table { width: 100%; }
        .property-details td { padding: 3px 8px; }
        .property-details td:first-child { font-weight: bold; width: 40%; color: #374151; }
        .financial { margin: 10px 0; }
        .financial table { width: 100%; border-collapse: collapse; }
        .financial th, .financial td { padding: 6px 10px; text-align: left; border-bottom: 1px solid #e5e7eb; }
        .financial th { background: #0D9488; color: white; font-size: 10px; }
        .financial td { font-size: 11px; }
        .financial .amount { text-align: right; font-weight: bold; }
        .article { margin: 12px 0; }
        .article h3 { font-size: 11px; font-weight: bold; margin-bottom: 4px; }
        .article p { text-align: justify; }
        .signatures { display: table; width: 100%; margin-top: 40px; }
        .signature { display: table-cell; width: 45%; text-align: center; padding-top: 60px; border-top: 1px solid #333; }
        .signature-spacer { display: table-cell; width: 10%; }
        .signature p { font-size: 10px; color: #666; }
        .footer { text-align: center; margin-top: 30px; padding-top: 10px; border-top: 1px solid #e5e7eb; font-size: 9px; color: #999; }
    </style>
</head>
<body>
    <div class="header">
        <h1>CONTRAT DE BAIL D'HABITATION</h1>
        <p>Généré via KeyHome — Plateforme immobilière</p>
        <p class="contract-number">Réf. {{ $contract_number }}</p>
    </div>

    <h2>Article 1 — Les Parties</h2>
    <div class="parties">
        <div class="party">
            <strong>LE BAILLEUR</strong>
            <p>{{ $landlord_name }}</p>
            <p>Tél : {{ $landlord_phone }}</p>
            <p>Email : {{ $landlord_email }}</p>
        </div>
        <div class="party-spacer"></div>
        <div class="party">
            <strong>LE LOCATAIRE</strong>
            <p>{{ $tenant_name }}</p>
            <p>Tél : {{ $tenant_phone }}</p>
            @if($tenant_email)<p>Email : {{ $tenant_email }}</p>@endif
            @if($tenant_id_number)<p>CNI/Passeport : {{ $tenant_id_number }}</p>@endif
        </div>
    </div>

    <h2>Article 2 — Désignation du bien</h2>
    <div class="property-details">
        <table>
            <tr><td>Désignation</td><td>{{ $property_title }}</td></tr>
            @if($unit_reference)<tr><td>Référence</td><td>{{ $unit_reference }}</td></tr>@endif
            <tr><td>Type</td><td>{{ $property_type }}</td></tr>
            <tr><td>Adresse</td><td>{{ $property_address }}</td></tr>
            <tr><td>Quartier / Ville</td><td>{{ $quarter }}, {{ $city }}</td></tr>
            <tr><td>Chambres / SDB</td><td>{{ $bedrooms }} chambre(s), {{ $bathrooms }} salle(s) de bain</td></tr>
            @if($surface_area)<tr><td>Surface</td><td>{{ $surface_area }} m²</td></tr>@endif
        </table>
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
            <p><strong>Le Bailleur</strong></p>
            <p>{{ $landlord_name }}</p>
            <p>Fait à {{ $city }}, le {{ $lease_start }}</p>
        </div>
        <div class="signature-spacer"></div>
        <div class="signature">
            <p><strong>Le Locataire</strong></p>
            <p>{{ $tenant_name }}</p>
            <p>Fait à {{ $city }}, le {{ $lease_start }}</p>
        </div>
    </div>

    <div class="footer">
        <p>Document généré par KeyHome le {{ $generated_at }} — Réf. {{ $contract_number }}</p>
        <p>Ce contrat est un modèle indicatif. Il est recommandé de le faire valider par un professionnel du droit.</p>
    </div>
</body>
</html>
