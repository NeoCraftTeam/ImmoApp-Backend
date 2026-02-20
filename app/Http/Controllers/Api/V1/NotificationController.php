<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\PropertyAttribute;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use OpenApi\Attributes as OA;

final class NotificationController
{
    /**
     * Get all notifications for the authenticated user.
     */
    #[OA\Get(
        path: '/api/v1/notifications',
        summary: 'Get user notifications',
        description: 'Returns paginated list of notifications for the authenticated user.',
        security: [['sanctum' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'unread_only', in: 'query', schema: new OA\Schema(type: 'boolean')),
            new OA\Parameter(name: 'per_page', in: 'query', schema: new OA\Schema(type: 'integer', default: 15)),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notifications retrieved successfully'),
            new OA\Response(response: 401, description: 'Unauthenticated'),
        ]
    )]
    public function index(Request $request): JsonResponse
    {
        $user = $request->user();
        $perPage = min((int) $request->get('per_page', 15), 100);

        $query = $user->notifications();

        if ($request->boolean('unread_only')) {
            $query->whereNull('read_at');
        }

        $notifications = $query->latest()->paginate($perPage);

        return response()->json([
            'success' => true,
            'data' => $notifications->items(),
            'meta' => [
                'current_page' => $notifications->currentPage(),
                'last_page' => $notifications->lastPage(),
                'per_page' => $notifications->perPage(),
                'total' => $notifications->total(),
                'unread_count' => $user->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * Get unread notifications count.
     */
    #[OA\Get(
        path: '/api/v1/notifications/unread-count',
        summary: 'Get unread notifications count',
        security: [['sanctum' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'Count retrieved successfully'),
        ]
    )]
    public function unreadCount(Request $request): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => [
                'count' => $request->user()->unreadNotifications()->count(),
            ],
        ]);
    }

    /**
     * Mark a notification as read.
     */
    #[OA\Post(
        path: '/api/v1/notifications/{id}/read',
        summary: 'Mark notification as read',
        security: [['sanctum' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification marked as read'),
            new OA\Response(response: 404, description: 'Notification not found'),
        ]
    )]
    public function markAsRead(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Notification marquée comme lue',
        ]);
    }

    /**
     * Mark all notifications as read.
     */
    #[OA\Post(
        path: '/api/v1/notifications/read-all',
        summary: 'Mark all notifications as read',
        security: [['sanctum' => []]],
        tags: ['Notifications'],
        responses: [
            new OA\Response(response: 200, description: 'All notifications marked as read'),
        ]
    )]
    public function markAllAsRead(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications->markAsRead();

        return response()->json([
            'success' => true,
            'message' => 'Toutes les notifications ont été marquées comme lues',
        ]);
    }

    /**
     * Delete a notification.
     */
    #[OA\Delete(
        path: '/api/v1/notifications/{id}',
        summary: 'Delete a notification',
        security: [['sanctum' => []]],
        tags: ['Notifications'],
        parameters: [
            new OA\Parameter(name: 'id', in: 'path', required: true, schema: new OA\Schema(type: 'string')),
        ],
        responses: [
            new OA\Response(response: 200, description: 'Notification deleted'),
            new OA\Response(response: 404, description: 'Notification not found'),
        ]
    )]
    public function destroy(Request $request, string $id): JsonResponse
    {
        $notification = $request->user()->notifications()->findOrFail($id);
        $notification->delete();

        return response()->json([
            'success' => true,
            'message' => 'Notification supprimée',
        ]);
    }

    /**
     * Get available property attributes.
     */
    #[OA\Get(
        path: '/api/v1/property-attributes',
        summary: 'Get available property attributes',
        description: 'Returns list of available property attributes (Wi-Fi, parking, etc.) for ads.',
        tags: ['Ads'],
        responses: [
            new OA\Response(response: 200, description: 'Property attributes retrieved successfully'),
        ]
    )]
    public function propertyAttributes(): JsonResponse
    {
        return response()->json([
            'success' => true,
            'data' => PropertyAttribute::toSelectArray(),
        ]);
    }
}
