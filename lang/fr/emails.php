<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Email Translations — French (default)
    |--------------------------------------------------------------------------
    */

    // Layout / Footer
    'layout' => [
        'rights' => 'Tous droits réservés.',
        'receiving_reason' => 'Vous recevez cet email car vous êtes inscrit sur :app.',
        'unsubscribe' => 'Se désabonner',
        'manage_preferences' => 'Gérer mes préférences',
    ],

    // Welcome
    'welcome' => [
        'subject' => 'Bienvenue sur :app',
        'preheader' => 'Votre compte est activé — commencez à explorer des milliers d\'annonces immobilières vérifiées.',
        'heading' => 'Bienvenue, :name',
        'intro' => 'Votre compte <strong>:app</strong> est maintenant activé. Vous faites officiellement partie de la communauté !',
        'what_you_can_do' => 'Nous avons conçu cette plateforme pour vous simplifier la vie immobilière. Voici ce que vous pouvez faire dès maintenant :',
        'feature_search' => 'Rechercher intelligemment',
        'feature_search_desc' => 'Filtres avancés, recherche par carte et par quartier.',
        'feature_alerts' => 'Créer des alertes',
        'feature_alerts_desc' => 'Soyez notifié dès qu\'un bien correspond à vos critères.',
        'feature_favorites' => 'Gérer vos favoris',
        'feature_favorites_desc' => 'Sauvegardez les annonces qui vous intéressent.',
        'cta' => 'Faire le tour du propriétaire',
        'help' => 'Si vous avez des questions, notre équipe est disponible à',
    ],

    // Verification Code
    'verification_code' => [
        'subject' => ':code est votre code de vérification :app',
        'heading' => 'Code de vérification',
        'enter_code' => 'Entrez le code de vérification suivant lorsqu\'il vous est demandé :',
        'otp_label' => 'Code à usage unique — ne pas partager',
        'not_requested' => 'Vous n\'avez pas fait cette demande ?',
        'requested_from' => 'Ce code a été demandé depuis <strong>:from</strong> le <strong>:at</strong>. Si vous n\'êtes pas à l\'origine de cette demande, vous pouvez ignorer cet email.',
    ],

    // Forgot Password
    'forgot_password' => [
        'subject' => 'Réinitialisez votre mot de passe :app',
        'heading' => 'Réinitialisation du mot de passe',
        'intro' => 'Nous avons reçu une demande de réinitialisation du mot de passe de votre compte <strong>:app</strong>.',
        'click_below' => 'Cliquez sur le bouton ci-dessous pour choisir un nouveau mot de passe. Ce lien expirera dans <strong>60 minutes</strong>.',
        'cta' => 'Réinitialiser le mot de passe',
        'fallback' => 'Ou copiez et collez ce lien dans votre navigateur :',
        'not_requested' => 'Vous n\'avez pas fait cette demande ?',
        'requested_from' => 'Cette demande a été effectuée depuis <strong>:from</strong> le <strong>:at</strong>. Si vous n\'êtes pas à l\'origine de cette demande, vous pouvez ignorer cet email en toute sécurité.',
    ],

    // Ad Approved
    'ad_approved' => [
        'subject' => 'Votre annonce est publiée',
        'heading' => 'Votre annonce est publiée',
        'greeting' => 'Bonjour <strong>:name</strong>,',
        'intro' => 'Excellente nouvelle — votre annonce a été <strong>validée</strong> par notre équipe de modération et est désormais <strong>visible par tous les utilisateurs</strong> de la plateforme.',
        'status_badge' => 'Publiée et visible',
        'recap_label' => 'Récapitulatif',
        'title_label' => 'Titre',
        'price_label' => 'Prix',
        'cta' => 'Voir mon annonce en ligne',
        'thanks' => 'Merci de votre confiance et bonne publication sur KeyHome.',
    ],

    // Ad Declined
    'ad_declined' => [
        'subject' => 'Votre annonce n\'a pas été validée',
        'heading' => 'Annonce non validée',
    ],

    // Search Alert Match
    'search_alert' => [
        'subject' => 'Nouvelle annonce pour vous — :app',
        'heading' => ':name, une annonce vous attend !',
        'intro' => 'Un bien qui correspond à vos critères vient d\'être publié sur <strong>KeyHome</strong>. Ne laissez pas passer cette opportunité — les meilleures annonces partent vite.',
        'new_badge' => 'Nouveau',
        'per_month' => '/mois',
        'surface' => 'Surface',
        'bedrooms' => 'Chambres',
        'bathrooms' => 'SdB',
        'cta' => 'Voir cette annonce',
    ],

    // Refund Confirmation
    'refund' => [
        'subject' => 'Remboursement confirmé — :amount XAF',
        'heading' => 'Montant remboursé',
        'greeting' => 'Bonjour :name,',
        'intro' => 'Votre demande de remboursement a été traitée avec succès. Voici les détails :',
        'payment_ref' => 'Référence paiement',
        'type' => 'Type',
        'type_partial' => 'Remboursement partiel',
        'type_full' => 'Remboursement intégral',
        'reason' => 'Motif',
        'date' => 'Date',
        'processing_note' => 'Le remboursement sera crédité sur votre moyen de paiement d\'origine dans un délai de 5 à 10 jours ouvrés, selon votre opérateur.',
        'contact' => 'Si vous avez des questions, n\'hésitez pas à nous contacter.',
    ],

    // Subscription Expiring
    'subscription_expiring' => [
        'subject' => 'Rappel : Votre abonnement expire bientôt',
    ],

    // Subscription Renewal Reminder
    'subscription_renewal' => [
        'subject' => 'Renouvellement de votre abonnement — Action requise',
    ],

    // Credit Purchase
    'credit_purchase' => [
        'subject' => 'Achat de crédits confirmé — :name',
    ],

    // New Device Sign-In
    'new_device' => [
        'subject' => 'Connexion depuis un nouvel appareil',
    ],

    // New Location Sign-In
    'new_location' => [
        'subject' => 'Connexion depuis un nouvel emplacement',
    ],

    // Password Changed
    'password_changed' => [
        'subject' => 'Votre mot de passe a été modifié',
    ],

    // Passkey Changed
    'passkey_changed' => [
        'subject' => 'Votre passkey a été modifié',
    ],

    // Email Updated
    'email_updated' => [
        'subject' => 'Votre adresse email a été modifiée',
    ],

    // Generic
    'generic' => [
        'hello' => 'Bonjour',
        'thanks' => 'Merci',
        'support_email' => 'support@keyhome.app',
        'questions' => 'Si vous avez des questions, contactez-nous à',
    ],

    // Welcome Drip Series
    'welcome_drip' => [
        'day1_subject' => 'Astuce #1 — Comment trouver le bien idéal sur :app',
        'day1_heading' => 'Trouvez votre prochain chez-vous',
        'day1_intro' => 'Bonjour :name, bienvenue dans la communauté <strong>:app</strong> ! Voici nos meilleurs conseils pour démarrer.',
        'day1_tip1' => 'Utilisez les filtres avancés',
        'day1_tip1_desc' => 'Prix, quartier, nombre de chambres… affinez votre recherche en quelques clics.',
        'day1_tip2' => 'Activez les alertes',
        'day1_tip2_desc' => 'Recevez un email dès qu\'un nouveau bien correspond à vos critères.',
        'day1_tip3' => 'Explorez la carte',
        'day1_tip3_desc' => 'Visualisez les biens disponibles dans votre quartier préféré.',
        'day1_cta' => 'Explorer les annonces',

        'day3_subject' => 'Astuce #2 — Créez votre première alerte sur :app',
        'day3_heading' => 'Ne ratez plus aucun bien',
        'day3_intro' => ':name, les meilleures annonces partent vite. Créez une alerte pour être prévenu en premier.',
        'day3_cta' => 'Créer une alerte',

        'day7_subject' => 'Comment se passe votre expérience sur :app ?',
        'day7_heading' => 'Votre avis compte',
        'day7_intro' => ':name, cela fait une semaine que vous nous avez rejoints. Avez-vous trouvé ce que vous cherchiez ?',
        'day7_cta' => 'Parcourir les annonces',
    ],

    // Inactivity Warning
    'inactivity' => [
        'subject' => ':name, vous nous manquez sur :app !',
        'heading' => 'De retour parmi nous ?',
        'intro' => 'Cela fait :days jours que vous ne vous êtes pas connecté à <strong>:app</strong>. De nouvelles annonces vous attendent !',
        'stats' => ':count nouvelles annonces publiées depuis votre dernière visite.',
        'cta' => 'Voir les nouveautés',
    ],

    // Failed Payment Retry
    'failed_payment' => [
        'subject' => 'Votre paiement n\'a pas abouti — :app',
        'heading' => 'Paiement non abouti',
        'intro' => 'Bonjour :name, votre paiement de <strong>:amount XAF</strong> pour <strong>:type</strong> n\'a pas pu être traité.',
        'reason' => 'Cela peut être dû à un solde insuffisant, un réseau instable ou une session expirée.',
        'cta' => 'Réessayer le paiement',
        'help' => 'Si le problème persiste, contactez-nous à',
    ],

    // Weekly Digest
    'digest' => [
        'subject' => 'Votre résumé immobilier de la semaine — :app',
        'heading' => 'Votre résumé de la semaine',
        'intro' => 'Bonjour :name, voici ce qui s\'est passé cette semaine sur <strong>:app</strong>.',
        'new_ads' => ':count nouvelles annonces',
        'in_your_city' => 'dans votre ville',
        'matching_alerts' => ':count correspondent à vos alertes',
        'cta' => 'Voir toutes les annonces',
        'no_activity' => 'Aucune nouvelle annonce cette semaine. Créez une alerte pour ne rien manquer !',
    ],

    'subscription_success' => [
        'title' => 'Abonnement activé — :app',
        'heading' => 'Abonnement activé 🎉',
        'badge' => '✓ Paiement confirmé',
        'greeting' => 'Bonjour l\'équipe :agency,',
        'intro' => 'Nous avons le plaisir de vous confirmer l\'activation de votre abonnement. Merci pour votre confiance !',
        'amount_label' => 'Montant payé',
        'details_heading' => ' Détails de l\'abonnement',
        'plan' => 'Plan',
        'period' => 'Période',
        'valid_until' => 'Valide jusqu\'au',
        'benefits' => 'Avantages',
        'benefits_value' => 'Boost + limites augmentées',
        'dashboard_cta' => 'Accéder à mon tableau de bord',
        'closing' => 'Merci d\'avoir choisi :app pour développer votre activité !',
    ],

    'appointment_reminder' => [
        'subject' => 'Rappel : Visite prévue demain — :app',
        'heading' => 'Rappel de visite',
        'intro' => 'Bonjour :name, vous avez une visite prévue demain pour le bien <strong>:property</strong>.',
        'date_label' => 'Date et heure',
        'address_label' => 'Adresse',
        'cta' => 'Voir les détails',
        'cancel_note' => 'Si vous ne pouvez plus y assister, veuillez annuler au moins 2h à l\'avance.',
    ],

    'post_viewing_feedback' => [
        'subject' => 'Qu\'avez-vous pensé de votre visite ? — :app',
        'heading' => 'Votre avis nous intéresse',
        'intro' => 'Bonjour :name, vous avez visité le bien <strong>:property</strong> récemment. Qu\'en avez-vous pensé ?',
        'cta' => 'Donner mon avis',
        'alternative' => 'Vous cherchez encore ? Découvrez d\'autres biens similaires.',
        'browse_cta' => 'Voir les annonces',
    ],

    'abandoned_search' => [
        'subject' => 'Vos recherches vous attendent — :app',
        'heading' => 'Ne passez pas à côté de votre futur bien',
        'intro' => 'Bonjour :name, vous avez récemment cherché des biens sur <strong>:app</strong> mais n\'avez pas finalisé vos recherches.',
        'matching' => ':count annonces correspondent à vos critères.',
        'cta' => 'Reprendre ma recherche',
        'alert_tip' => 'Créez une alerte pour être notifié dès qu\'un bien correspondant à vos critères est publié.',
    ],
];
