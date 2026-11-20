<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\ListRequest;
use App\Http\Requests\StockRequest;
use App\Http\Resources\MovementResource;
use App\Models\InventoryMovement;
use App\Models\Organization;
use App\Services\InventoryService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function index(ListRequest $request, Organization $organization)
    {
        Gate::authorize('permission', [$organization, 'inventory.read']);
        $query = $this->buildFilteredQuery($request, $organization);

        return MovementResource::collection($query->orderByDesc('id')->paginate($request->integer('per_page', 20)));
    }

    public function store(StockRequest $request, Organization $organization, InventoryService $service)
    {
        Gate::authorize('permission', [$organization, 'inventory.write']);

        return (new MovementResource(
            $service->adjust($organization, $request->user(), $request->validated())->load('product', 'user')
        ))->response()->setStatusCode(201);
    }

    public function export(ListRequest $request, Organization $organization): StreamedResponse
    {
        Gate::authorize('permission', [$organization, 'inventory.read']);

        $query = $this->buildFilteredQuery($request, $organization)->orderByDesc('id');
        $filename = 'movimientos-inventario-'.now()->format('Y-m-d-His').'.csv';

        $headers = [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}\"",
            'Pragma' => 'no-cache',
            'Cache-Control' => 'must-revalidate, post-check=0, pre-check=0',
            'Expires' => '0',
        ];

        return response()->stream(function () use ($query) {
            $handle = fopen('php://output', 'w');
            // Write UTF-8 BOM for Excel compatibility
            fprintf($handle, chr(0xEF).chr(0xBB).chr(0xBF));

            fputcsv($handle, ['ID', 'Fecha', 'Producto', 'SKU', 'Tipo', 'Cantidad', 'Stock Resultante', 'Usuario', 'Referencia', 'Motivo']);

            $query->chunk(500, function ($movements) use ($handle) {
                foreach ($movements as $m) {
                    fputcsv($handle, [
                        $m->id,
                        $m->created_at->format('Y-m-d H:i:s'),
                        $m->product?->name ?? 'N/A',
                        $m->product?->sku ?? 'N/A',
                        $m->type->value,
                        $m->quantity_delta,
                        $m->balance_after,
                        $m->user?->name ?? 'N/A',
                        $m->reference_number ?? '',
                        $m->reason,
                    ]);
                }
            });

            fclose($handle);
        }, 200, $headers);
    }

    private function buildFilteredQuery(ListRequest $request, Organization $organization): Builder
    {
        $query = InventoryMovement::forOrganization($organization)->with('product', 'user');

        if ($productId = $request->validated('product_id')) {
            $query->where('product_id', $productId);
        }

        if ($userId = $request->validated('user_id')) {
            $query->where('user_id', $userId);
        }

        if ($type = $request->validated('type')) {
            $query->where('type', $type);
        }

        if ($from = $request->validated('from')) {
            $query->whereDate('created_at', '>=', $from);
        }

        if ($to = $request->validated('to')) {
            $query->whereDate('created_at', '<=', $to);
        }

        if ($search = $request->validated('search')) {
            $query->where(function (Builder $q) use ($search) {
                $q->whereHas('product', fn (Builder $pq) => $pq->where('name', 'ilike', "%{$search}%")->orWhere('sku', 'ilike', "%{$search}%"))
                    ->orWhere('reference_number', 'ilike', "%{$search}%")
                    ->orWhere('reason', 'ilike', "%{$search}%");
            });
        }

        return $query;
    }
}
