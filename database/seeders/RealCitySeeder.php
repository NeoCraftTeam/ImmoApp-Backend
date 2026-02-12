<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\City;
use App\Models\Quarter;
use Illuminate\Database\Seeder;

class RealCitySeeder extends Seeder
{
    /**
     * Seed all real Cameroonian cities and quarters, organized by region.
     */
    public function run(): void
    {
        // Clear existing data
        Quarter::query()->forceDelete();
        City::query()->forceDelete();

        $totalCities = 0;
        $totalQuarters = 0;

        foreach ($this->getCitiesWithQuarters() as $cityName => $quarters) {
            $city = City::create(['name' => $cityName]);

            foreach ($quarters as $quarterName) {
                Quarter::create([
                    'name' => $quarterName,
                    'city_id' => $city->id,
                ]);
            }

            $totalCities++;
            $totalQuarters += count($quarters);
        }

        $this->command->info("Imported {$totalCities} Cameroonian cities with {$totalQuarters} quarters.");
    }

    /**
     * Complete list of Cameroonian cities and their quarters/neighborhoods.
     *
     * @return array<string, list<string>>
     */
    private function getCitiesWithQuarters(): array
    {
        return array_merge(
            $this->regionCentre(),
            $this->regionLittoral(),
            $this->regionOuest(),
            $this->regionNordOuest(),
            $this->regionSudOuest(),
            $this->regionNord(),
            $this->regionExtremeNord(),
            $this->regionAdamaoua(),
            $this->regionEst(),
            $this->regionSud(),
        );
    }

    // ──────────────────────────────────────────────
    //  REGION DU CENTRE  (Chef-lieu : Yaoundé)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, list<string>>
     */
    private function regionCentre(): array
    {
        return [
            // ── Yaoundé (Capitale politique) ──
            'Yaoundé' => [
                // Yaoundé I (Nlongkak)
                'Bastos', 'Nlongkak', 'Tsinga', 'Elig-Essono', 'Quartier du Lac',
                'Centre Administratif', 'Hippodrome', 'Golf',
                // Yaoundé II (Tsinga)
                'Mokolo', 'Briqueterie', 'Madagascar', 'Messa', 'Carrière',
                'Cité Verte', 'Nkomkana', 'Nsam',
                // Yaoundé III (Efoulan)
                'Efoulan', 'Nsimeyong', 'Mvog-Ada', 'Mvog-Atangana Mballa',
                'Obobogo', 'Etoa-Meki', 'Nkolmesseng',
                // Yaoundé IV (Kondengui)
                'Kondengui', 'Ekounou', 'Odza', 'Mimboman', 'Nkoldongo',
                'Mvog-Betsi', 'Emombo', 'Awae', 'Mfandena', 'Omnisport',
                'Tongolo', 'Elig-Edzoa', 'Mballa II',
                // Yaoundé V (Essos)
                'Essos', 'Ngousso', 'Mfandena', 'Ngoulmekong', 'Anguissa',
                'Nkolmesseng', 'Emana', 'Mini Ferme',
                // Yaoundé VI (Biyem-Assi)
                'Biyem-Assi', 'Mendong', 'Melen', 'Simbock', 'Etoug-Ebe',
                'Jouvence', 'TKC', 'Mvog-Mbi', 'Nkolbikok',
                // Yaoundé VII (Nkolbisson)
                'Nkolbisson', 'Oyom-Abang', 'Santa Barbara', 'Minkoameyos',
                'Nkolafamba',
                // Autres quartiers notables
                'Obili', 'Ngoa-Ekelle', 'Cradat', 'Damas', 'Elig-Effa',
                'Nkomo', 'Mbankolo', 'Ahala', 'Olembe', 'Nkol-Eton',
                'Biteng', 'Messassi', 'Mvolyé', 'Nkom-Kana',
                'Eleveur', 'Tam-Tam Weekend', 'Montée Jouvence',
                'Rond-Point Nlongkak', 'Carrefour Warda', 'Bata Nlongkak',
            ],

            // ── Mbalmayo ──
            'Mbalmayo' => [
                'Centre Commercial', 'Oyak', 'Nkol-Ngui', 'Abang', 'Akom',
                'Nkol-Mefou', 'Plateau', 'Quartier Administratif', 'Bilik',
            ],

            // ── Obala ──
            'Obala' => [
                'Centre-ville', 'Nkol-Afamba', 'Nkol-Melen', 'Quartier Haoussa',
                'Minkama', 'Nkolfoulou',
            ],

            // ── Bafia ──
            'Bafia' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Biakoa', 'Goufan', 'Gouife', 'Bangangte Bafia',
            ],

            // ── Nanga-Eboko ──
            'Nanga-Eboko' => [
                'Centre-ville', 'Quartier Administratif', 'Nkometou',
                'Bibey', 'Nguinda',
            ],

            // ── Monatélé ──
            'Monatélé' => [
                'Centre-ville', 'Quartier Administratif', 'Obout',
                'Elat', 'Nkolmelen',
            ],

            // ── Akonolinga ──
            'Akonolinga' => [
                'Centre-ville', 'Quartier Administratif', 'Nkong-Abok',
                'Quartier Haoussa', 'Mvomeka',
            ],

            // ── Ntui ──
            'Ntui' => [
                'Centre-ville', 'Quartier Administratif', 'Yoko Road',
                'Natchigal',
            ],

            // ── Mfou ──
            'Mfou' => [
                'Centre-ville', 'Quartier Administratif', 'Nsimalen',
                'Awae', 'Nkol-Afamba',
            ],

            // ── Soa ──
            'Soa' => [
                'Centre-ville', 'Campus Universitaire', 'Nkolbisson Soa',
                'Plateau', 'Quartier Chefferie',
            ],

            // ── Esse ──
            'Esse' => [
                'Centre-ville', 'Quartier Administratif', 'Zoétélé',
            ],

            // ── Okola ──
            'Okola' => [
                'Centre-ville', 'Quartier Administratif', 'Lobo',
            ],

            // ── Evodoula ──
            'Evodoula' => [
                'Centre-ville', 'Quartier Administratif', 'Nkolmékok',
            ],

            // ── Ngoumou ──
            'Ngoumou' => [
                'Centre-ville', 'Quartier Administratif', 'Bikok',
            ],

            // ── Ayos ──
            'Ayos' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
            ],

