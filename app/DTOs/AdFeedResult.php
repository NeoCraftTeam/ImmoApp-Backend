<?php

declare(strict_types=1);

namespace App\DTOs;

use App\Models\Ad;
use Illuminate\Contracts\Pagination\CursorPaginator;

/**
 * Domain result returned by AdFeedService::build(): the ranked, sponsorship-
 * distributed cursor page together with the cached approximate inventory total
 * the feed surfaces as `total_approximate`.
 */
final readonly class AdFeedResult
{
    /**
     * @param  CursorPaginator<int, Ad>  $paginator
     */
    public function __construct(
        public CursorPaginator $paginator,
        public int $total,
    ) {}
}
