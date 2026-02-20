<?php

declare(strict_types=1);

namespace App\Services;

use Illuminate\Support\Facades\Log;

/**
 * Service pour gérer la communication entre le backend Laravel et l'application native.
 *
 * Permet de :
 * - Envoyer des notifications push
 * - Déclencher des actions natives (géolocalisation, caméra, etc.)
 * - Gérer les événements de l'application native
 */
class NativeAppService
{
    /**
     * Envoyer un message à l'application native via WebView.
     */
    public static function sendToNative(string $type, array $data = []): array
    {
        return [
            'type' => $type,
            'data' => $data,
            'timestamp' => now()->toIso8601String(),
        ];
    }

    /**
     * Demander la géolocalisation à l'application native.
     */
    public static function requestGeolocation(): array
    {
        return self::sendToNative('REQUEST_GEOLOCATION', [
            'timeout' => 30000,
            'enableHighAccuracy' => true,
        ]);
    }

    /**
     * Demander l'accès à la caméra pour les photos.
     */
    public static function requestCamera(): array
    {
        return self::sendToNative('REQUEST_CAMERA', [
            'allowEditing' => true,
            'mediaType' => 'photo',
        ]);
    }

    /**
     * Demander l'accès à la galerie.
     */
    public static function requestGallery(): array
    {
        return self::sendToNative('REQUEST_GALLERY', [
            'allowsMultiple' => true,
            'mediaType' => 'photo',
        ]);
    }

    /**
     * Notifier l'application native que la page a changé.
     */
    public static function notifyPageChange(string $url): array
    {
        return self::sendToNative('PAGE_CHANGED', [
            'url' => $url,
        ]);
    }

    /**
     * Demander à l'application native d'ouvrir un lien externe.
     */
    public static function openExternalLink(string $url): array
    {
        return self::sendToNative('OPEN_EXTERNAL_LINK', [
            'url' => $url,
        ]);
    }

    /**
     * Envoyer une notification de succès.
     */
    public static function showNotification(string $title, string $message, string $type = 'success'): array
    {
        return self::sendToNative('SHOW_NOTIFICATION', [
            'title' => $title,
            'message' => $message,
            'type' => $type,
        ]);
    }

    /**
     * Vérifier si la requête provient d'une application native.
     */
    public static function isNativeApp(): bool
    {
        return request()->has('app_mode') && request()->get('app_mode') === 'native'
            || request()->header('X-Native-App') === 'true'
            || str_contains(request()->header('User-Agent', ''), 'ReactNativeWebView');
    }

    /**
     * Logger une action native pour le débogage.
     */
    public static function logNativeAction(string $action, array $context = []): void
    {
        Log::channel('native')->info('Native action: '.$action, $context);
    }
}
