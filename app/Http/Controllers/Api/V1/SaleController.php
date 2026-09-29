<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\StoreSaleRequest;
use App\Http\Resources\SaleResource;
use App\Models\Organization;
use App\Models\Sale;
use App\Services\SaleService;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SaleController extends Controller
{
    public function index(ListRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'sales.read']);
        $query = Sale::forOrganization($organization)->with('seller');
        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }
        if ($from = $request->validated('from')) {
            $query->where('sold_at', '>=', CarbonImmutable::parse($from, $organization->timezone)->startOfDay()->utc());
        }
        if ($to = $request->validated('to')) {
            $query->where('sold_at', '<', CarbonImmutable::parse($to, $organization->timezone)->addDay()->startOfDay()->utc());
        }
        if ($search = $request->validated('search')) {
            $query->where('customer_name', 'ilike', '%'.$search.'%');
        }

        return SaleResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20)));
    }

    public function show(Organization $organization, int $sale)
    {
        Gate::authorize('permission', [$organization, 'sales.read']);

        return new SaleResource(Sale::forOrganization($organization)->with('seller', 'items')->findOrFail($sale));
    }

    public function store(StoreSaleRequest $request, Organization $organization, SaleService $service)
    {
        Gate::authorize('permission', [$organization, 'sales.write']);

        return (new SaleResource($service->create($organization, $request->user(), $request->validated())))->response()->setStatusCode(201);
    }

    public function cancel(Request $request, Organization $organization, int $sale, SaleService $service)
    {
        Gate::authorize('permission', [$organization, 'sales.cancel']);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:500']]);

        return new SaleResource($service->cancel($organization, $request->user(), $sale, $data['reason']));
    }
}
