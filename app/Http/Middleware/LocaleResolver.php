<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

/**
 * Resolves the request locale from (in priority order):
 *   1. `?lang=` query param (preview / debug)
 *   2. `X-Lang` header (PWA explicit choice)
 *   3. authenticated `users.locale` column
 *   4. `Accept-Language` header negotiation
 *   5. `config('locale.default')`
 *
 * Sets `App::setLocale()` for the request lifetime so all translation
 * calls (`__()`, validation messages, mail) pick up the correct locale.
 */
final class LocaleResolver
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var array<int, string> $supported */
        $supported = (array) config('locale.supported', ['fr']);
        $default = (string) config('locale.default', 'fr');

        $locale = $this->normalize($request->query('lang'), $supported)
            ?? $this->normalize($request->header('X-Lang'), $supported)
            ?? $this->fromAuthenticatedUser($request, $supported)
            ?? $this->negotiateAcceptLanguage($request->header('Accept-Language', ''), $supported)
            ?? $default;

        App::setLocale($locale);

        return $next($request);
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function normalize(?string $candidate, array $supported): ?string
    {
        if ($candidate === null) {
            return null;
        }

        $short = strtolower(substr(trim($candidate), 0, 2));

        return in_array($short, $supported, true) ? $short : null;
    }

    /**
     * @param  array<int, string>  $supported
     */
    private function fromAuthenticatedUser(Request $request, array $supported): ?string
    {
        $user = $request->user();

        if (!$user instanceof User) {
            return null;
        }

        return $this->normalize($user->locale ?? null, $supported);
    }

    /**
     * Tiny RFC-7231 Accept-Language negotiator (no Symfony AcceptHeader to keep
     * this self-contained and trivially testable).
     *
     * @param  array<int, string>  $supported
     */
    private function negotiateAcceptLanguage(string $header, array $supported): ?string
    {
        if ($header === '') {
            return null;
        }

        $entries = [];
        foreach (explode(',', $header) as $segment) {
            $parts = array_map(trim(...), explode(';', $segment));
            $tag = array_shift($parts);
            if ($tag === '') {
                continue;
            }
            $q = 1.0;
            foreach ($parts as $param) {
                if (str_starts_with($param, 'q=')) {
                    $q = (float) substr($param, 2);
                    break;
                }
            }
            $entries[] = [$tag, $q];
        }

        usort($entries, static fn ($a, $b) => $b[1] <=> $a[1]);

        foreach ($entries as [$tag]) {
            $short = strtolower(substr($tag, 0, 2));
            if (in_array($short, $supported, true)) {
                return $short;
            }
        }

        return null;
    }
}
