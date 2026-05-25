<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\Quarter;
use Illuminate\Database\Seeder;

/**
 * Données réelles : villes et quartiers du Cameroun.
 * Idempotent — utilise firstOrCreate sur les noms.
 *
 * Usage : php artisan db:seed --class=CityQuarterCameroonSeeder
 */
class CityQuarterCameroonSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->data() as $cityName => $quarters) {
            $city = City::firstOrCreate(['name' => $cityName]);

            foreach ($quarters as $quarterName) {
                Quarter::firstOrCreate([
                    'name' => $quarterName,
                    'city_id' => $city->id,
                ]);
            }

            $this->command->line("  ✓ {$cityName} (".count($quarters).' quartiers)');
        }

        $this->command->info('Villes et quartiers Cameroun importés.');
    }

    /** @return array<string, list<string>> */
    private function data(): array
    {
        return [

            // ─── DOUALA ──────────────────────────────────────────────────────
            'Douala' => [
                'Akwa',
                'Bonanjo',
                'Bonapriso',
                'Bonamoussadi',
                'Bonabéri',
                'Deido',
                'Bassa',
                'New Bell',
                'Makepe',
                'Logpom',
                'Kotto',
                'Ndokotti',
                'Cité des Palmiers',
                'PK 8',
                'PK 10',
                'PK 13',
                'PK 14',
                'Ndogbong',
                'Ndogpassi',
                'Bangué',
                'Mbanga Pongo',
                'Denver',
                'Village',
                'Njo-njo',
                'Newtown',
                'Ndokouhe',
                'Beedi',
                'Brazzaville',
                'Bepanda',
                'Mbopi',
                'Nyalla',
                'Logbessou',
                'Sodiko',
                'Ndobo',
                'Boko',
                'Nylon',
                'Sapeur',
                'Ange Raphaël',
                'Cité Sic',
                'Mbanga Bakoko',
                'Yassa',
                'Japoma',
                'Ndogsimbi',
                'Cité Cicam',
                'Ngodi Bakoko',
                'Koumassi',
                'Mbénos',
                'Bilonguè',
            ],

            // ─── YAOUNDÉ ─────────────────────────────────────────────────────
            'Yaoundé' => [
                'Bastos',
                'Nlongkak',
                'Mvan',
                'Essos',
                'Ngousso',
                'Centre-ville',
                'Mvog-Mbi',
                'Biyem-Assi',
                'Mendong',
                'Odza',
                'Ekounou',
                'Mimboman',
                'Nsimeyong',
                'Omnisport',
                'Emana',
                'Nkolbisson',
                'Efoulan',
                'Simbock',
                'Etoa-Meki',
                'Kondengui',
                'Ngoa-Ekélé',
                'Mvog-Ada',
                'Fouda',
                'Etoug-Ebe',
                'Elig-Edzoa',
                'Messa',
                'Nkol-Eton',
                'Cité Verte',
                'Damas',
                'Poste Centrale',
                'Nkol-Afeme',
                'Tsinga',
                'Melen',
                'Nkolndongo',
                'Elig-Effa',
                'Mballa II',
                'Nkolmesseng',
                'Olembe',
                'Nkomo',
                'Anguissa',
                'Madagascar',
                'Mvog-Atangana-Mballa',
                'Obobogo',
                'Biyem-Assi Carrefour',
                'Cité Verte',
                'Nkoldongo',
                'Carrière',
                'Biteng',
                'Ahala',
            ],

            // ─── BAFOUSSAM ────────────────────────────────────────────────────
            'Bafoussam' => [
                'Banengo',
                'Famla',
                'Djeleng',
                'Tamdja',
                'Ndiengdam',
                'Tougang Vilage',
                'Kamkop',
                'Ngouache',
                'Tsesse',
                'Centre-ville',
                'Kouoptamo',
                'Quartier Administratif',
                'Nguelemendouka',
                'Fô-Ongba',
                'Koptchou',
                'Kwa Kwa',
                'Djénaré',
                'Toumzan',
            ],

            // ─── BAMENDA ──────────────────────────────────────────────────────
            'Bamenda' => [
                'Up Station',
                'Old Town',
                'Commercial Avenue',
                'Ntarikon',
                'Mankon',
                'Nkwen',
                'Mile 2',
                'Mile 4',
                'Small Mankon',
                'Mulang',
                'Azire',
                'Ngemba',
                'Hospital Round About',
                'Ngeng',
                'Bayelle',
            ],

            // ─── GAROUA ───────────────────────────────────────────────────────
            'Garoua' => [
                'Marouaré',
                'Poumpoumré',
                'Bocklé',
                'Djamboutou',
                'Roumdé-Adjia',
                'Souaré',
                'Centre Commercial',
                'Ridel',
                'Lopéré',
                'Ngong',
                'Foulbéré',
                'Bibemi Quarter',
                'Administratif',
            ],

            // ─── MAROUA ───────────────────────────────────────────────────────
            'Maroua' => [
                'Domayo',
                'Hardé',
                'Kakataré',
                'Pont Vert',
                'Dougoy',
                'Palar',
                'Zokok',
                'Lopéré',
                'Founangué',
                'Kodek',
                'Boula-Iblis',
                'Adakol',
                'Barza',
            ],

            // ─── NGAOUNDÉRÉ ──────────────────────────────────────────────────
            'Ngaoundéré' => [
                'Baladji',
                'Dang',
                'Sabongari',
                'Joli-Soir',
                'Mbideng',
                'Burkina',
                'Mayo-Doua',
                'Hosséré',
                'Centre Administratif',
                'Marché Central',
                'Laïté',
                'Malang',
            ],

            // ─── KRIBI ────────────────────────────────────────────────────────
            'Kribi' => [
                'Centre-ville',
                'Talla',
                'Mpangou',
                'Bikondo',
                'Afan Mabe',
                'Ngovayang',
                'Bandevouri',
                'Frontière',
                'Camp Yabassi',
            ],

            // ─── LIMBÉ ────────────────────────────────────────────────────────
            'Limbé' => [
                'Bota',
                'Church Street',
                'Down Beach',
                'Clerks Quarter',
                'New Layout',
                'Motowoh',
                'GRA',
                'Market',
                'Lyongo',
                'Batoke',
                'Idenau',
            ],

            // ─── BUEA ─────────────────────────────────────────────────────────
            'Buea' => [
                'Molyko',
                'Bonduma',
                'Great Soppo',
                'Small Soppo',
                'Clerks Quarter',
                'Mile 16',
                'Mile 17',
                'Bolifamba',
                'Muea',
                'Bokwango',
                'GRA',
                'Federal Quarter',
                'Wokoko',
                'Bova',
                'Lysoka',
            ],

            // ─── BERTOUA ──────────────────────────────────────────────────────
            'Bertoua' => [
                'Centre-ville',
                'Haoussa',
                'Mokolo',
                'Madagascar',
                'Derrière la Préfecture',
                'Nkolbikon',
                'Administratif',
                'Souck',
                'Plateau',
            ],

            // ─── EBOLOWA ──────────────────────────────────────────────────────
            'Ebolowa' => [
                'Centre-ville',
                'Angalé',
                'Nkoadjap',
                'Nkoloveng',
                'Nko-Elanga',
                'Administratif',
                'Marché',
                'Nkol Nda',
            ],

            // ─── EDÉA ─────────────────────────────────────────────────────────
            'Edéa' => [
                'Centre-ville',
                'Éléphant',
                'ALUCAM Cité',
                'Mbanga Bakoko',
                'Bassa Mbénga',
                'Administratif',
                'Ndjok',
            ],

            // ─── NKONGSAMBA ───────────────────────────────────────────────────
            'Nkongsamba' => [
                'Centre-ville',
                'Bafaka',
                'Cabosse',
                'Njombe',
                'Baré-Bakem',
                'Haoussa',
                'Administratif',
            ],

            // ─── KUMBA ────────────────────────────────────────────────────────
            'Kumba' => [
                'Mbonge Road',
                'Mile 4',
                'Fiango',
                'Buea Road',
                'Light Up',
                'Kake',
                'Marché Central',
            ],

            // ─── BAFIA ────────────────────────────────────────────────────────
            'Bafia' => [
                'Centre-ville',
                'Administratif',
                'Marché',
                'Mbam',
                'Ngambe',
            ],

            // ─── FOUMBAN ──────────────────────────────────────────────────────
            'Foumban' => [
                'Centre-ville',
                'Palais Royal',
                'Haoussa',
                'Njiné',
                'Ngouon',
                'Koutaba',
            ],

            // ─── DSCHANG ──────────────────────────────────────────────────────
            'Dschang' => [
                'Centre-ville',
                'Foto',
                'Fongo-Tongo',
                'Tsinkop',
                'Haoussa',
                'Penka-Michel',
                'Toutsang',
            ],

            // ─── SANGMÉLIMA ───────────────────────────────────────────────────
            'Sangmélima' => [
                'Centre-ville',
                'Administratif',
                'Marché',
                'Mvom',
                'Biwong',
            ],

            // ─── KOUSSÉRI ─────────────────────────────────────────────────────
            'Kousséri' => [
                'Centre-ville',
                'Administratif',
                'Guerléou',
                'Marché Central',
                'Moundoul',
            ],

            // ─── MEIGANGA ─────────────────────────────────────────────────────
            'Meiganga' => [
                'Centre-ville',
                'Administratif',
                'Haoussa',
                'Tcholliré',
            ],

            // ─── TIBATI ───────────────────────────────────────────────────────
            'Tibati' => [
                'Centre-ville',
                'Haoussa',
                'Administratif',
            ],
        ];
    }
}
