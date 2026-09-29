<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\StockRequest;
use App\Http\Resources\MovementResource;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Services\InventoryService;
use Illuminate\Support\Facades\Gate;

class InventoryController extends Controller
{
    public function index(ListRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'inventory.read']);
        $query = InventoryMovement::forOrganization($organization)->with('product', 'user');
        if ($id = $request->validated('product_id')) {
            $query->where('product_id', $id);
        }

        return MovementResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20)));
    }

    public function store(StockRequest $request, Organization $organization, InventoryService $service)
    {
        Gate::authorize('permission', [$organization, 'inventory.write']);

        return (new MovementResource($service->adjust($organization, $request->user(), $request->validated())->load('product', 'user')))->response()->setStatusCode(201);
    }
}
