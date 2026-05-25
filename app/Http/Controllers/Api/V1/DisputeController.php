<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Enums\DisputeEvidenceType;
use App\Enums\UserRole;
use App\Http\Requests\StoreDisputeMessageRequest;
use App\Http\Requests\StoreDisputeRequest;
use App\Http\Requests\TransitionDisputeRequest;
use App\Http\Requests\UploadDisputeEvidenceRequest;
use App\Http\Resources\DisputeEvidenceResource;
use App\Http\Resources\DisputeMessageResource;
use App\Http\Resources\DisputeResource;
use App\Models\Dispute;
use App\Services\DisputeService;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final readonly class DisputeController
{
    use AuthorizesRequests;

    public function __construct(
        private DisputeService $disputes,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Dispute::class);

        $user = $request->user();
        $isAdmin = ($user->role ?? null) === UserRole::ADMIN;

        $query = Dispute::query()
            ->with(['initiator', 'respondent', 'ad'])
            ->latest();

        if (!$isAdmin) {
            $query->involving($user->id);
        }

        if ($status = $request->query('status')) {
            $query->where('status', is_string($status) ? $status : '');
        }

        if ($request->boolean('open_only')) {
            $query->open();
        }

        return DisputeResource::collection($query->paginate(20));
    }

    public function store(StoreDisputeRequest $request): JsonResponse
    {
        $dispute = $this->disputes->open($request->user(), $request->validated());

        return DisputeResource::make($dispute->loadMissing(['initiator', 'respondent', 'ad']))
            ->response()
            ->setStatusCode(201);
    }

    public function show(Request $request, Dispute $dispute): DisputeResource
    {
        $this->authorize('view', $dispute);

        $dispute->load(['initiator', 'respondent', 'admin', 'ad', 'messages.sender', 'evidences']);

        return DisputeResource::make($dispute);
    }

    public function storeMessage(StoreDisputeMessageRequest $request, Dispute $dispute): JsonResponse
    {
        $this->authorize('reply', $dispute);

        $message = $this->disputes->addMessage(
            $dispute,
            $request->user(),
            $request->string('body')->toString(),
            $request->boolean('is_internal'),
        );

        return DisputeMessageResource::make($message->loadMissing('sender'))
            ->response()
            ->setStatusCode(201);
    }

    public function uploadEvidence(UploadDisputeEvidenceRequest $request, Dispute $dispute): JsonResponse
    {
        $this->authorize('uploadEvidence', $dispute);

        $evidence = $this->disputes->uploadEvidence(
            $dispute,
            $request->user(),
            $request->file('file'),
            DisputeEvidenceType::from($request->string('type')->toString()),
        );

        return DisputeEvidenceResource::make($evidence)
            ->response()
            ->setStatusCode(201);
    }

    public function transition(TransitionDisputeRequest $request, Dispute $dispute): DisputeResource
    {
        $this->authorize('transition', $dispute);

        $target = $request->targetStatus();

        $dispute = $this->disputes->transition(
            $dispute,
            $request->user(),
            $target,
            $request->input('resolution_note'),
        );

        return DisputeResource::make($dispute->loadMissing(['initiator', 'respondent', 'admin']));
    }
}
