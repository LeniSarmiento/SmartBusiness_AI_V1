<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\SecurityAuditLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class SecurityAuditController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'audit.read']);
        $data = $request->validate([
            'event' => ['sometimes', 'string', 'max:100'],
            'actor_id' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        $query = SecurityAuditLog::forOrganization($organization)->with('actor:id,name,email');
        if (isset($data['event'])) {
            $query->where('event', $data['event']);
        }
        if (isset($data['actor_id'])) {
            $query->where('actor_id', $data['actor_id']);
        }

        return response()->json($query->latest('id')->paginate($data['per_page'] ?? 25));
    }
}
