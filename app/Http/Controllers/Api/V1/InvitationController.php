<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Models\OrganizationInvitation;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use App\Notifications\OrganizationInvitationNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class InvitationController extends Controller
{
    public function index(Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'members.manage']);

        return response()->json([
            'data' => $organization->invitations()->with('inviter', 'role')->latest()->get()->map(fn ($inv) => [
                'id' => $inv->id,
                'email' => $inv->email,
                'role' => $inv->role->name,
                'invited_by' => $inv->inviter->name,
                'is_pending' => $inv->isPending(),
                'is_expired' => $inv->isExpired(),
                'accepted_at' => $inv->accepted_at?->toIso8601String(),
                'expires_at' => $inv->expires_at->toIso8601String(),
                'created_at' => $inv->created_at->toIso8601String(),
                'url' => config('app.frontend_url').'/invitations/'.$inv->token,
            ]),
        ]);
    }

    public function store(Request $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'members.manage']);

        $data = $request->validate([
            'email' => ['required', 'email', 'lowercase', 'max:255'],
            'role' => ['required', 'in:admin,manager,seller'],
        ]);

        $isAlreadyMember = $organization->memberships()
            ->whereHas('user', fn ($q) => $q->where('email', $data['email']))
            ->where('active', true)
            ->exists();

        abort_if($isAlreadyMember, 422, 'El usuario ya es un miembro activo de esta organización.');

        $role = Role::where('name', $data['role'])->firstOrFail();
        $token = Str::random(64);

        $invitation = OrganizationInvitation::updateOrCreate(
            ['organization_id' => $organization->id, 'email' => $data['email']],
            [
                'invited_by' => $request->user()->id,
                'role_id' => $role->id,
                'token' => $token,
                'expires_at' => now()->addDays(7),
                'accepted_at' => null,
                'accepted_by' => null,
            ]
        );

        $invitation->load('organization', 'inviter', 'role');

        Notification::route('mail', $invitation->email)
            ->notify(new OrganizationInvitationNotification($invitation));

        return response()->json([
            'data' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
                'role' => $invitation->role->name,
                'token' => $invitation->token,
                'expires_at' => $invitation->expires_at->toIso8601String(),
                'url' => config('app.frontend_url').'/invitations/'.$invitation->token,
            ],
            'message' => 'Invitación enviada exitosamente.',
        ], 201);
    }

    public function destroy(Organization $organization, int $invitation)
    {
        Gate::authorize('permission', [$organization, 'members.manage']);

        $inv = $organization->invitations()->findOrFail($invitation);
        $inv->delete();

        return response()->json(['message' => 'Invitación cancelada.']);
    }

    public function show(string $token)
    {
        $invitation = OrganizationInvitation::with('organization', 'inviter', 'role')
            ->where('token', $token)
            ->firstOrFail();

        return response()->json([
            'data' => [
                'organization' => $invitation->organization->only('id', 'name'),
                'inviter' => $invitation->inviter->only('id', 'name'),
                'email' => $invitation->email,
                'role' => $invitation->role->name,
                'is_valid' => $invitation->isPending(),
                'is_expired' => $invitation->isExpired(),
                'is_accepted' => ! is_null($invitation->accepted_at),
                'expires_at' => $invitation->expires_at->toIso8601String(),
            ],
        ]);
    }

    public function accept(Request $request, string $token)
    {
        $invitation = OrganizationInvitation::with('organization', 'role')
            ->where('token', $token)
            ->firstOrFail();

        abort_unless($invitation->isPending(), 410, 'La invitación ha expirado o ya fue utilizada.');

        $user = Auth::guard('web')->user();

        if ($user) {
            abort_unless(
                strtolower($user->email) === strtolower($invitation->email),
                403,
                'Esta invitación fue dirigida a una dirección de correo diferente a la de tu sesión activa.'
            );
        } else {
            $existingUser = User::where('email', strtolower($invitation->email))->first();

            if ($existingUser) {
                throw ValidationException::withMessages([
                    'email' => 'Ya existe una cuenta con este correo. Inicia sesión antes de aceptar la invitación.',
                ]);
            }

            $data = $request->validate([
                'name' => ['required', 'string', 'max:120'],
                'password' => ['required', 'string', 'confirmed', 'max:72', PasswordRule::min(12)->mixedCase()->numbers()],
            ]);

            $user = User::create([
                'name' => $data['name'],
                'email' => strtolower($invitation->email),
                'password' => $data['password'],
            ]);
            $user->markEmailAsVerified();
            $user->forceFill(['last_login_at' => now()])->save();

            if ($request->hasSession()) {
                Auth::guard('web')->login($user);
                $request->session()->regenerate();
            }
        }

        DB::transaction(function () use ($invitation, $user) {
            OrganizationMembership::updateOrCreate(
                ['organization_id' => $invitation->organization_id, 'user_id' => $user->id],
                ['role_id' => $invitation->role_id, 'active' => true, 'deactivated_at' => null]
            );

            $invitation->update([
                'accepted_at' => now(),
                'accepted_by' => $user->id,
            ]);
        });

        return response()->json([
            'message' => '¡Te has unido exitosamente a '.$invitation->organization->name.'!',
            'data' => new UserResource($user->load('memberships.organization', 'memberships.role.permissions')),
        ]);
    }
}
