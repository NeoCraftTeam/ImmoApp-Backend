<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Belt-and-suspenders role gate for owner-only API routes.
 *
 * Complements the existing AdPolicy checks with an explicit HTTP-layer rejection
 * so that even if a policy is misconfigured, customers cannot mutate owner resources.
 *
 * Allowed roles: AGENT, ADMIN
 */
final class EnsureOwnerRole
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (!$user || (!$user->isAgent() && !$user->isAdmin())) {
            return response()->json([
                'message' => 'Cette action est réservée aux propriétaires et agences.',
            ], 403);
        }

        return $next($request);
    }
}