            // ── Bokito ──
            'Bokito' => [
                'Centre-ville', 'Quartier Administratif', 'Ombessa',
            ],

            // ── Ombessa ──
            'Ombessa' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Yoko ──
            'Yoko' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
            ],

            // ── Ebebda ──
            'Ebebda' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Bot-Makak ──
            'Bot-Makak' => [
                'Centre-ville', 'Quartier Administratif', 'Song-Mpeck',
            ],

            // ── Matomb ──
            'Matomb' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Nguibassal ──
            'Nguibassal' => [
                'Centre-ville', 'Quartier Administratif',
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  REGION DU LITTORAL  (Chef-lieu : Douala)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, list<string>>
     */
    private function regionLittoral(): array
    {
        return [
            // ── Douala (Capitale économique) ──
            'Douala' => [
                // Douala I (Bonanjo)
                'Bonanjo', 'Akwa', 'Bonapriso', 'Bali', 'Joss', 'Bonadoumbe',
                'Akwa-Nord', 'Deïdo',
                // Douala II (New Bell)
                'New Bell', 'Bassa', 'Nkongmondo', 'Congo', 'Ndogbong',
                'Nkoulouloun', 'Mboppi', 'Barcelona', 'Ndog-Bong',
                // Douala III (Logbaba)
                'Logbaba', 'Logpom', 'Nyalla', 'Ndogpassi', 'Cité des Palmiers',
                'Yassa', 'PK8', 'PK9', 'PK10', 'PK11', 'PK12', 'PK13', 'PK14',
                'PK15', 'PK16', 'PK17', 'Kotto', 'Ngangué', 'Ndoghem',
                'Lendi', 'Bilongue', 'Soboum',
                // Douala IV (Bonassama)
                'Bonassama', 'Bonabéri', 'Sodiko', 'Bekoko', 'Ndobo',
                'Mabanda', 'Bonanloka', 'Ngwele', 'Kombo', 'Village',
                // Douala V (Kotto)
                'Bépanda', 'Ndokoti', 'Makepe', 'Bonamoussadi', 'Logbessou',
                'Denver', 'Bonamikano', 'Kotto-Village', 'Missokè',
                'Rond-Point Deïdo', 'Ancien Dalip', 'Bépanda Omnisport',
                'Cite SIC', 'Total Makepe', 'Ange Raphaël', 'Tradex Bonamoussadi',
                // Autres quartiers notables
                'Mbanga Bakoko', 'Bessengue', 'Nylon', 'Ndogbati',
                'Japoma', 'Cité Cicam', 'Ange Raphaël', 'Beedi',
                'Grand Moulin', 'Ndogmbe', 'Koumassi', 'Nkapa',
                'Mboppi', 'Ngodi', 'Aéroport',
            ],

            // ── Edéa ──
            'Edéa' => [
                'Centre-ville', 'Bilalang', 'Pongo', 'Mbanda', 'Akwa Nord',
                'Quartier Administratif', 'Pont du Wouri', 'Ngalang',
                'Ndog-Bea', 'Ekité',
            ],

            // ── Nkongsamba ──
            'Nkongsamba' => [
                'Gare', 'Quartier Haoussa', 'Bonabéri', 'Baré', 'Melong',
                'Quartier Administratif', 'Elung', 'Ngaméné', 'Quartier Bamoun',
                'Quartier Bamiléké', 'Nkongsamba Centre', 'Madélé',
            ],

            // ── Loum ──
            'Loum' => [
                'Centre-ville', 'Gare', 'Quartier Haoussa', 'Nlonako',
                'Quartier Administratif', 'Quartier Chefferie', 'Kumba Road',
            ],

            // ── Manjo ──
            'Manjo' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Ekonjo', 'Nkongsamba Road',
            ],

            // ── Dibombari ──
            'Dibombari' => [
                'Centre-ville', 'Quartier Administratif', 'Souza',
                'Quartier Chefferie',
            ],

            // ── Bonabéri ──
            'Bonabéri' => [
                'Sodiko', 'Bekoko', 'Mabanda', 'Ndobo', 'Ngwele',
            ],

            // ── Mbanga ──
            'Mbanga' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Quartier Elung',
            ],

            // ── Yabassi ──
            'Yabassi' => [
                'Centre-ville', 'Quartier Administratif', 'Bodiman',
                'Quartier Bonabéri',
            ],

            // ── Dizangué ──
            'Dizangué' => [
                'Centre-ville', 'Quartier Administratif', 'Mouanko',
            ],

            // ── Mouanko ──
            'Mouanko' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Penja ──
            'Penja' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Quartier Plantation', 'Njombe',
            ],

            // ── Njombe-Penja ──
            'Njombe-Penja' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  REGION DE L'OUEST  (Chef-lieu : Bafoussam)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, list<string>>
     */
    private function regionOuest(): array
    {
        return [
            // ── Bafoussam ──
            'Bafoussam' => [
                'Djeleng', 'Tamdja', 'Famla', 'Banengo', 'King-Place',
                'Tougang', 'Ndiangdam', 'Kamkop', 'Bamougoum', 'Tomdjo',
                'Quartier Administratif', 'Marché A', 'Marché B', 'Djemoun',
                'Tyo-Ville', 'Quartier Haoussa', 'Ndiangdam', 'Kouogang',
                'Baleng', 'Bandjoun Route', 'Njintout', 'Kena', 'Ngouache',
                'Sakbayemé', 'Pont Noun',
            ],

            // ── Dschang ──
            'Dschang' => [
                'Foto', 'Tsinkop', 'Fiankop', 'Keleng', 'Mingmeto',
                'Campus Universitaire', 'Quartier Administratif', 'Foreke-Dschang',
                'Haoussa', 'Ngui', 'Siteu', 'Quartier des Enseignants',
                'Vallée', 'Tchoualé', 'Paid Ground',
            ],

            // ── Mbouda ──
            'Mbouda' => [
                'Centre-ville', 'Quartier Administratif', 'Bamenkombo',
                'Babété', 'Bamessingué', 'Quartier Haoussa', 'Bamesso',
                'Petit Marché', 'Grand Marché',
            ],

            // ── Foumban ──
            'Foumban' => [
                'Njinka', 'Koupara', 'Njintout', 'Quartier Haoussa', 'Manka',
                'Quartier Administratif', 'Koutaba', 'Palais Royal',
                'Fontaine', 'Mantoum',
            ],

            // ── Bangangté ──
            'Bangangté' => [
                'Centre-ville', 'Quartier Administratif', 'Batié', 'Bazou',
                'Bagnoun', 'Bangoulap', 'Quartier Haoussa', 'Bangoua',
            ],

            // ── Bafang ──
            'Bafang' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Bana', 'Bandja', 'Kekem', 'Fotouni', 'Bakou',
            ],

            // ── Bandjoun ──
            'Bandjoun' => [
                'Centre-ville', 'Quartier Administratif', 'Djebem',
                'Famleng', 'Hiala', 'Chefferie', 'Poumougne',
            ],

            // ── Foumbot ──
            'Foumbot' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Kouoptamo', 'Fossang', 'Kouptamo',
            ],

            // ── Kékem ──
            'Kékem' => [
                'Centre-ville', 'Quartier Administratif', 'Banwa',
                'Quartier Haoussa',
            ],

            // ── Banganté ──
            'Tonga' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Baré',
            ],

            // ── Bazou ──
            'Bazou' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Fokoué ──
            'Fokoué' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Penka-Michel ──
            'Penka-Michel' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Santchou ──
            'Santchou' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Galim ──
            'Galim' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Koutaba ──
            'Koutaba' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
            ],

            // ── Massangam ──
            'Massangam' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Batié ──
            'Batié' => [
                'Centre-ville', 'Quartier Administratif', 'Batcham',
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  REGION DU NORD-OUEST  (Chef-lieu : Bamenda)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, list<string>>
     */
    private function regionNordOuest(): array
    {
        return [
            // ── Bamenda ──
            'Bamenda' => [
                'Commercial Avenue', 'Nkwen', 'Mile 4', 'Old Town',
                'Up Station', 'Below Foncha', 'Ntarikon', 'Musang',
                'City Chemist', 'Hospital Roundabout', 'Cow Street',
                'Meta Quarter', 'Atuazire', 'Bayelle', 'Small Mankon',
                'Big Mankon', 'Mile 3', 'Mile 2', 'Ngomgham',
                'Alabukam', 'Mulang', 'Azire', 'Sisia',
                'Food Market', 'Savannah', 'Finance Junction',
                'Veterinary Junction', 'Bambili', 'Bambui',
            ],

            // ── Kumbo ──
            'Kumbo' => [
                'Centre-ville', 'Tobin', 'Squares', 'Mbveh', 'Kikaikelaki',
                'Quartier Administratif', 'Shisong', 'Kumbo Town',
            ],

            // ── Wum ──
            'Wum' => [
                'Centre-ville', 'Quartier Administratif', 'Weh',
                'Aghem', 'Naikom',
            ],

            // ── Ndop ──
            'Ndop' => [
                'Centre-ville', 'Quartier Administratif', 'Bamunka',
                'Bamessing', 'Baba I',
            ],

            // ── Fundong ──
            'Fundong' => [
                'Centre-ville', 'Quartier Administratif', 'Belo',
                'Njinikom',
            ],

            // ── Nkambe ──
            'Nkambe' => [
                'Centre-ville', 'Quartier Administratif', 'Ndu',
                'Misaje', 'Nwa',
            ],

            // ── Mbengwi ──
            'Mbengwi' => [
                'Centre-ville', 'Quartier Administratif', 'Batibo',
            ],

            // ── Batibo ──
            'Batibo' => [
                'Centre-ville', 'Quartier Administratif', 'Ashong',
            ],

            // ── Bali ──
            'Bali Nyonga' => [
                'Centre-ville', 'Quartier Administratif', 'Bawock',
            ],

            // ── Tubah ──
            'Tubah' => [
                'Centre-ville', 'Bambui', 'Bambili', 'Kedjom Ketinguh',
            ],

            // ── Santa ──
            'Santa' => [
                'Centre-ville', 'Quartier Administratif', 'Pinyin',
                'Awing', 'Akum',
            ],

            // ── Jakiri ──
            'Jakiri' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Ndu ──
            'Ndu' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Belo ──
            'Belo' => [
                'Centre-ville', 'Quartier Administratif',
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  REGION DU SUD-OUEST  (Chef-lieu : Buéa)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, list<string>>
     */
    private function regionSudOuest(): array
    {
        return [
            // ── Buéa ──
            'Buéa' => [
                'Molyko', 'Bomaka', 'Bokwango', 'Great Soppo', 'Small Soppo',
                'Mile 17', 'Bunduma', 'Buea Town', 'Check Point',
                'Clerks Quarter', 'GRA', 'Mile 16', 'Mile 18',
                'Sandpit', 'Bova', 'Muea', 'Tole',
            ],

            // ── Limbé ──
            'Limbé' => [
                'Down Beach', 'Mile 4', 'Church Street', 'Botanic Garden',
                'Bota', 'GRA', 'New Town', 'Half Mile', 'Clerks Quarter',
                'Limbe Town', 'Mile 2', 'Mile 3', 'Mile 1',
                'Garden', 'Mabeta', 'Idenau',
            ],

            // ── Kumba ──
            'Kumba' => [
                'Fiango', 'Buea Road', 'Three Corners', 'Kake', 'Meta Quarter',
                'Quartier Administratif', 'Marché', 'Kosala', 'Mbonge Road',
                'Mile 1', 'Kumba Town', 'Small Kumba',
            ],

            // ── Tiko ──
            'Tiko' => [
                'Down Beach', 'Likomba', 'Mutengene', 'Tiko Town',
                'Mondoni', 'Holforth', 'Ombe', 'Missellele',
            ],

            // ── Mutengene ──
            'Mutengene' => [
                'Centre-ville', 'Mile 17', 'Three Corners', 'Quartier Administratif',
                'Tiko Road', 'Muea Road',
            ],

            // ── Mamfe ──
            'Mamfe' => [
                'Centre-ville', 'Quartier Administratif', 'Ikom Road',
                'Bachuo Akagbe', 'Eyumojock',
            ],

            // ── Fontem ──
            'Fontem' => [
                'Centre-ville', 'Quartier Administratif', 'Menji',
            ],

            // ── Mundemba ──
            'Mundemba' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Tombel ──
            'Tombel' => [
                'Centre-ville', 'Quartier Administratif', 'Nguti',
            ],

            // ── Bangem ──
            'Bangem' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Konye ──
            'Konye' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Nguti ──
            'Nguti' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Idénau ──
            'Idénau' => [
                'Centre-ville', 'Quartier Administratif', 'Batoke',
            ],

            // ── Ekondo-Titi ──
            'Ekondo-Titi' => [
                'Centre-ville', 'Quartier Administratif',
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  REGION DU NORD  (Chef-lieu : Garoua)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, list<string>>
     */
    private function regionNord(): array
    {
        return [
            // ── Garoua ──
            'Garoua' => [
                'Roumdé Adjia', 'Yelwa', 'Bibémiré', 'Souari', 'Lopéré',
                'Poumpoumré', 'Djamboutou', 'Marouaré', 'Foulbéré',
                'Plateau', 'Ngaoundéré Road', 'Quartier Administratif',
                'Mboulaye', 'Liddiré', 'Karouari', 'Boklé',
                'Laïndé', 'Ouro-Hounou', 'Kolléré', 'Harde',
            ],

            // ── Guider ──
            'Guider' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Quartier Foulbé', 'Lam', 'Mayo-Oulo',
            ],

            // ── Pitoa ──
            'Pitoa' => [
                'Centre-ville', 'Quartier Administratif', 'Bibémi',
            ],

            // ── Poli ──
            'Poli' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Tcholliré ──
            'Tcholliré' => [
                'Centre-ville', 'Quartier Administratif', 'Rey-Bouba',
                'Madingring',
            ],

            // ── Rey-Bouba ──
            'Rey-Bouba' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Figuil ──
            'Figuil' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
            ],

            // ── Bibémi ──
            'Bibémi' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Lagdo ──
            'Lagdo' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Bénoué ──
            'Gaschiga' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Dembo ──
            'Dembo' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Touroua ──
            'Touroua' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Bashéo ──
            'Bashéo' => [
                'Centre-ville', 'Quartier Administratif',
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  REGION DE L'EXTRÊME-NORD  (Chef-lieu : Maroua)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, list<string>>
     */
    private function regionExtremeNord(): array
    {
        return [
            // ── Maroua ──
            'Maroua' => [
                'Domayo', 'Dougoi', 'Palar', 'Founangué', 'Dougoï',
                'Kakataré', 'Pitoaré', 'Zokok', 'Quartier Administratif',
                'Pont Vert', 'Hardé', 'Djarengol', 'Ouro Tchedé',
                'Meskine', 'Lopéré', 'Kongola', 'Makabaye',
                'Kodek', 'Djiddéré', 'Bamaré', 'Doualaré',
            ],

            // ── Mokolo ──
            'Mokolo' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Gawar', 'Koza', 'Quartier Foulbé',
            ],

            // ── Mora ──
            'Mora' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Kolofata', 'Tokombéré', 'Quartier Mandara',
            ],

            // ── Kousseri ──
            'Kousseri' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Logone-Birni', 'Goulfey', 'Makary', 'Quartier Arabe',
                'Quartier Kotoko',
            ],

            // ── Yagoua ──
            'Yagoua' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Kaï-Kaï', 'Maga', 'Vélé', 'Guirvidig',
            ],

            // ── Kaélé ──
            'Kaélé' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Mindif', 'Moulvoudaye', 'Guidiguis',
            ],

            // ── Koza ──
            'Koza' => [
                'Centre-ville', 'Quartier Administratif', 'Mozogo',
            ],

            // ── Maga ──
            'Maga' => [
                'Centre-ville', 'Quartier Administratif', 'Pouss',
            ],

            // ── Tokombéré ──
            'Tokombéré' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Kolofata ──
            'Kolofata' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Mindif ──
            'Mindif' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Logone-Birni ──
            'Logone-Birni' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Blangoua ──
            'Blangoua' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Goulfey ──
            'Goulfey' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Makary ──
            'Makary' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Waza ──
            'Waza' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Moulvoudaye ──
            'Moulvoudaye' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Guidiguis ──
            'Guidiguis' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Mozogo ──
            'Mozogo' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Hina ──
            'Hina' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Bourha ──
            'Bourha' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Pétté ──
            'Pétté' => [
                'Centre-ville', 'Quartier Administratif',
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  REGION DE L'ADAMAOUA  (Chef-lieu : Ngaoundéré)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, list<string>>
     */
    private function regionAdamaoua(): array
    {
        return [
            // ── Ngaoundéré ──
            'Ngaoundéré' => [
                'Joli Soir', 'Bini', 'Sabongari', 'Bamyanga', 'Dang',
                'Mbideng', 'Quartier Administratif', 'Plateau',
                'Baladji', 'Burkina', 'Onaref', 'Manwi',
                'Ndelbe', 'Madagascar', 'Gare Marchandises',
                'Malang', 'Camp SIC', 'Quartier Haoussa',
            ],

            // ── Meiganga ──
            'Meiganga' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Djohong', 'Dir', 'Ngaoundal',
            ],

            // ── Tibati ──
            'Tibati' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Lac Tison', 'Mbakaou',
            ],

            // ── Tignère ──
            'Tignère' => [
                'Centre-ville', 'Quartier Administratif', 'Galim-Tignère',
                'Kontcha',
            ],

            // ── Banyo ──
            'Banyo' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Mayo-Darlé', 'Bankim',
            ],

            // ── Ngaoundal ──
            'Ngaoundal' => [
                'Centre-ville', 'Quartier Administratif', 'Gare',
            ],

            // ── Djohong ──
            'Djohong' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Dir ──
            'Dir' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Kontcha ──
            'Kontcha' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Bankim ──
            'Bankim' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Mayo-Baléo ──
            'Mayo-Baléo' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Belel ──
            'Belel' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Galim-Tignère ──
            'Galim-Tignère' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Mbakaou ──
            'Mbakaou' => [
                'Centre-ville', 'Quartier Barrage',
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  REGION DE L'EST  (Chef-lieu : Bertoua)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, list<string>>
     */
    private function regionEst(): array
    {
        return [
            // ── Bertoua ──
            'Bertoua' => [
                'Centre-ville', 'Haoussa', 'Mokolo', 'Nkolbikon', 'Madagascar',
                'Quartier Administratif', 'Mandjou', 'Nkoumadjap',
                'Camp SIC', 'Tigaza', 'Mokolo II', 'Ydéna',
            ],

            // ── Batouri ──
            'Batouri' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Dem', 'Kambélé', 'Mbang', 'Nguélémendouka',
            ],

            // ── Yokadouma ──
            'Yokadouma' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Moloundou', 'Salapoumbé', 'Mboy',
            ],

            // ── Abong-Mbang ──
            'Abong-Mbang' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Doumé', 'Angossas',
            ],

            // ── Bélabo ──
            'Bélabo' => [
                'Centre-ville', 'Quartier Administratif', 'Gare',
                'Diang', 'Quartier Haoussa',
            ],

            // ── Garoua-Boulaï ──
            'Garoua-Boulaï' => [
                'Centre-ville', 'Quartier Administratif', 'Frontière',
                'Quartier Haoussa',
            ],

            // ── Doumé ──
            'Doumé' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Lomié ──
            'Lomié' => [
                'Centre-ville', 'Quartier Administratif', 'Ngoyla',
                'Messok',
            ],

            // ── Moloundou ──
            'Moloundou' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Kétté ──
            'Kétté' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Mbang ──
            'Mbang' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Nguélémendouka ──
            'Nguélémendouka' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Diang ──
            'Diang' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Ngoyla ──
            'Ngoyla' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Salapoumbé ──
            'Salapoumbé' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Angossas ──
            'Angossas' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Kambélé ──
            'Kambélé' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Ndélélé ──
            'Ndélélé' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Messamena ──
            'Messamena' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Mindourou ──
            'Mindourou' => [
                'Centre-ville', 'Quartier Administratif',
            ],
        ];
    }

    // ──────────────────────────────────────────────
    //  REGION DU SUD  (Chef-lieu : Ebolowa)
    // ──────────────────────────────────────────────

    /**
     * @return array<string, list<string>>
     */
    private function regionSud(): array
    {
        return [
            // ── Ebolowa ──
            'Ebolowa' => [
                'Nko\'ovos', 'Angalé', 'New-Town', 'Mekalat', 'Nkoétyé',
                'Quartier Administratif', 'Mvam-Essakoe', 'Quartier Haoussa',
                'Nko\'olong', 'Nkoemvone', 'Abang', 'Camp SIC',
            ],

            // ── Kribi ──
            'Kribi' => [
                'Centre-ville', 'Mpangou', 'Ngoye', 'Dombé', 'Talla',
                'Grand Batanga', 'Quartier Administratif', 'Afan Mabé',
                'Londji', 'Ebodjé', 'New Bell', 'Mokolo',
                'Quartier Résidentiel', 'Plage', 'Port de Pêche',
            ],

            // ── Sangmélima ──
            'Sangmélima' => [
                'Centre-ville', 'Nkpwang', 'Bikoula', 'Quartier Administratif',
                'Quartier Haoussa', 'Meyomessala', 'Djoum', 'Zoétélé',
            ],

            // ── Ambam ──
            'Ambam' => [
                'Centre-ville', 'Quartier Administratif', 'Quartier Haoussa',
                'Meyo-Centre', 'Kye-Ossi', 'Ma\'an',
            ],

            // ── Lolodorf ──
            'Lolodorf' => [
                'Centre-ville', 'Quartier Administratif', 'Bipindi',
                'Akom II',
            ],

            // ── Campo ──
            'Campo' => [
                'Centre-ville', 'Quartier Administratif', 'Campo Beach',
            ],

            // ── Djoum ──
            'Djoum' => [
                'Centre-ville', 'Quartier Administratif', 'Mintom',
                'Oveng',
            ],

            // ── Meyomessala ──
            'Meyomessala' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Zoétélé ──
            'Zoétélé' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Mvangan ──
            'Mvangan' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Bengbis ──
            'Bengbis' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Mengong ──
            'Mengong' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Akom II ──
            'Akom II' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Bipindi ──
            'Bipindi' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Kye-Ossi ──
            'Kye-Ossi' => [
                'Centre-ville', 'Quartier Administratif', 'Frontière',
            ],

            // ── Ma\'an ──
            'Ma\'an' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Mintom ──
            'Mintom' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Olamze ──
            'Olamze' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Meyo-Centre ──
            'Meyo-Centre' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Niété ──
            'Niété' => [
                'Centre-ville', 'Quartier Administratif',
            ],

            // ── Mvila ──
            'Efoulan' => [
                'Centre-ville', 'Quartier Administratif',
            ],
        ];
    }
}
