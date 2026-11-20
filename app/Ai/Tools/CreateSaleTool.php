<?php

namespace App\Ai\Tools;

use App\Models\Organization;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class CreateSaleTool extends BaseTool
{
    public function __construct(private SaleService $saleService) {}

    public function getName(): string
    {
        return 'create_sale';
    }

    public function getDescription(): string
    {
        return 'Crea y confirma una venta transaccional en la organización, descontando inventario con verificación de stock y cálculo de precios en el servidor.';
    }

    public function getRequiredPermission(): string
    {
        return 'sales.write';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'items' => [
                    'type' => 'array',
                    'description' => 'Lista de productos a vender.',
                    'items' => [
                        'type' => 'object',
                        'properties' => [
                            'product_id' => [
                                'type' => 'integer',
                                'description' => 'ID del producto a vender.',
                            ],
                            'quantity' => [
                                'type' => 'integer',
                                'description' => 'Cantidad de unidades a vender (mínimo 1).',
                            ],
                        ],
                        'required' => ['product_id', 'quantity'],
                    ],
                ],
                'customer_id' => [
                    'type' => 'integer',
                    'description' => 'ID opcional del cliente registrado en la organización.',
                ],
                'notes' => [
                    'type' => 'string',
                    'description' => 'Notas u observaciones opcionales de la venta.',
                ],
                'request_key' => [
                    'type' => 'string',
                    'description' => 'Clave de idempotencia UUID v4 opcional. Si no se provee, el sistema genera una automáticamente.',
                ],
            ],
            'required' => ['items'],
        ];
    }

    public function execute(Organization $organization, User $user, array $arguments): array
    {
        $this->authorize($organization, $user);

        if (empty($arguments['request_key'])) {
            $arguments['request_key'] = (string) Str::uuid();
        }

        $validated = $this->validate($arguments, [
            'request_key' => ['required', 'uuid'],
            'customer_id' => [
                'nullable',
                'integer',
                Rule::exists('customers', 'id')
                    ->where('organization_id', $organization->id)
                    ->whereNull('deleted_at')
                    ->where('active', true),
            ],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:100'],
            'items.*.product_id' => [
                'required',
                'integer',
                'distinct',
                Rule::exists('products', 'id')
                    ->where('organization_id', $organization->id)
                    ->whereNull('deleted_at')
                    ->where('active', true),
            ],
            'items.*.quantity' => ['required', 'integer', 'between:1,10000'],
        ]);

        $sale = $this->saleService->create($organization, $user, $validated);

        return [
            'sale_id' => $sale->id,
            'number' => $sale->number,
            'status' => $sale->status->value,
            'customer_id' => $sale->customer_id,
            'customer_name' => $sale->customer_name,
            'subtotal' => $sale->subtotal,
            'tax' => $sale->tax,
            'total' => $sale->total,
            'items_count' => count($sale->items),
            'items' => $sale->items->map(fn ($item) => [
                'product_id' => $item->product_id,
                'product_name' => $item->product_name,
                'product_sku' => $item->product_sku,
                'quantity' => $item->quantity,
                'unit_price' => $item->unit_price,
                'subtotal' => $item->subtotal,
                'tax' => $item->tax,
                'total' => $item->total,
            ])->all(),
            'sold_at' => $sale->sold_at->toIso8601String(),
        ];
    }
}
