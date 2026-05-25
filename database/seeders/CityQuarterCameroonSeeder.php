<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\Quarter;
use Illuminate\Database\Seeder;

/**
 * Données réelles : villes et quartiers CEMAC + UEMOA (Afrique subsaharienne francophone).
 * Idempotent — utilise firstOrCreate sur (name, country).
 *
 * Usage : php artisan db:seed --class=CityQuarterCameroonSeeder
 */
class CityQuarterCameroonSeeder extends Seeder
{
    public function run(): void
    {
        foreach ($this->data() as $country => $cities) {
            foreach ($cities as $cityName => $quarters) {
                $city = City::firstOrCreate(
                    ['name' => $cityName, 'country' => $country],
                );

                foreach ($quarters as $quarterName) {
                    Quarter::firstOrCreate([
                        'name' => $quarterName,
                        'city_id' => $city->id,
                    ]);
                }

                $this->command->line("  ✓ [{$country}] {$cityName} (".count($quarters).' quartiers)');
            }
        }

        $this->command->info('Villes et quartiers CEMAC + UEMOA importés.');
    }

    /** @return array<string, array<string, list<string>>> */
    private function data(): array
    {
        return [

            // ══════════════════════════════════════════════════════════════════
            // CEMAC
            // ══════════════════════════════════════════════════════════════════

            'Cameroun' => [
                'Douala' => [
                    'Akwa', 'Bonanjo', 'Bonapriso', 'Bonamoussadi', 'Bonabéri',
                    'Deido', 'Bassa', 'New Bell', 'Makepe', 'Logpom', 'Kotto',
                    'Ndokotti', 'Cité des Palmiers', 'PK 8', 'PK 10', 'PK 13',
                    'PK 14', 'Ndogbong', 'Ndogpassi', 'Bangué', 'Mbanga Pongo',
                    'Denver', 'Village', 'Njo-njo', 'Newtown', 'Ndokouhe',
                    'Beedi', 'Brazzaville', 'Bepanda', 'Nyalla', 'Logbessou',
                    'Sodiko', 'Ndobo', 'Boko', 'Nylon', 'Yassa', 'Japoma',
                    'Cité SIC', 'Koumassi', 'Bilonguè',
                ],
                'Yaoundé' => [
                    'Bastos', 'Nlongkak', 'Mvan', 'Essos', 'Ngousso',
                    'Centre-ville', 'Mvog-Mbi', 'Biyem-Assi', 'Mendong', 'Odza',
                    'Ekounou', 'Mimboman', 'Nsimeyong', 'Omnisport', 'Emana',
                    'Nkolbisson', 'Efoulan', 'Simbock', 'Etoa-Meki', 'Kondengui',
                    'Ngoa-Ekélé', 'Mvog-Ada', 'Fouda', 'Etoug-Ebe', 'Elig-Edzoa',
                    'Messa', 'Nkol-Eton', 'Cité Verte', 'Damas', 'Tsinga',
                    'Melen', 'Nkolndongo', 'Elig-Effa', 'Olembe', 'Anguissa',
                    'Madagascar', 'Obobogo', 'Ahala', 'Carrière', 'Biteng',
                ],
                'Bafoussam' => [
                    'Banengo', 'Famla', 'Djeleng', 'Tamdja', 'Ndiengdam',
                    'Tougang Vilage', 'Kamkop', 'Ngouache', 'Tsesse',
                    'Centre-ville', 'Kouoptamo', 'Quartier Administratif',
                    'Kwa Kwa', 'Djénaré', 'Toumzan',
                ],
                'Bamenda' => [
                    'Up Station', 'Old Town', 'Commercial Avenue', 'Ntarikon',
                    'Mankon', 'Nkwen', 'Mile 2', 'Mile 4', 'Small Mankon',
                    'Mulang', 'Azire', 'Ngemba', 'Bayelle',
                ],
                'Garoua' => [
                    'Marouaré', 'Poumpoumré', 'Bocklé', 'Djamboutou',
                    'Roumdé-Adjia', 'Souaré', 'Centre Commercial', 'Ridel',
                    'Lopéré', 'Ngong', 'Foulbéré',
                ],
                'Maroua' => [
                    'Domayo', 'Hardé', 'Kakataré', 'Pont Vert', 'Dougoy',
                    'Palar', 'Zokok', 'Founangué', 'Kodek', 'Boula-Iblis',
                ],
                'Ngaoundéré' => [
                    'Baladji', 'Dang', 'Sabongari', 'Joli-Soir', 'Mbideng',
                    'Burkina', 'Mayo-Doua', 'Centre Administratif', 'Marché Central',
                ],
                'Kribi' => [
                    'Centre-ville', 'Talla', 'Mpangou', 'Bikondo', 'Afan Mabe',
                    'Camp Yabassi',
                ],
                'Limbé' => [
                    'Bota', 'Church Street', 'Down Beach', 'Clerks Quarter',
                    'New Layout', 'Motowoh', 'GRA', 'Batoke', 'Idenau',
                ],
                'Buea' => [
                    'Molyko', 'Bonduma', 'Great Soppo', 'Small Soppo',
                    'Clerks Quarter', 'Mile 16', 'Mile 17', 'Bolifamba',
                    'Muea', 'Bokwango', 'GRA', 'Wokoko',
                ],
                'Bertoua' => [
                    'Centre-ville', 'Haoussa', 'Mokolo', 'Madagascar',
                    'Nkolbikon', 'Administratif', 'Plateau',
                ],
                'Ebolowa' => [
                    'Centre-ville', 'Angalé', 'Nkoadjap', 'Nkoloveng',
                    'Administratif', 'Marché', 'Nkol Nda',
                ],
                'Edéa' => [
                    'Centre-ville', 'ALUCAM Cité', 'Mbanga Bakoko',
                    'Administratif', 'Ndjok',
                ],
                'Nkongsamba' => [
                    'Centre-ville', 'Bafaka', 'Cabosse', 'Baré-Bakem',
                    'Haoussa', 'Administratif',
                ],
                'Kumba' => [
                    'Mbonge Road', 'Mile 4', 'Fiango', 'Buea Road',
                    'Light Up', 'Marché Central',
                ],
                'Dschang' => [
                    'Centre-ville', 'Foto', 'Fongo-Tongo', 'Tsinkop',
                    'Haoussa', 'Penka-Michel',
                ],
                'Foumban' => [
                    'Centre-ville', 'Palais Royal', 'Haoussa', 'Njiné', 'Ngouon',
                ],
                'Bafia' => ['Centre-ville', 'Administratif', 'Marché', 'Ngambe'],
                'Sangmélima' => ['Centre-ville', 'Administratif', 'Marché', 'Mvom'],
                'Kousséri' => ['Centre-ville', 'Administratif', 'Guerléou', 'Marché Central'],
                'Meiganga' => ['Centre-ville', 'Administratif', 'Haoussa'],
            ],

            'Gabon' => [
                'Libreville' => [
                    'Akanda', 'Angondjé', 'Awendjé', 'Batavéa', 'Glass',
                    'Lalala', 'Louis', 'Nzeng-Ayong', 'Owendo', 'PK5', 'PK8',
                    'PK9', 'PK12', 'Pont-Rouge', 'Sotega', 'IAI', 'Centre-ville',
                    'Gros Bouquet', 'Alibandeng', 'Camp de Gaulle',
                ],
                'Port-Gentil' => [
                    'Centre-ville', 'Balise', 'Bois des Coqs', 'Cité Shell',
                    'Ozouri', 'Village 2', 'Grand Village', 'Abattoir',
                ],
                'Franceville' => [
                    'Centre-ville', 'Administratif', 'Moanda', 'Lékoni',
                ],
                'Oyem' => ['Centre-ville', 'Administratif', 'Marché'],
                'Mouila' => ['Centre-ville', 'Administratif', 'Marché'],
            ],

            'Congo (République du)' => [
                'Brazzaville' => [
                    'Bacongo', 'Djiri', 'Makélékélé', 'Madibou', 'Mfilou',
                    'Moungali', 'Ouenzé', 'Poto-Poto', 'Talangaï',
                    'Centre-ville', 'Plateau des 15 Ans', 'Mansimou',
                    'Gombe-Sudzou', 'Kingasani',
                ],
                'Pointe-Noire' => [
                    'Centre-ville', 'Loandjili', 'Ngoyo', 'Mvou-Mvou',
                    'Tie-Tie', 'Mont-Kamba', 'Fond Tié-Tié',
                ],
                'Dolisie' => ['Centre-ville', 'Administratif', 'Marché'],
                'Nkayi' => ['Centre-ville', 'Administratif'],
            ],

            'République Centrafricaine' => [
                'Bangui' => [
                    'Boy-Rabe', 'Centre-ville', 'Combattant', 'Fatima',
                    'Gobongo', 'KM5', 'Lakouanga', 'Miskine', 'Ndress',
                    'Ouango', 'Pétévo', 'Bimbo', 'Sica I', 'Sica II',
                    'Sica III', 'Boeing',
                ],
                'Berberati' => ['Centre-ville', 'Administratif'],
                'Bossangoa' => ['Centre-ville', 'Administratif'],
            ],

            'Tchad' => [
                "N'Djamena" => [
                    'Moursal', 'Farcha', 'Ndjari', 'Chagoua', 'Dembé',
                    'Goudji', 'Bololo', 'Mille Sept Cent', 'Diguel',
                    'Kabalaye', 'Paris Congo', 'Atrone', 'Klemat',
                    'Centre-ville', 'Ngueli',
                ],
                'Moundou' => [
                    'Centre-ville', 'Administratif', 'Marché Central',
                    'Gaoui', 'Béwara',
                ],
                'Sarh' => ['Centre-ville', 'Administratif', 'Marché'],
                'Abéché' => ['Centre-ville', 'Administratif', 'Marché'],
            ],

            'Guinée Équatoriale' => [
                'Malabo' => [
                    'Centro', 'Ela Nguema', 'Caracolas', 'Hacienda', 'Riaba',
                    'Secretaría de Estado', 'Nueva Esperanza',
                ],
                'Bata' => [
                    'Centro', 'Mbini', 'Cogo', 'Ebebiyin', 'Akonibe',
                ],
            ],

            // ══════════════════════════════════════════════════════════════════
            // UEMOA
            // ══════════════════════════════════════════════════════════════════

            "Côte d'Ivoire" => [
                'Abidjan' => [
                    'Plateau', 'Cocody', 'Yopougon', 'Adjamé', 'Marcory',
                    'Koumassi', 'Treichville', 'Abobo', 'Attécoubé', 'Port-Bouët',
                    'Bingerville', 'Williamsville', 'Deux-Plateaux', 'Riviera',
                    'Zone 4', 'Angré', 'Palmeraie', 'Bassam Road', 'Vridi',
                    'Locodjro', 'Blokosso', 'Banco 1', 'Banco 2',
                ],
                'Yamoussoukro' => [
                    'Centre-ville', 'Dioulakro', 'N\'Zuessi', 'Quartier Millionnaire',
                    'Habitat', 'Fétékro', 'Assabou',
                ],
                'Bouaké' => [
                    'Air France', 'Commerce', 'Nimbo', 'Sokoura',
                    'Broukro', 'Belleville', 'Koko',
                ],
                'Daloa' => ['Centre-ville', 'Commerce', 'Lobia', 'Tazibouo'],
                'San Pedro' => ['Centre-ville', 'Bardot', 'Bardo', 'Cité'],
                'Man' => ['Centre-ville', 'Administratif', 'Marché'],
                'Korhogo' => ['Centre-ville', 'Administratif', 'Kombolokoura'],
            ],

            'Sénégal' => [
                'Dakar' => [
                    'Plateau', 'Médina', 'Fann-Point E-Amitié', 'Almadies',
                    'Parcelles Assainies', 'Ouakam', 'Yoff', 'Grand Dakar',
                    'Pikine', 'Guédiawaye', 'HLM', 'Liberté', 'Mermoz',
                    'Sacré-Cœur', 'Point E', 'Ngor', 'Mamelles', 'Cambérène',
                    'Thiaroye', 'Rufisque', 'Keur Massar', 'Mbao', 'Diamaguene',
                ],
                'Saint-Louis' => [
                    'Île de Saint-Louis', 'Sor', 'Guet Ndar', 'Hydro Base',
                    'Léona', 'Bango',
                ],
                'Thiès' => [
                    'Centre-ville', 'Randoulène', 'Mbambara', 'Medina Fall',
                    'Génie', 'Escale',
                ],
                'Kaolack' => ['Centre-ville', 'Léona', 'Ndangane', 'Touba Ndorong'],
                'Ziguinchor' => ['Centre-ville', 'Boucotte', 'Kandialang', 'Santhiaba'],
                'Touba' => ['Centre-ville', 'Darou Khoudoss', 'Guédé'],
            ],

            'Mali' => [
                'Bamako' => [
                    'Commune I', 'Commune II', 'Commune III', 'Commune IV',
                    'Commune V', 'Commune VI', 'Hippodrome', 'Badalabougou',
                    'ACI 2000', 'Hamdallaye', 'Magnambougou', 'Lafiabougou',
                    'Kalaban-Coura', 'Sotuba', 'Sebenikoro', 'Missira',
                    'Niarela', 'Medina Coura', 'Quinzambougou', 'Baco Djicoroni',
                    'Sabalibougou',
                ],
                'Sikasso' => ['Centre-ville', 'Administratif', 'Wayerma', 'Sanoubougou'],
                'Ségou' => ['Centre-ville', 'Administratif', 'Médine', 'Pelengana'],
                'Mopti' => ['Centre-ville', 'Komoguel', 'Gangal', 'Sévaré'],
                'Gao' => ['Centre-ville', 'Administratif', 'Château', 'Sossokoira'],
                'Kayes' => ['Centre-ville', 'Administratif', 'Lafiabougou'],
                'Koutiala' => ['Centre-ville', 'Administratif', 'Koko'],
            ],

            'Burkina Faso' => [
                'Ouagadougou' => [
                    'Ouaga 2000', 'Cissin', 'Dassasgho', 'Gounghin',
                    'Hamdallaye', 'Karpala', 'Kossodo', 'Patte d\'Oie',
                    'Tanghin', 'Wemtenga', 'Zangouettin', 'Zogona',
                    'Balkuy', 'Bendogo', 'Bilbalogo', 'Boulmiougou',
                    'Dagnoen', 'Kamsaoghin', 'Kilwin', 'Koulouba',
                    'Larlé', 'Nonghin', 'Rood-Woko', 'Secteur 15',
                    'Waghin', 'Wayalghin', 'Zone du Bois',
                ],
                'Bobo-Dioulasso' => [
                    'Accart-Ville', 'Koko', 'Dafra', 'Sarfalao', 'Secteur 25',
                    'Colsama', 'Dogona', 'Lafiabougou', 'Tounouma',
                ],
                'Koudougou' => ['Centre-ville', 'Administratif', 'Secteur 4'],
                'Ouahigouya' => ['Centre-ville', 'Administratif', 'Marché'],
                'Banfora' => ['Centre-ville', 'Administratif', 'Secteur 3'],
            ],

            'Togo' => [
                'Lomé' => [
                    'Agbalépédogan', 'Bè', 'Hédzranawoé', 'Kodjoviakopé',
                    'Tokoin', 'Adidogomé', 'Agoè-Nyivé', 'Avédji', 'Dékon',
                    'Hanoukopé', 'Nyékonakpoè', 'Baguida', 'Cacaveli',
                    'Djidjolé', 'Kégué', 'Zanguéra', 'Ablogamé',
                    'Aflao Gakli', 'Amégbapui', 'Bè-Kpota',
                ],
                'Kara' => ['Centre-ville', 'Administratif', 'Kpéwa', 'Lassa'],
                'Sokodé' => ['Centre-ville', 'Administratif', 'Kpaza'],
                'Atakpamé' => ['Centre-ville', 'Administratif', 'Agbandi'],
                'Tsévié' => ['Centre-ville', 'Administratif'],
            ],

            'Bénin' => [
                'Cotonou' => [
                    'Akpakpa', 'Cadjèhoun', 'Fidjrossè', 'Ganhi', 'Jéricho',
                    'Mènontin', 'Sikècodji', 'Vodjè', 'Xwlacodji', 'Gbégamey',
                    'Towéta', 'Agla', 'Dantokpa', 'Haie Vive', 'Aidjèdo',
                    'Godomey', 'Kpankpan', 'Menontin', 'Sainte Rita', 'Zogbo',
                ],
                'Porto-Novo' => [
                    'Centre-ville', 'Ouando', 'Tokpota', 'Agbokou', 'Honvié',
                    'Djassin', 'Avlékété',
                ],
                'Parakou' => [
                    'Centre-ville', 'Administratif', 'Zongo', 'Madécali', 'Kpébié',
                ],
                'Abomey-Calavi' => [
                    'Centre-ville', 'Godomey', 'Fidjrossè Kpota', 'Togba',
                ],
                'Natitingou' => ['Centre-ville', 'Administratif', 'Kota'],
            ],

            'Niger' => [
                'Niamey' => [
                    'Plateau', 'Niamey 1', 'Niamey 2', 'Niamey 3', 'Niamey 4',
                    'Niamey 5', 'Bobiel', 'Foulan Koira', 'Gamkalle', 'Lazaret',
                    'Boukoki', 'Koira Kano', 'Saga', 'Yantala', 'Talladjé',
                    'Pays-Bas', 'Bagalam', 'Dar Es Salam', 'Djambal Kayna',
                ],
                'Zinder' => ['Centre-ville', 'Administratif', 'Zengou', 'Kara-Kara'],
                'Maradi' => ['Centre-ville', 'Administratif', 'Dan Goulbi'],
                'Tahoua' => ['Centre-ville', 'Administratif', 'Birnin Konni'],
                'Agadez' => ['Centre-ville', 'Administratif', 'Marché'],
            ],

            'Guinée-Bissau' => [
                'Bissau' => [
                    'Bandim', 'Bairro Militar', 'Cupilom', 'Antula',
                    'Santa Luzia', 'Belem', 'Missirá', 'Pluba', 'Quelélé',
                ],
                'Bafatá' => ['Centre-ville', 'Administratif'],
                'Gabú' => ['Centre-ville', 'Administratif'],
            ],
        ];
    }
}
