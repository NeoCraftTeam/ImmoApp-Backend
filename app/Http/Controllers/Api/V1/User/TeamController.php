<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\User;

use App\Http\Requests\Api\V1\User\InviteTeamMemberRequest;
use App\Models\Agency;
use App\Models\TeamInvitation;
use App\Models\User;
use App\Notifications\TeamInvitationNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;

final class TeamController
{
    public function index(): JsonResponse
    {
        $user = auth()->user();

        if (!$user->agency_id) {
            return response()->json(['message' => 'Vous n\'avez pas d\'agence.'], 403);
        }

        $members = User::query()
            ->where('agency_id', $user->agency_id)
            ->select(['id', 'firstname', 'lastname', 'email', 'role', 'created_at'])
            ->get();

        $pendingInvitations = TeamInvitation::query()
            ->where('agency_id', $user->agency_id)
            ->whereNull('accepted_at')
            ->where('expires_at', '>', now())
            ->get();

        return response()->json([
            'members' => $members,
            'invitations' => $pendingInvitations,
        ]);
    }

    public function invite(InviteTeamMemberRequest $request): JsonResponse
    {
        $user = auth()->user();

        if (!$user->agency_id) {
            return response()->json(['message' => 'Vous n\'avez pas d\'agence.'], 403);
        }

        // OWASP A01 — only the agency owner may invite new members. This
        // mirrors the auth posture of `destroy()` and `removeMember()`
        // which already enforce `agency.owner_id === auth()->id()`. A
        // viewer/manager bound to the agency must not be able to grow
        // the membership of an agency they don't control.
        $agency = Agency::query()->find($user->agency_id);
        if (!$agency || $agency->owner_id !== auth()->id()) {
            return response()->json(['message' => 'Seul le propriétaire de l\'agence peut inviter de nouveaux membres.'], 403);
        }

        $validated = $request->validated();

        $alreadyMember = User::query()
            ->where('agency_id', $user->agency_id)
            ->where('email', $validated['email'])
            ->exists();

        if ($alreadyMember) {
            return response()->json(['message' => 'Cet utilisateur est déjà membre de votre agence.'], 409);
        }

        $invitation = TeamInvitation::query()->create([
            'agency_id' => $user->agency_id,
            'invited_by' => auth()->id(),
            'email' => $validated['email'],
            'role' => $validated['role'],
            'token' => Str::random(64),
            'expires_at' => now()->addDays(7),
        ]);

        Notification::route('mail', $validated['email'])
            ->notify(new TeamInvitationNotification($invitation));

        return response()->json(['data' => $invitation], 201);
    }

    public function accept(Request $request, string $token): JsonResponse
    {
        $invitation = TeamInvitation::query()
            ->where('token', $token)
            ->firstOrFail();

        if ($invitation->isAccepted()) {
            return response()->json(['message' => 'Cette invitation a déjà été acceptée.'], 409);
        }

        if ($invitation->isExpired()) {
            return response()->json(['message' => 'Cette invitation a expiré.'], 410);
        }

        /** @var User $authUser */
        $authUser = auth()->user();

        $authUser->forceFill(['agency_id' => $invitation->agency_id])->save();

        $invitation->forceFill(['accepted_at' => now()])->save();

        return response()->json(['message' => 'Invitation acceptée avec succès.']);
    }

    public function destroy(TeamInvitation $teamInvitation): JsonResponse
    {
        $agency = $teamInvitation->agency;

        if (!$agency || $agency->owner_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        $teamInvitation->delete();

        return response()->json(['message' => 'Invitation révoquée.']);
    }

    public function removeMember(User $user): JsonResponse
    {
        $authUser = auth()->user();

        if (!$authUser->agency_id) {
            return response()->json(['message' => 'Vous n\'avez pas d\'agence.'], 403);
        }

        $agency = Agency::query()->find($authUser->agency_id);

        if (!$agency || $agency->owner_id !== auth()->id()) {
            return response()->json(['message' => 'Non autorisé.'], 403);
        }

        if ($user->agency_id !== $authUser->agency_id) {
            return response()->json(['message' => 'Cet utilisateur n\'est pas membre de votre agence.'], 404);
        }

        $user->forceFill(['agency_id' => null])->save();

        return response()->json(['message' => 'Membre retiré de l\'agence.']);
    }
}
