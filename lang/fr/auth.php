<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Authentication Language Lines
    |--------------------------------------------------------------------------
    */

    'failed' => 'Ces identifiants ne correspondent pas à nos enregistrements.',
    'password' => 'Le mot de passe fourni est incorrect.',
    'throttle' => 'Trop de tentatives de connexion. Veuillez réessayer dans :seconds secondes.',

    // Message d'inscription GÉNÉRIQUE (OWASP Authentication Cheat Sheet —
    // « Account creation ») : ne confirme jamais qu'un compte existe déjà
    // ni sa méthode de connexion (Google/SSO/mot de passe), pour empêcher
    // l'énumération de comptes et la divulgation du fournisseur.
    'registration_generic_conflict' => 'Impossible de finaliser l\'inscription avec ces informations. Si vous avez déjà un compte, connectez-vous ou utilisez « Mot de passe oublié ».',

];
