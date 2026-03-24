<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Models\LoginHistory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class LoginHistoryController
{
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();

        $history = LoginHistory::query()
            ->where('user_id', auth()->id())
            ->select(['id', 'ip_address', 'device_type', 'browser', 'platform', 'country', 'city', 'guard', 'successful', 'created_at'])
            ->latest()
            ->paginate(20);

        return response()->json([
            'data' => $history,
            'current_login' => [
                'last_login_at' => $user->last_login_at,
                'last_login_ip' => $user->last_login_ip,
                'last_login_country' => $user->last_login_country,
                'last_login_city' => $user->last_login_city,
            ],
        ]);
    }

    public function destroy(Request $request): JsonResponse
    {
        $deleted = LoginHistory::query()
            ->where('user_id', auth()->id())
            ->delete();

        return response()->json([
            'message' => 'Historique de connexion supprimé.',
            'deleted' => $deleted,
        ]);
    }
}
