<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\ProductResource;
use App\Models\Organization;
use App\Models\Supplier;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class SupplierController extends Controller
{
    public function index(ListRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'suppliers.read']);
        $query = Supplier::forOrganization($organization);
        if ($search = $request->validated('search')) {
            $query->where(fn ($q) => $q->where('name', 'ilike', '%'.$search.'%'));
        }
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return ContactResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20)));
    }

    public function store(ContactRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'suppliers.write']);
        $model = Supplier::create([...$request->validated(), 'organization_id' => $organization->id]);

        return (new ContactResource($model))->response()->setStatusCode(201);
    }

    public function show(Organization $organization, int $supplier)
    {
        Gate::authorize('permission', [$organization, 'suppliers.read']);

        return new ContactResource(Supplier::forOrganization($organization)->findOrFail($supplier));
    }

    public function update(ContactRequest $request, Organization $organization, int $supplier)
    {
        Gate::authorize('permission', [$organization, 'suppliers.write']);
        $model = DB::transaction(function () use ($organization, $supplier, $request) {
            $model = Supplier::forOrganization($organization)->lockForUpdate()->findOrFail($supplier);
            $model->update($request->validated());

            return $model;
        });

        return new ContactResource($model);
    }

    public function destroy(Organization $organization, int $supplier)
    {
        Gate::authorize('permission', [$organization, 'suppliers.write']);
        DB::transaction(function () use ($organization, $supplier) {
            $model = Supplier::forOrganization($organization)->lockForUpdate()->findOrFail($supplier);

            $model->delete();
        });

        return response()->noContent();
    }

    public function products(ListRequest $request, Organization $organization, int $supplier)
    {
        Gate::authorize('permission', [$organization, 'suppliers.read']);
        $model = Supplier::forOrganization($organization)->findOrFail($supplier);

        return ProductResource::collection($model->products()->with('category', 'supplier')->paginate($request->integer('per_page', 20)));
    }
}
