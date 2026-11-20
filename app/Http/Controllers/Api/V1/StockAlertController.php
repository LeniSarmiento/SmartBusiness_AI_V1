<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Organization;
use App\Models\StockAlert;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class StockAlertController extends Controller
{
    public function index(Request $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'inventory.read']);

        $query = StockAlert::forOrganization($organization)->with('product');

        if ($request->boolean('active_only', true)) {
            $query->whereNull('resolved_at');
        }

        $alerts = $query->orderByDesc('id')->get()->map(function ($alert) {
            return [
                'id' => $alert->id,
                'product_id' => $alert->product_id,
                'product_name' => $alert->product?->name,
                'product_sku' => $alert->product?->sku,
                'current_stock' => $alert->product?->current_stock,
                'threshold_quantity' => $alert->threshold_quantity,
                'notified_at' => $alert->notified_at?->toIso8601String(),
                'resolved_at' => $alert->resolved_at?->toIso8601String(),
                'created_at' => $alert->created_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $alerts]);
    }
}
