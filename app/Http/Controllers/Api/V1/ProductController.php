<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\ProductRequest;
use App\Http\Resources\ProductResource;
use App\Models\Organization;
use App\Models\Product;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class ProductController extends Controller
{
    public function index(ListRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'products.read']);
        $query = Product::forOrganization($organization)->with(['category', 'supplier']);
        if ($search = $request->validated('search')) {
            $query->where(fn ($q) => $q->where('name', 'ilike', '%'.$search.'%')->orWhere('sku', 'ilike', '%'.$search.'%'));
        }
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }
        if ($request->boolean('low_stock')) {
            $query->whereColumn('current_stock', '<=', 'minimum_stock')->where('active', true);
        }

        return ProductResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20)));
    }

    public function store(ProductRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'products.write']);
        $model = Product::create([...$request->validated(), 'organization_id' => $organization->id])->refresh();

        return (new ProductResource($model))->response()->setStatusCode(201);
    }

    public function show(Organization $organization, int $product)
    {
        Gate::authorize('permission', [$organization, 'products.read']);

        return new ProductResource(Product::forOrganization($organization)->with(['category', 'supplier'])->findOrFail($product));
    }

    public function update(ProductRequest $request, Organization $organization, int $product)
    {
        Gate::authorize('permission', [$organization, 'products.write']);
        $model = DB::transaction(function () use ($organization, $product, $request) {
            $model = Product::forOrganization($organization)->lockForUpdate()->findOrFail($product);
            $model->update($request->validated());

            return $model;
        });

        return new ProductResource($model);
    }

    public function destroy(Organization $organization, int $product)
    {
        Gate::authorize('permission', [$organization, 'products.write']);
        DB::transaction(function () use ($organization, $product) {
            $model = Product::forOrganization($organization)->lockForUpdate()->findOrFail($product);
            abort_if($model->current_stock !== 0, 409, 'Ajusta el stock a cero antes de archivar el producto.');
            $model->delete();
        });

        return response()->noContent();
    }
}
