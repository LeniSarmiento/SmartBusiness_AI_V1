<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Services\SecurityAuditService;
use App\Services\UserSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rules\Password;

class MemberController extends Controller
{
    public function index(Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'members.manage']);

        return response()->json([
            'data' => $organization->memberships()->with('user', 'role')->get()->map(fn ($m) => [
                'id' => $m->id,
                'user_id' => $m->user_id,
                'name' => $m->user->name,
                'email' => $m->user->email,
                'role' => $m->role->name,
                'active' => (bool) $m->active,
                'deactivated_at' => $m->deactivated_at?->toIso8601String(),
                'last_login_at' => $m->user->last_login_at?->toIso8601String(),
                'email_verified' => ! is_null($m->user->email_verified_at),
            ]),
        ]);
    }

    public function store(Request $request, Organization $organization, SecurityAuditService $audit)
    {
        Gate::authorize('permission', [$organization, 'members.manage']);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'lowercase', 'max:255', 'unique:users,email'],
            'password' => ['required', 'string', 'max:72', Password::min(12)->mixedCase()->numbers()],
            'role' => ['required', 'in:admin,manager,seller'],
        ]);

        $membership = DB::transaction(function () use ($data, $organization, $request, $audit) {
            $user = User::create(collect($data)->only('name', 'email', 'password')->all());

            $membership = OrganizationMembership::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role_id' => Role::where('name', $data['role'])->firstOrFail()->id,
                'active' => true,
            ]);
            $audit->record($organization, $request->user(), 'membership.created', $membership, null, [
                'user_id' => $user->id,
                'role' => $data['role'],
                'active' => true,
            ]);

            return $membership;
        });

        return response()->json([
            'data' => [
                'id' => $membership->id,
                'name' => $data['name'],
                'email' => $data['email'],
                'role' => $data['role'],
                'active' => true,
            ],
        ], 201);
    }

    public function update(
        Request $request,
        Organization $organization,
        int $member,
        SecurityAuditService $audit,
        UserSessionService $sessions,
    ) {
        Gate::authorize('permission', [$organization, 'members.manage']);
        $data = $request->validate([
            'role' => ['sometimes', 'in:admin,manager,seller'],
            'active' => ['sometimes', 'boolean'],
        ]);

        abort_if(empty($data), 422, 'Se requiere al menos un campo a modificar (role o active).');

        DB::transaction(function () use ($organization, $member, $data, $request, $audit, $sessions) {
            Organization::lockForUpdate()->findOrFail($organization->id);
            $membership = $organization->memberships()->with('role', 'user')->findOrFail($member);
            $before = ['role' => $membership->role->name, 'active' => (bool) $membership->active];

            $willBeInactive = array_key_exists('active', $data) && ! $data['active'];
            $willLoseAdmin = array_key_exists('role', $data) && $data['role'] !== 'admin';

            if ($membership->role->name === 'admin' && ($willBeInactive || $willLoseAdmin)) {
                $otherActiveAdmins = $organization->memberships()
                    ->where('id', '!=', $membership->id)
                    ->where('active', true)
                    ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
                    ->count();

                abort_if($otherActiveAdmins < 1, 409, 'La empresa debe conservar al menos un administrador activo.');
            }

            $updates = [];
            if (array_key_exists('role', $data)) {
                $updates['role_id'] = Role::where('name', $data['role'])->firstOrFail()->id;
            }
            if (array_key_exists('active', $data)) {
                $updates['active'] = $data['active'];
                $updates['deactivated_at'] = $data['active'] ? null : now();
            }

            $membership->update($updates);
            $membership->refresh()->load('role');
            $after = ['role' => $membership->role->name, 'active' => (bool) $membership->active];

            if ($before['role'] !== $after['role']) {
                $audit->record($organization, $request->user(), 'membership.role_changed', $membership, $before, $after);
            }
            if ($before['active'] !== $after['active']) {
                $audit->record(
                    $organization,
                    $request->user(),
                    $after['active'] ? 'membership.reactivated' : 'membership.deactivated',
                    $membership,
                    $before,
                    $after
                );
                if (! $after['active']) {
                    $sessions->revoke($membership->user);
                }
            }
        });

        return response()->json(['message' => 'Miembro actualizado.']);
    }

    public function destroy(
        Request $request,
        Organization $organization,
        int $member,
        SecurityAuditService $audit,
        UserSessionService $sessions,
    ) {
        Gate::authorize('permission', [$organization, 'members.manage']);

        return DB::transaction(function () use ($organization, $member, $request, $audit, $sessions) {
            Organization::lockForUpdate()->findOrFail($organization->id);
            $membership = $organization->memberships()->with('role', 'user')->findOrFail($member);

            if ($membership->role->name === 'admin') {
                $otherActiveAdmins = $organization->memberships()
                    ->where('id', '!=', $membership->id)
                    ->where('active', true)
                    ->whereHas('role', fn ($q) => $q->where('name', 'admin'))
                    ->count();

                abort_if($otherActiveAdmins < 1, 409, 'No puedes eliminar al único administrador de la empresa.');
            }

            $hasSales = DB::table('sales')->where('organization_id', $organization->id)->where('seller_id', $membership->user_id)->exists();
            $hasMovements = DB::table('inventory_movements')->where('organization_id', $organization->id)->where('user_id', $membership->user_id)->exists();

            if ($hasSales || $hasMovements) {
                abort(409, 'El miembro tiene registros históricos (ventas o movimientos) asociados a la empresa. Para revocar su acceso preservando la auditoría, desactiva su membresía en lugar de eliminarla.');
            }

            $audit->record($organization, $request->user(), 'membership.removed', $membership, [
                'user_id' => $membership->user_id,
                'role' => $membership->role->name,
                'active' => (bool) $membership->active,
            ]);
            $membership->delete();
            $sessions->revoke($membership->user);

            return response()->json(['message' => 'Miembro retirado de la empresa.']);
        });
    }
}
