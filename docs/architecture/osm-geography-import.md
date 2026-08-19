# Référentiel géographique OpenStreetMap

KeyHome conserve ses lieux métier dans `city` et `quarter`. Les données OSM
brutes sont importées dans le schéma jetable `osm_import`; elles ne sont jamais
utilisées directement par les annonces.

## Prérequis

- PostgreSQL 17 avec PostGIS, `pg_trgm` et `unaccent`.
- `osm2pgsql` 2.x disponible dans le `PATH` (ou `OSM2PGSQL_BINARY`).
- Espace disque suffisant pour le PBF et l'import temporaire.

Le téléchargement passe par `curl --output` et écrit directement dans un
fichier `.part`; le contenu du PBF n'est jamais chargé dans la mémoire PHP.

## Pipeline

```bash
php artisan geo:refresh-osm cameroon
```

Afficher les régions configurées et importer plusieurs pays en une seule fois :

```bash
php artisan geo:refresh-osm --list
php artisan geo:refresh-osm cameroon france germany
```

Chaque extrait passe successivement dans le schéma temporaire `osm_import`,
puis ses données sont ajoutées ou actualisées dans le catalogue permanent. Un
pays déjà synchronisé n'est donc pas supprimé lorsqu'un autre pays est traité.

Cette commande unique télécharge et vérifie l'extrait, reconstruit le schéma
temporaire avec `osm2pgsql`, puis synchronise `city` et `quarter`. Elle réutilise
le fichier PBF local lorsqu'il est déjà présent. Pour forcer son téléchargement :

```bash
php artisan geo:refresh-osm cameroon --force-download
```

Le PBF est conservé dans `storage/app/private/osm/` pour accélérer la prochaine
actualisation. Il n'est plus nécessaire après la synchronisation. Pour le
supprimer automatiquement uniquement après la réussite des trois étapes :

```bash
php artisan geo:refresh-osm france germany --force-download --cleanup
```

Pour tester le marché allemand sans télécharger l'extrait national de plusieurs
gigaoctets, utiliser le pilote léger du Land de Brême :

```bash
php artisan geo:refresh-osm germany-bremen --force-download --cleanup
```

En production, ajouter `--force` pour autoriser la reconstruction de
`osm_import`. Les commandes `geo:download-osm`, `geo:import-osm` et
`geo:sync-osm` restent disponibles séparément pour le diagnostic et la reprise
d'une étape interrompue.

Le téléchargement est atomique (`.part` puis renommage) et validé avec le MD5
publié par Geofabrik. L'import Flex ne conserve que les lieux et limites
administratives. Les tables intermédiaires osm2pgsql sont supprimées après
l'import (`--drop`) puisque la commande reconstruit intégralement le schéma à
chaque nouvel extrait.

La synchronisation est idempotente grâce à `(osm_type, osm_id)`. Les lieux
`city`, `town`, `municipality` et `village` alimentent `city`; `suburb`,
`quarter`, `neighbourhood` et `locality` alimentent `quarter`. Un quartier est
rattaché à la localité la plus proche du même pays, dans un rayon maximal de
75 km. Les lignes hors rayon sont comptabilisées comme non rattachées.

## Ordre des coordonnées

- Magellan : `Point::makeGeodetic(latitude, longitude)`.
- PostGIS : `ST_MakePoint(longitude, latitude)`.
- SRID de stockage : 4326.

## Après une reconstruction de base

`DatabaseSeeder` n'importe plus l'ancien catalogue GeoNames. Après
`migrate:fresh --seed`, exécuter le pipeline OSM, puis éventuellement :

```bash
php artisan db:seed --class=MassiveAdSeeder
```

## Extension géographique

Ajouter chaque extrait à `config/osm.php`, tester d'abord un pays, puis une
région. L'extrait `africa` est prévu pour le déploiement disposant de l'espace
disque et de la mémoire nécessaires. Les pays européens sont ajoutés à la même
configuration selon la liste de marchés activés.

L'interface doit afficher l'attribution `© OpenStreetMap contributors` et un
lien vers la licence ODbL.
