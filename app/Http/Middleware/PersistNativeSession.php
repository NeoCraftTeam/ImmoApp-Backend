<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Middleware pour persister les sessions dans les WebViews React Native.
 *
 * Garantit que :
 * - Les cookies de session sont correctement stockés et transmis
 * - Les tokens d'authentification sont persistés
 * - Les données de session survivent aux rechargements de page
 */
class PersistNativeSession
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Détecter si c'est une requête depuis une WebView native
        $isNativeWebView = $this->isNativeWebView($request);

        if ($isNativeWebView) {
            // Ajouter les en-têtes pour permettre la persistance des cookies
            $response->header('Set-Cookie', $this->buildSessionCookie($request), false);

            // Permettre à la WebView de stocker les données
            $response->header('Access-Control-Allow-Credentials', 'true');
            $response->header('Access-Control-Allow-Origin', '*');

            // Empêcher la mise en cache des pages authentifiées
            $response->header('Cache-Control', 'no-cache, no-store, must-revalidate');
            $response->header('Pragma', 'no-cache');
            $response->header('Expires', '0');
        }

        return $response;
    }

    /**
     * Vérifier si la requête provient d'une WebView native.
     */
    private function isNativeWebView(Request $request): bool
    {
        return $request->has('app_mode') && $request->get('app_mode') === 'native'
            || $request->header('X-Native-App') === 'true'
            || str_contains($request->header('User-Agent', ''), 'ReactNativeWebView');
    }

    /**
     * Construire un cookie de session persistant pour WebView.
     */
    private function buildSessionCookie(Request $request): string
    {
        $sessionName = config('session.cookie');
        $sessionId = $request->session()->getId();
        $lifetime = config('session.lifetime') * 60; // Convertir en secondes

        return sprintf(
            '%s=%s; Path=/; HttpOnly; SameSite=Lax; Max-Age=%d',
            $sessionName,
            $sessionId,
            $lifetime
        );
    }
}
