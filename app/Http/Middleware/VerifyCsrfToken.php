<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class VerifyCsrfToken
{
 protected $except = [
        // Exclure vos routes d'auth du CSRF
        'api/v1/auth/login',
        'api/v1/auth/registerCustomer',
        'api/v1/auth/registerAgent',
        'api/v1/auth/logout',
        
        // Ou utiliser un wildcard
        // 'api/v1/auth/*',
    ];

}
