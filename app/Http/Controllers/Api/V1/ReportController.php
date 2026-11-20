<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Services\ReportingService;
use Illuminate\Support\Facades\Gate;

class ReportController extends Controller
{
    public function inventoryValuation(Organization $organization, ReportingService $service)
    {
        Gate::authorize('permission', [$organization, 'reports.read']);

        return response()->json(['data' => $service->inventoryValuation($organization)]);
    }
}
