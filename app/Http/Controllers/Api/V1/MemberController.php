<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class MemberController extends Controller
{
    public function index(Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'members.manage']);

        return response()->json(['data' => $organization->memberships()->with('user', 'role')->get()->map(fn ($m) => ['id' => $m->id, 'name' => $m->user->name, 'email' => $m->user->email, 'role' => $m->role->name])]);
    }

    public function store(Request $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'members.manage']);
        // Existing global accounts require a future invitation/acceptance workflow.
        $data = $request->validate(['name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'lowercase', 'max:255', 'unique:users,email'], 'password' => ['required', 'string', 'max:72', Password::min(12)->mixedCase()->numbers()], 'role' => ['required', 'in:admin,manager,seller']]);
        $membership = DB::transaction(function () use ($data, $organization) {
            $user = User::create(collect($data)->only('name', 'email', 'password')->all());

            return OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => Role::where('name', $data['role'])->firstOrFail()->id]);
        });

        return response()->json(['data' => ['id' => $membership->id, 'name' => $data['name'], 'email' => $data['email'], 'role' => $data['role']]], 201);
    }

    public function update(Request $request, Organization $organization, int $member)
    {
        Gate::authorize('permission', [$organization, 'members.manage']);
        $data = $request->validate(['role' => ['required', 'in:admin,manager,seller']]);
        DB::transaction(function () use ($organization, $member, $data) {
            Organization::lockForUpdate()->findOrFail($organization->id);
            $membership = $organization->memberships()->with('role')->findOrFail($member);
            if ($membership->role->name === 'admin' && $data['role'] !== 'admin') {
                abort_if($organization->memberships()->whereHas('role', fn ($q) => $q->where('name', 'admin'))->count() <= 1, 409, 'La empresa debe conservar al menos un administrador.');
            }
            $membership->update(['role_id' => Role::where('name', $data['role'])->firstOrFail()->id]);
        });

        return response()->json(['message' => 'Rol actualizado.']);
    }
}
