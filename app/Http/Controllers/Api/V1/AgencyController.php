<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Actions\Agency\CreateAgencyAction;
use App\Actions\Agency\DeleteAgencyAction;
use App\Actions\Agency\ListAgenciesAction;
use App\Actions\Agency\UpdateAgencyAction;
use App\Http\Requests\AgencyRequest;
use App\Http\Resources\AgencyResource;
use App\Models\Agency;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

final class AgencyController
{
    use AuthorizesRequests;

    public function index(ListAgenciesAction $listAgencies)
    {
        $this->authorize('viewAny', Agency::class);

        return $listAgencies->handle();
    }

    public function store(AgencyRequest $request, CreateAgencyAction $createAgency)
    {
        $this->authorize('create', Agency::class);

        $agency = $createAgency->handle($request->validated());

        return new AgencyResource($agency);
    }

    public function show(Agency $agency)
    {
        $this->authorize('view', $agency);

        return new AgencyResource($agency);
    }

    public function update(AgencyRequest $request, Agency $agency, UpdateAgencyAction $updateAgency)
    {
        $this->authorize('update', $agency);

        $agency = $updateAgency->handle($agency, $request->validated());

        return new AgencyResource($agency);
    }

    public function destroy(Agency $agency, DeleteAgencyAction $deleteAgency)
    {
        $this->authorize('delete', $agency);

        $deleteAgency->handle($agency);

        return response()->json();
    }
}
