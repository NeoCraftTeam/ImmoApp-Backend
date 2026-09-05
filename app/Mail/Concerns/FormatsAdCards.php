<?php

declare(strict_types=1);

namespace App\Mail\Concerns;

use App\Models\Ad;
use Illuminate\Support\Collection;

/**
 * Flattens ads into the plain arrays `emails.partials.ad-card` expects.
 *
 * Two reasons this is not done in the Blade view:
 *
 * 1. A mailable is queued, so its properties are serialized. Keeping `Ad`
 *    models around means the job wakes up, re-hydrates them, and then touches
 *    `quarter.city` and the media library per card — one N+1 per email sent.
 *    Flattening in the constructor resolves everything once, while the request
 *    that dispatched the job still has the relations warm.
 * 2. A deleted or unpublished ad would explode inside the view, and a view is
 *    the worst place to discover that. Here it just drops out of the list.
 *
 * The image is deliberately the ORIGINAL upload rather than the `thumb`
 * conversion: every conversion registered on {@see Ad} is WebP, which Outlook
 * desktop and several mobile clients still refuse to render. `ad-card` carries
 * `width="552"` so the browser scales the full-size file in place.
 */
trait FormatsAdCards
{
    /**
     * @param  iterable<int, Ad>  $ads
     * @return array<int, array{title: string, price: string, location: string, url: string, image: string|null}>
     */
    protected function formatAdCards(iterable $ads, int $limit = 3): array
    {
        return Collection::make($ads)
            ->take($limit)
            ->map(fn (Ad $ad): array => $this->formatAdCard($ad))
            ->values()
            ->all();
    }

    /**
     * @return array{title: string, price: string, location: string, url: string, image: string|null}
     */
    protected function formatAdCard(Ad $ad): array
    {
        $image = $ad->getFirstMediaUrl('images');

        return [
            'title' => (string) $ad->title,
            'price' => $this->formatAdPrice($ad),
            'location' => $this->formatAdLocation($ad),
            'url' => $this->adUrl($ad),
            'image' => $image !== '' ? $image : null,
        ];
    }

    private function formatAdPrice(Ad $ad): string
    {
        $price = (float) ($ad->price ?? 0);

        if ($price <= 0.0) {
            return __('emails.components.price_on_request');
        }

        return number_format($price, 0, ',', ' ').' FCFA';
    }

    private function formatAdLocation(Ad $ad): string
    {
        $parts = array_filter([
            $ad->quarter?->name,
            $ad->quarter?->city?->name,
        ]);

        return implode(', ', $parts);
    }

    private function adUrl(Ad $ad): string
    {
        $base = rtrim((string) config('app.frontend_url', config('app.url')), '/');
        $slug = (string) ($ad->slug ?? '');

        return $slug !== ''
            ? $base.'/ads/'.rawurlencode($slug)
            : $base.'/ads/'.rawurlencode((string) $ad->id);
    }
}
