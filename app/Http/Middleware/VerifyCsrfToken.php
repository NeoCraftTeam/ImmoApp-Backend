<?php

namespace App\Http\Middleware;

use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken as Middleware;

class VerifyCsrfToken extends Middleware
{
    /**
     * Les URLs qui doivent être exclues de la vérification CSRF.
     *
     * @var array<int, string>
     */
    protected $except = [
        // Exclure toutes les routes API
        'api/*',

        // Exclure des routes spécifiques
        '/auth/login',
        '/auth/logout',
        '/auth/registerCustomer',
        '/auth/registerAgent',

        // Ou exclure complètement toutes les routes (équivalent à désactiver CSRF)
        '*',
    ];
}
