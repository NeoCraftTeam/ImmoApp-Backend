<?php

declare(strict_types=1);

namespace App\Support;

/** Catalogue résidentiel pour studios, appartements et maisons. */
final class PropertyAttributeCatalog
{
    /** @return list<array{name:string,icon:string,admin_icon:string,attributes:list<array{name:string,icon:string}>}> */
    public static function categories(): array
    {
        return [
            self::category('Agencement intérieur', 'Home', 'heroicon-o-home-modern', [
                ['Séjour séparé', 'Weekend'], ['Salle à manger', 'TableRestaurant'],
                ['Cuisine séparée', 'Kitchen'], ['Cuisine ouverte', 'Countertops'],
                ['Salle de bain privative', 'Bathtub'], ['Toilettes séparées', 'Wc'],
                ['Dressing', 'Checkroom'], ['Placards intégrés', 'Inventory2'],
                ['Cellier', 'Shelves'], ['Buanderie', 'LocalLaundryService'],
                ['Bureau / espace de travail', 'Desk'], ['Entrée indépendante', 'MeetingRoom'],
            ]),
            self::category('Cuisine et électroménager', 'Kitchen', 'heroicon-o-cake', [
                ['Cuisine équipée', 'Kitchen'], ['Plaques de cuisson', 'Whatshot'],
                ['Four', 'Microwave'], ['Micro-ondes', 'Microwave'],
                ['Réfrigérateur', 'Kitchen'], ['Congélateur', 'AcUnit'],
                ['Hotte aspirante', 'Air'], ['Lave-vaisselle', 'LocalDining'],
                ['Lave-linge', 'LocalLaundryService'], ['Sèche-linge', 'DryCleaning'],
            ]),
            self::category('Confort et connectivité', 'Thermostat', 'heroicon-o-wifi', [
                ['Climatisation', 'AcUnit'], ['Ventilateur plafond', 'ModeFanOff'],
                ['Chauffage', 'Whatshot'], ['Chauffe-eau', 'WaterHeater'],
                ['Eau chaude', 'HotTub'], ['WiFi', 'Wifi'],
                ['Fibre optique disponible', 'Router'], ['Prise TV', 'Tv'],
                ['Logement meublé', 'Chair'], ['Double vitrage', 'Window'],
                ['Bonne ventilation naturelle', 'Air'],
            ]),
            self::category('Eau, énergie et assainissement', 'ElectricalServices', 'heroicon-o-bolt', [
                ['Eau courante permanente', 'Water'], ['Réservoir / château d’eau', 'Water'],
                ['Forage / puits', 'Terrain'], ['Compteur d’eau individuel', 'WaterDrop'],
                ['Compteur électrique individuel', 'ElectricMeter'], ['Groupe électrogène', 'ElectricalServices'],
                ['Onduleur / batterie de secours', 'BatteryChargingFull'], ['Panneaux solaires', 'SolarPower'],
                ['Tout-à-l’égout', 'Plumbing'], ['Fosse septique', 'Plumbing'],
            ]),
            self::category('Sécurité', 'Security', 'heroicon-o-shield-check', [
                ['Résidence clôturée', 'Fence'], ['Portail sécurisé', 'DoorFront'],
                ['Gardien', 'SupportAgent'], ['Vidéosurveillance', 'Videocam'],
                ['Interphone / visiophone', 'VideoCall'], ['Digicode / badge', 'Pin'],
                ['Alarme', 'NotificationImportant'], ['Détecteur de fumée', 'Sensors'],
                ['Extincteur', 'LocalFireDepartment'], ['Éclairage extérieur', 'LightMode'],
            ]),
            self::category('Accès et stationnement', 'DirectionsCar', 'heroicon-o-truck', [
                ['Parking privé', 'LocalParking'], ['Parking couvert', 'Garage'],
                ['Garage fermé', 'Garage'], ['Parking visiteurs', 'LocalParking'],
                ['Accès voiture', 'AddRoad'], ['Route goudronnée', 'AddRoad'],
                ['Ascenseur', 'Elevator'], ['Accès sans marche', 'Accessible'],
                ['Accès PMR', 'AccessibleForward'], ['Borne de recharge électrique', 'EvStation'],
            ]),
            self::category('Extérieur et dépendances', 'Deck', 'heroicon-o-sun', [
                ['Balcon', 'Balcony'], ['Terrasse', 'Deck'], ['Cour privative', 'Yard'],
                ['Jardin privatif', 'Yard'], ['Jardin commun', 'Park'],
                ['Piscine privée', 'Pool'], ['Piscine commune', 'Pool'],
                ['Dépendance', 'OtherHouses'], ['Local de rangement', 'Inventory'],
                ['Vue dégagée', 'Landscape'], ['Vue mer', 'Water'],
            ]),
            self::category('Conditions d’occupation', 'Gavel', 'heroicon-o-clipboard-document-list', [
                ['Animaux acceptés', 'Pets'], ['Colocation acceptée', 'Group'],
                ['Étudiants acceptés', 'School'], ['Fumeurs acceptés', 'SmokingRooms'],
                ['Usage professionnel autorisé', 'BusinessCenter'],
            ]),
        ];
    }

    /**
     * @param  list<array{0:string,1:string}>  $attributes
     * @return array{name:string,icon:string,admin_icon:string,attributes:list<array{name:string,icon:string}>}
     */
    private static function category(string $name, string $icon, string $adminIcon, array $attributes): array
    {
        return [
            'name' => $name,
            'icon' => $icon,
            'admin_icon' => $adminIcon,
            'attributes' => array_map(
                static fn (array $attribute): array => ['name' => $attribute[0], 'icon' => $attribute[1]],
                $attributes,
            ),
        ];
    }
}
