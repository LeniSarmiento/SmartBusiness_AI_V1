<?php

namespace App\Http\Middleware;

use App\Models\Organization;
use Closure;
use Illuminate\Http\Request;

class EnsureOrganizationMember
{
    public function handle(Request $request, Closure $next): mixed
    {
        $organization = $request->route('organization');
        if (! $organization instanceof Organization) {
            $organization = Organization::findOrFail($organization);
            $request->route()->setParameter('organization', $organization);
        }
        $membership = $request->user()->memberships()->where('organization_id', $organization->id)->with('role.permissions')->first();
        abort_unless($membership, 404);
        $request->attributes->set('membership', $membership);

        return $next($request);
    }
}
