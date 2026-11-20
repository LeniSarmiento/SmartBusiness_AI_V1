<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Http\Resources\UserResource;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Events\PasswordReset;
use Illuminate\Auth\Events\Verified;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(RegisterRequest $request)
    {
        abort_unless($request->hasSession(), 400, 'Utiliza el origen de la SPA y una sesión CSRF.');
        $data = $request->validated();
        $user = DB::transaction(function () use ($data) {
            $organization = Organization::create([
                'name' => $data['organization_name'],
                'currency' => $data['currency'] ?? 'COP',
                'timezone' => $data['timezone'] ?? 'America/Bogota',
            ]);
            $user = User::create(collect($data)->only('name', 'email', 'password')->all());
            OrganizationMembership::create([
                'organization_id' => $organization->id,
                'user_id' => $user->id,
                'role_id' => Role::where('name', 'admin')->firstOrFail()->id,
                'active' => true,
            ]);

            return $user;
        });

        Auth::guard('web')->login($user);
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        $user->sendEmailVerificationNotification();

        return (new UserResource($user->load('memberships.organization', 'memberships.role.permissions')))->response()->setStatusCode(201);
    }

    public function login(LoginRequest $request)
    {
        abort_unless($request->hasSession(), 400, 'Utiliza el origen de la SPA y una sesión CSRF.');
        if (! Auth::guard('web')->attempt($request->validated())) {
            throw ValidationException::withMessages(['email' => 'Las credenciales no son correctas.']);
        }

        $user = Auth::guard('web')->user();
        $user->forceFill(['last_login_at' => now()])->save();
        $request->session()->regenerate();

        return $this->me($request);
    }

    public function me(Request $request)
    {
        return new UserResource($request->user()->load('memberships.organization', 'memberships.role.permissions'));
    }

    public function logout(Request $request)
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->noContent();
    }

    public function profile(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'lowercase', 'max:255', Rule::unique('users')->ignore($request->user()->id)],
            'current_password' => ['required', 'current_password:web'],
        ]);

        $user = $request->user();
        $emailChanged = strtolower($data['email']) !== strtolower($user->email);
        $user->update(collect($data)->only('name', 'email')->all());

        if ($emailChanged) {
            $user->forceFill(['email_verified_at' => null])->save();
            $user->sendEmailVerificationNotification();
        }

        return $this->me($request);
    }

    public function changePassword(Request $request)
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password:web'],
            'password' => ['required', 'confirmed', 'max:72', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);

        $user = $request->user();
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'remember_token' => Str::random(60),
        ])->save();

        if ($request->hasSession()) {
            DB::table('sessions')
                ->where('user_id', $user->id)
                ->where('id', '!=', $request->session()->getId())
                ->delete();
        }

        return response()->json(['message' => 'Contraseña actualizada correctamente.']);
    }

    public function verifyEmail(Request $request, int $id, string $hash)
    {
        $user = User::findOrFail($id);

        abort_unless(hash_equals(sha1($user->getEmailForVerification()), (string) $hash), 403, 'Enlace de verificación inválido o alterado.');

        if (! $user->hasVerifiedEmail()) {
            $user->markEmailAsVerified();
            event(new Verified($user));
        }

        if ($request->expectsJson() || $request->is('api/*')) {
            return response()->json(['message' => 'Correo electrónico verificado exitosamente.']);
        }

        return redirect(config('app.frontend_url').'/login?verified=1');
    }

    public function resendVerification(Request $request)
    {
        $user = $request->user();

        if ($user->hasVerifiedEmail()) {
            return response()->json(['message' => 'Tu correo electrónico ya se encuentra verificado.']);
        }

        $user->sendEmailVerificationNotification();

        return response()->json(['message' => 'Enlace de verificación enviado a tu correo.']);
    }

    public function forgot(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::sendResetLink(['email' => strtolower($data['email'])]);

        return response()->json(['message' => 'Si el correo está registrado, recibirás un enlace para restablecer tu contraseña.']);
    }

    public function reset(Request $request)
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', 'max:72', PasswordRule::min(12)->mixedCase()->numbers()],
        ]);

        $status = Password::reset($data, function (User $user, string $password) {
            $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
            DB::table('sessions')->where('user_id', $user->id)->delete();
            event(new PasswordReset($user));
        });

        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'El enlace es inválido o ha expirado.']);
        }

        return response()->json(['message' => 'Contraseña actualizada. Ya puedes iniciar sesión.']);
    }
}
