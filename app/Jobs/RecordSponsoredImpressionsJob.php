<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Ad;
use App\Models\SponsoredImpression;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Persist sponsored-feed impression telemetry off the request thread.
 *
 * Previously called inline from AdController::feed() — every feed render
 * fired one UPDATE on `ad` (the hottest table) + one INSERT into
 * `sponsored_impressions`. Under peak feed traffic that meant 2×N write
 * QPS contending with publishes on the read path. Moving the work into
 * a dedicated queue lets the request return as soon as the page is
 * serialised; analytics catch up asynchronously.
 *
 * Payload is a flat list of `[ad_id, tier, slot]` tuples so the queue
 * worker doesn't have to deserialise full Eloquent models.
 *
 * @phpstan-type ImpressionRow array{ad_id: string, tier: string, slot: int}
 */
final class RecordSponsoredImpressionsJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 30;

    /**
     * @param  list<ImpressionRow>  $rows
     */
    public function __construct(
        public readonly array $rows,
        public readonly ?string $userId,
    ) {}

    public function handle(): void
    {
        if ($this->rows === []) {
            return;
        }

        $now = now();
        $ids = array_values(array_unique(array_column($this->rows, 'ad_id')));

        Ad::query()->whereIn('id', $ids)->update([
            'last_shown_at' => $now,
            'impression_count' => DB::raw('impression_count + 1'),
        ]);

        $impressions = array_map(fn (array $row): array => [
            'id' => (string) Str::uuid(),
            'ad_id' => $row['ad_id'],
            'user_id' => $this->userId,
            'tier' => $row['tier'],
            'slot' => $row['slot'],
            'shown_at' => $now,
        ], $this->rows);

        SponsoredImpression::query()->insert($impressions);
    }
}
