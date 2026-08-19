# Catalogue d’attributs immobiliers KeyHome — août 2026

## Décision

Le catalogue KeyHome cible la location longue durée et la vente de studios,
appartements et maisons. Il conserve uniquement des caractéristiques objectives,
vérifiables et utiles à la recherche. Les consommables et services propres aux
locations touristiques ont été retirés.

## Références utilisées

- RESO Data Dictionary 2.1 : champs normalisés de propriété, stationnement et
  réseaux (`ParkingFeatures`, `Utilities`, accessibilité).
- Airbnb, filtres officiels : Wi-Fi, cuisine, climatisation, lave-linge,
  stationnement, ascenseur, sécurité, extérieur et accessibilité sont des
  critères effectivement recherchés.
- Airbnb, règles d’exactitude : les équipements déclarés doivent réellement être
  présents; le catalogue privilégie donc les attributs vérifiables.

## Adaptation KeyHome

Huit catégories sont retenues : agencement, cuisine/électroménager,
confort/connectivité, eau/énergie/assainissement, sécurité, accès/stationnement,
extérieur/dépendances et conditions d’occupation. Les contraintes régionales
importantes (réservoir d’eau, forage, groupe électrogène, batterie, compteur
individuel, clôture et gardien) complètent le socle international.

## Sécurité et gouvernance

- La commande est idempotente par slug.
- `--dry-run` prévisualise les volumes.
- `--fresh` est réservé à une reconstruction assumée du catalogue.
- Les administrateurs restent libres d’activer, désactiver ou compléter le
  catalogue depuis Filament après installation.

## Sources

- https://dd.reso.org/DD2.1/Property/
- https://dd.reso.org/DD2.1/Building/ParkingFeatures/
- https://www.airbnb.com/help/article/3740
- https://www.airbnb.com/help/article/2895
