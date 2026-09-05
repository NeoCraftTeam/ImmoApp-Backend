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

    // Shared partials (ad-card, ad-list, stat, divider)
    'components' => [
        'see_listing' => 'Voir l\'annonce',
        'price_on_request' => 'Prix sur demande',
    ],

    // Welcome (client)
    'welcome' => [
        'subject' => 'Votre compte :app est activé — explorez dès maintenant',
        'preheader' => 'Annonces vérifiées, alertes personnalisées et recherche intelligente — tout est prêt pour vous.',
        'heading' => 'Bienvenue, :name',
        'intro' => 'Votre compte <strong>:app</strong> est activé. Rejoignez des milliers d\'utilisateurs qui trouvent leur prochain bien en toute confiance.',
        'what_you_can_do' => 'Voici ce que vous pouvez faire dès maintenant :',
        'feature_search' => 'Recherche avancée',
        'feature_search_desc' => 'Filtres précis, carte interactive et biens vérifiés dans votre quartier.',
        'feature_alerts' => 'Alertes personnalisées',
        'feature_alerts_desc' => 'Soyez notifié en premier dès qu\'un bien correspond à vos critères.',
        'feature_favorites' => 'Liste de favoris',
        'feature_favorites_desc' => 'Sauvegardez et comparez facilement les annonces qui vous intéressent.',
        'cta' => 'Explorer les annonces',
        'help' => 'Une question ? Notre équipe est disponible à',
    ],

    // Verification Code
    'verification_code' => [
        'subject' => ':code — votre code de vérification :app',
        'heading' => 'Confirmez votre identité',
        'enter_code' => 'Saisissez ce code lorsqu\'il vous est demandé. Il expire dans 10 minutes.',
        'otp_label' => 'Code à usage unique — confidentiel',
        'not_requested' => 'Vous n\'avez pas fait cette demande ?',
        'requested_from' => 'Ce code a été demandé depuis <strong>:from</strong> le <strong>:at</strong>. Si vous n\'en êtes pas à l\'origine, ignorez cet email — aucune action n\'est requise.',
    ],

    // Forgot Password
    'forgot_password' => [
        'subject' => 'Réinitialisation du mot de passe — lien valide 60 min',
        'heading' => 'Choisissez un nouveau mot de passe',
        'intro' => 'Nous avons reçu une demande de réinitialisation pour votre compte <strong>:app</strong>. Cliquez ci-dessous pour définir un nouveau mot de passe.',
        'click_below' => 'Ce lien expire dans <strong>60 minutes</strong>. Passé ce délai, soumettez une nouvelle demande.',
        'cta' => 'Définir mon nouveau mot de passe',
        'fallback' => 'Le bouton ne fonctionne pas ? Copiez ce lien dans votre navigateur :',
        'not_requested' => 'Vous n\'avez pas fait cette demande ?',
        'requested_from' => 'Cette demande provient de <strong>:from</strong> le <strong>:at</strong>. Si vous n\'en êtes pas à l\'origine, ignorez cet email — votre mot de passe reste inchangé.',
    ],

    // Ad Approved
    'ad_approved' => [
        'subject' => '✓ Votre annonce est maintenant en ligne',
        'heading' => 'Annonce publiée avec succès',
        'greeting' => 'Bonjour <strong>:name</strong>,',
        'intro' => 'Votre annonce a été <strong>validée par notre équipe</strong> et est désormais visible par des milliers de visiteurs sur la plateforme.',
        'status_badge' => 'En ligne · Visible',
        'recap_label' => 'Récapitulatif',
        'title_label' => 'Titre',
        'price_label' => 'Prix',
        'cta' => 'Voir mon annonce',
        'thanks' => 'Merci de faire confiance à KeyHome pour votre présence immobilière.',
    ],

    // Ad Declined
    'ad_declined' => [
        'subject' => 'Action requise — votre annonce nécessite des modifications',
        'heading' => 'Modifications requises',
        'greeting' => 'Bonjour <strong>:name</strong>,',
        'intro' => 'Votre annonce <strong>« :title »</strong> n\'a pas pu être publiée en l\'état. Notre équipe de modération a relevé des points à corriger.',
        'reason_label' => 'Motif',
        'instructions' => 'Apportez les corrections mentionnées, puis resouméttez votre annonce. Notre équipe la revalidera dans les meilleurs délais.',
        'cta' => 'Modifier mon annonce',
        'support_note' => 'Des questions sur ce refus ? Répondez directement à cet email.',
    ],

    // Search Alert Match
    'search_alert' => [
        'subject' => 'Un bien correspond à votre alerte — :app',
        'heading' => ':name, un bien vous attend',
        'intro' => 'Une annonce correspondant à vos critères vient d\'être publiée sur <strong>KeyHome</strong>. Les meilleures offres se louent vite — consultez-la dès maintenant.',
        'new_badge' => 'Nouveau',
        'per_month' => '/mois',
        'surface' => 'Surface',
        'bedrooms' => 'Chambres',
        'bathrooms' => 'SdB',
        'cta' => 'Voir cette annonce',
    ],

    // Refund Confirmation
    'refund' => [
        'subject' => 'Remboursement de :amount XAF confirmé',
        'heading' => 'Remboursement traité',
        'greeting' => 'Bonjour :name,',
        'intro' => 'Votre remboursement a été traité avec succès. Voici le récapitulatif :',
        'payment_ref' => 'Référence',
        'type' => 'Type',
        'type_partial' => 'Remboursement partiel',
        'type_full' => 'Remboursement intégral',
        'reason' => 'Motif',
        'date' => 'Date',
        'processing_note' => 'Le montant sera crédité sur votre moyen de paiement d\'origine sous 5 à 10 jours ouvrés, selon votre opérateur.',
        'contact' => 'Un problème ? Contactez-nous en répondant directement à cet email.',
    ],

    // Subscription Expiring
    'subscription_expiring' => [
        'subject' => 'Votre abonnement :app expire bientôt — agissez maintenant',
    ],

    // Subscription Renewal Reminder
    'subscription_renewal' => [
        'subject' => 'Action requise — renouvelez votre abonnement :app',
    ],

    // Credit Purchase
    'credit_purchase' => [
        'subject' => 'Crédits confirmés — :name ajouté à votre compte',
    ],

    // New Device Sign-In
    'new_device' => [
        'subject' => 'Connexion depuis un nouvel appareil — :app',
    ],

    // New Location Sign-In
    'new_location' => [
        'subject' => 'Connexion depuis un nouvel emplacement — vérifiez si c\'est vous',
    ],

    // Password Changed
    'password_changed' => [
        'subject' => 'Confirmation — votre mot de passe :app a été modifié',
    ],

    // Passkey Changed
    'passkey_changed' => [
        'subject' => 'Sécurité — votre passkey :app a été mise à jour',
    ],

    // Email Updated
    'email_updated' => [
        'subject' => 'Adresse email mise à jour — vérification requise',
    ],

    // Generic
    'generic' => [
        'hello' => 'Bonjour',
        'thanks' => 'Merci',
        'support_email' => 'support@keyhome.app',
        'questions' => 'Une question ? Écrivez-nous à',
    ],

    // Welcome Drip Series
    'welcome_drip' => [
        'day1_subject' => 'Démarrez sur :app — 3 fonctionnalités à activer dès aujourd\'hui',
        'day1_heading' => 'Commencez du bon pied',
        'day1_intro' => 'Bonjour :name, bienvenue dans la communauté <strong>:app</strong>. Voici trois fonctionnalités clés pour maximiser vos chances de trouver le bien idéal.',
        'day1_tip1' => 'Filtres avancés',
        'day1_tip1_desc' => 'Prix, quartier, surface, nombre de pièces — affinez votre recherche avec précision.',
        'day1_tip2' => 'Alertes automatiques',
        'day1_tip2_desc' => 'Soyez notifié en temps réel dès qu\'un bien correspond à vos critères.',
        'day1_tip3' => 'Carte interactive',
        'day1_tip3_desc' => 'Visualisez l\'emplacement exact des biens et explorez les quartiers.',
        'day1_cta' => 'Explorer les annonces',

        'day3_subject' => 'Ne ratez aucun bien — activez votre première alerte :app',
        'day3_heading' => 'Votre première alerte en 30 secondes',
        'day3_intro' => ':name, les meilleures annonces partent en quelques heures. Créez une alerte et soyez notifié avant tout le monde.',
        'day3_cta' => 'Créer mon alerte',

        'day7_subject' => ':name, avez-vous trouvé votre prochain bien sur :app ?',
        'day7_heading' => 'Votre recherche avance-t-elle ?',
        'day7_intro' => ':name, une semaine s\'est écoulée depuis votre inscription. Si vous n\'avez pas encore trouvé le bien idéal, nos outils de recherche peuvent vous aider à affiner vos critères.',
        'day7_cta' => 'Reprendre ma recherche',
    ],

    // Inactivity Warning
    'inactivity' => [
        'subject' => ':name, de nouvelles annonces vous attendent sur :app',
        'subject_early' => ':name, :count biens publiés depuis votre dernière visite — :app',
        'subject_winback' => ':name, votre prochain chez-vous vous attend toujours sur :app',
        'heading' => 'Du nouveau depuis votre dernière visite',
        'intro' => ':name, <strong>:count nouvelles annonces</strong> correspondant à votre profil ont été publiées depuis votre dernière connexion sur <strong>:app</strong>.',
        'stats' => ':count annonces publiées depuis votre dernière visite.',
        'cta' => 'Voir les nouveautés',
    ],

    // Failed Payment Retry
    'failed_payment' => [
        'subject' => 'Paiement non abouti — réglez la situation pour continuer',
        'heading' => 'Votre paiement n\'a pas abouti',
        'intro' => 'Bonjour :name, votre paiement de <strong>:amount XAF</strong> pour <strong>:type</strong> a échoué. Aucun montant n\'a été débité.',
        'reason' => 'Cela peut être dû à un solde insuffisant, une interruption réseau ou une session expirée.',
        'cta' => 'Régulariser le paiement',
        'help' => 'Le problème persiste ? Contactez notre support à',
    ],

    // Weekly Digest
    'digest' => [
        'subject' => 'Votre résumé immobilier de la semaine — :app',
        'heading' => 'Votre résumé de la semaine',
        'intro' => 'Bonjour :name, voici ce qui s\'est passé cette semaine sur <strong>:app</strong>.',
        'new_ads' => ':count nouvelles annonces',
        'in_your_city' => 'dans votre zone',
        'matching_alerts' => 'dont :count correspondent à vos alertes',
        'cta' => 'Voir toutes les annonces',
        'no_activity' => 'Aucune nouvelle annonce cette semaine. Modifiez vos critères pour élargir votre recherche.',
    ],

    // Subscription Success (Agency / Owner)
    'subscription_success' => [
        'title' => 'Abonnement activé — :app',
        'heading' => 'Abonnement activé avec succès',
        'badge' => '✓ Paiement confirmé',
        'greeting' => 'Bonjour l\'équipe :agency,',
        'intro' => 'Nous confirmons l\'activation de votre abonnement <strong>:plan</strong>. Votre accès aux fonctionnalités premium est effectif immédiatement.',
        'amount_label' => 'Montant réglé',
        'details_heading' => 'Détails de l\'abonnement',
        'plan' => 'Formule',
        'period' => 'Période',
        'valid_until' => 'Valide jusqu\'au',
        'benefits' => 'Avantages inclus',
        'benefits_value' => 'Boost d\'annonces · Limites élargies · Support prioritaire',
        'dashboard_cta' => 'Accéder à mon tableau de bord',
        'closing' => 'Merci de faire confiance à :app pour développer votre activité immobilière.',
    ],

    // Appointment Reminder
    'appointment_reminder' => [
        'subject' => 'Rappel — visite prévue demain sur :app',
        'heading' => 'Rappel de visite',
        'intro' => 'Bonjour :name, vous avez une visite prévue demain pour le bien <strong>:property</strong>. Retrouvez les informations pratiques ci-dessous.',
        'date_label' => 'Date et heure',
        'address_label' => 'Adresse',
        'cta' => 'Voir les détails',
        'cancel_note' => 'Vous ne pouvez plus y assister ? Annulez au moins 2h à l\'avance afin de libérer le créneau.',
    ],

    // Post-Viewing Feedback
    'post_viewing_feedback' => [
        'subject' => 'Votre avis sur la visite — :app',
        'heading' => 'Comment s\'est passée votre visite ?',
        'intro' => 'Bonjour :name, vous avez visité le bien <strong>:property</strong>. Votre retour d\'expérience nous aide à améliorer la qualité des annonces.',
        'cta' => 'Laisser mon avis',
        'alternative' => 'Ce bien ne vous correspond pas ? Découvrez des annonces similaires.',
        'browse_cta' => 'Voir les annonces',
    ],

    // Abandoned Search — client warm-lead win-back, 48h after an interaction
    'abandoned_search' => [
        'subject' => 'Avez-vous trouvé le bien que vous cherchiez ?',
        'preheader' => 'Votre recherche est restée exactement où vous l\'avez laissée.',
        'hero_eyebrow' => 'Votre recherche',
        'hero_heading' => 'Alors, vous avez trouvé ?',
        'hero_sub' => 'Il y a deux jours vous cherchiez un bien. Nous avons gardé vos repères.',
        'heading' => 'Bonjour :name, avez-vous trouvé votre bien ?',
        'intro' => 'Vous avez consulté des biens sur <strong>:app</strong> il y a quelques jours sans revenir depuis. Si vous avez signé, félicitations — vous pouvez ignorer cet email. Sinon, votre recherche vous attend, intacte.',
        'seen_title' => 'Les biens que vous avez regardés',
        'seen_note' => 'Encore disponibles à l\'heure où nous vous écrivons.',
        'stat_label' => 'nouvelles annonces publiées depuis votre passage',
        'stat_label_one' => 'nouvelle annonce publiée depuis votre passage',
        'matching' => ':count annonces disponibles pour vos critères actuels.',
        'cta' => 'Reprendre ma recherche',
        'alert_tip' => 'Activez une alerte pour être notifié dès qu\'un nouveau bien correspondant est publié — même lorsque vous n\'êtes pas connecté.',
        'found_it' => 'Vous avez déjà trouvé ? Mettez vos alertes en pause depuis vos préférences : nous cesserons de vous écrire à ce sujet.',
    ],

    // Owner Activity — landlord counterpart, 48h after activity on their listings
    'owner_activity' => [
        'subject' => 'Vos annonces ont été consultées :views fois pendant votre absence',
        'subject_one' => 'Votre annonce a été consultée pendant votre absence',
        'preheader' => 'Des locataires regardent vos biens en ce moment — voici ce qui vous attend.',
        'hero_eyebrow' => 'Pendant votre absence',
        'hero_heading' => 'Ça bouge sur vos annonces',
        'hero_sub' => 'Des locataires ont regardé vos biens depuis votre dernière connexion.',
        'heading' => 'Bonjour :name, il se passe quelque chose sur vos annonces',
        'intro' => 'Depuis votre dernière visite sur <strong>:app</strong>, des locataires ont consulté vos biens. Les bailleurs qui répondent dans les 24 heures concluent nettement plus souvent.',
        'stat_label' => 'vues sur vos annonces depuis votre dernière connexion',
        'stat_label_one' => 'vue sur vos annonces depuis votre dernière connexion',
        'favorites' => ':count personne(s) ont ajouté un de vos biens à leurs favoris.',
        'messages_waiting' => 'Vous avez :count message(s) de locataires en attente de réponse.',
        'top_title' => 'Vos annonces les plus regardées',
        'cta' => 'Ouvrir mon espace bailleur',
        'tip' => 'Un conseil : ajoutez vos créneaux de visite disponibles. Les annonces avec des créneaux ouverts reçoivent davantage de demandes concrètes.',
    ],
];
