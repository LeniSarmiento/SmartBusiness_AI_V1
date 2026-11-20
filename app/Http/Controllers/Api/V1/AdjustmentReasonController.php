<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\AdjustmentReason;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class AdjustmentReasonController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'inventory.read']);

        $query = AdjustmentReason::forOrganization($organization);

        if ($type = $request->query('type')) {
            $query->where('type', $type);
        }

        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return response()->json(['data' => $query->orderBy('name')->get()]);
    }

    public function store(Request $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'inventory.write']);

        $data = $request->validate([
            'name' => [
                'required',
                'string',
                'max:100',
                Rule::unique('adjustment_reasons')->where('organization_id', $organization->id),
            ],
            'type' => ['required', 'in:entry,exit,adjustment'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $reason = AdjustmentReason::create([
            'organization_id' => $organization->id,
            'name' => $data['name'],
            'type' => $data['type'],
            'active' => $data['active'] ?? true,
        ]);

        return response()->json(['data' => $reason], 201);
    }

    public function update(Request $request, Organization $organization, AdjustmentReason $adjustmentReason)
    {
        Gate::authorize('permission', [$organization, 'inventory.write']);
        abort_unless($adjustmentReason->organization_id === $organization->id, 404);

        $data = $request->validate([
            'name' => [
                'sometimes',
                'string',
                'max:100',
                Rule::unique('adjustment_reasons')->where('organization_id', $organization->id)->ignore($adjustmentReason->id),
            ],
            'type' => ['sometimes', 'in:entry,exit,adjustment'],
            'active' => ['sometimes', 'boolean'],
        ]);

        $adjustmentReason->update($data);

        return response()->json(['data' => $adjustmentReason]);
    }

    public function destroy(Organization $organization, AdjustmentReason $adjustmentReason)
    {
        Gate::authorize('permission', [$organization, 'inventory.write']);
        abort_unless($adjustmentReason->organization_id === $organization->id, 404);

        $adjustmentReason->delete();

        return response()->noContent();
    }
}
