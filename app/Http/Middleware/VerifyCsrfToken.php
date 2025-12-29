<?php

declare(strict_types=1);

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

    ];
}
