<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ContactRequest;
use App\Http\Requests\ListRequest;
use App\Http\Resources\ContactResource;
use App\Http\Resources\SaleResource;
use App\Models\Customer;
use App\Models\Organization;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

class CustomerController extends Controller
{
    public function index(ListRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'customers.read']);
        $query = Customer::forOrganization($organization);
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
        Gate::authorize('permission', [$organization, 'customers.write']);
        $model = Customer::create([...$request->validated(), 'organization_id' => $organization->id]);

        return (new ContactResource($model))->response()->setStatusCode(201);
    }

    public function show(Organization $organization, int $customer)
    {
        Gate::authorize('permission', [$organization, 'customers.read']);

        return new ContactResource(Customer::forOrganization($organization)->findOrFail($customer));
    }

    public function update(ContactRequest $request, Organization $organization, int $customer)
    {
        Gate::authorize('permission', [$organization, 'customers.write']);
        $model = DB::transaction(function () use ($organization, $customer, $request) {
            $model = Customer::forOrganization($organization)->lockForUpdate()->findOrFail($customer);
            $model->update($request->validated());

            return $model;
        });

        return new ContactResource($model);
    }

    public function destroy(Organization $organization, int $customer)
    {
        Gate::authorize('permission', [$organization, 'customers.write']);
        DB::transaction(function () use ($organization, $customer) {
            $model = Customer::forOrganization($organization)->lockForUpdate()->findOrFail($customer);

            $model->delete();
        });

        return response()->noContent();
    }

    public function sales(ListRequest $request, Organization $organization, int $customer)
    {
        Gate::authorize('permission', [$organization, 'sales.read']);
        $model = Customer::forOrganization($organization)->findOrFail($customer);

        return SaleResource::collection($model->sales()->with('seller')->latest('sold_at')->paginate($request->integer('per_page', 20)));
    }
}
