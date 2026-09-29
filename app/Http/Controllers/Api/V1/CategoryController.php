<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\CategoryRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CategoryController extends Controller
{
    public function index(ListRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'categories.read']);
        $query = Category::forOrganization($organization);
        if ($search = $request->validated('search')) {
            $query->where(fn ($q) => $q->where('name', 'ilike', '%'.$search.'%'));
        }
        if ($request->has('active')) {
            $query->where('active', $request->boolean('active'));
        }

        return CategoryResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20)));
    }

    public function store(CategoryRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'categories.write']);
        $model = Category::create([...$request->validated(), 'organization_id' => $organization->id]);

        return (new CategoryResource($model))->response()->setStatusCode(201);
    }

    public function show(Organization $organization, int $category)
    {
        Gate::authorize('permission', [$organization, 'categories.read']);

        return new CategoryResource(Category::forOrganization($organization)->findOrFail($category));
    }

    public function update(CategoryRequest $request, Organization $organization, int $category)
    {
        Gate::authorize('permission', [$organization, 'categories.write']);
        $model = DB::transaction(function () use ($organization, $category, $request) {
            $model = Category::forOrganization($organization)->lockForUpdate()->findOrFail($category);
            $model->update($request->validated());

            return $model;
        });

        return new CategoryResource($model);
    }

    public function destroy(Organization $organization, int $category)
    {
        Gate::authorize('permission', [$organization, 'categories.write']);
        DB::transaction(function () use ($organization, $category) {
            $model = Category::forOrganization($organization)->lockForUpdate()->findOrFail($category);

            $model->delete();
        });

        return response()->noContent();
    }
}
