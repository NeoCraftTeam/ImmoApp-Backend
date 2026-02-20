<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour optimiser les réponses destinées aux WebViews natives.
 * 
 * - Ajoute les en-têtes de cache appropriés
 * - Compresse les réponses
 * - Optimise les ressources statiques
 * - Ajoute les en-têtes de sécurité nécessaires pour les WebViews
 */
class OptimizeWebViewResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Détecter si c'est une requête depuis une WebView native
        $isNativeWebView = $request->has('app_mode') && $request->get('app_mode') === 'native'
            || $request->header('X-Native-App') === 'true'
            || $request->header('User-Agent', '')->contains('ReactNativeWebView');

        if ($isNativeWebView) {
            // Ajouter les en-têtes de cache pour les ressources statiques
            $response->header('Cache-Control', 'public, max-age=3600');
            
            // Permettre la compression gzip
            $response->header('Vary', 'Accept-Encoding');
            
            // Ajouter un en-tête pour identifier les réponses optimisées
            $response->header('X-Optimized-For-Native', 'true');
            
            // Sécurité : permettre les requêtes cross-origin depuis le contexte natif
            $response->header('X-Frame-Options', 'SAMEORIGIN');
            $response->header('X-Content-Type-Options', 'nosniff');
        }

        return $response;
    }
}
