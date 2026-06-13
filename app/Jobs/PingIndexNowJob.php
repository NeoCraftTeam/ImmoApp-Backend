<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Services\IndexNowService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * Pings the IndexNow API in the background.
 *
 * Was previously fired synchronously inside `AdObserver::created` /
 * `updated` / `deleted`. A slow IndexNow response (the call has a 5 s
 * timeout) stalled every owner publish/edit AND every admin bulk approve
 * (the bulk action loops `forceFill->save()` per record — N sequential
 * 5 s blocking calls). Moving the ping off the request thread keeps p99
 * publish latency in the ms range and lets bulk approvals scale.
 */
final class PingIndexNowJob implements ShouldQueue
{
    use Dispatchable;
    use InteractsWithQueue;
    use Queueable;
    use SerializesModels;

    public int $tries = 2;

    public int $timeout = 15;

    public function __construct(public readonly string $url) {}

    public function handle(IndexNowService $indexNow): void
    {
        $indexNow->ping($this->url);
    }
}
