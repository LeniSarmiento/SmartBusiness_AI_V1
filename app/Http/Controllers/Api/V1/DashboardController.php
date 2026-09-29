<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\SaleResource;
use App\Models\Organization;
use App\Services\ReportingService;
use Illuminate\Support\Facades\Gate;

class DashboardController extends Controller
{
    public function __invoke(Organization $organization, ReportingService $service)
    {
        Gate::authorize('permission', [$organization, 'reports.read']);
        $data = $service->dashboard($organization);
        $data['recent_sales'] = SaleResource::collection($data['recent_sales']);

        return response()->json(['data' => $data]);
    }
}
