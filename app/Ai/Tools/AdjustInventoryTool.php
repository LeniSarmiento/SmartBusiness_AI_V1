<?php

namespace App\Ai\Tools;

use App\Models\Organization;
use App\Models\User;
use App\Services\InventoryService;
use Illuminate\Validation\Rule;

class AdjustInventoryTool extends BaseTool
{
    public function __construct(private InventoryService $inventoryService) {}

    public function getName(): string
    {
        return 'adjust_inventory';
    }

    public function getDescription(): string
    {
        return 'Registra un movimiento de inventario (entrada, salida o ajuste manual) para un producto de la organización.';
    }

    public function getRequiredPermission(): string
    {
        return 'inventory.write';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'product_id' => [
                    'type' => 'integer',
                    'description' => 'ID único del producto en la organización.',
                ],
                'type' => [
                    'type' => 'string',
                    'enum' => ['entry', 'exit', 'adjustment'],
                    'description' => 'Tipo de movimiento: entrada (entry), salida (exit) o ajuste manual (adjustment).',
                ],
                'quantity' => [
                    'type' => 'integer',
                    'description' => 'Cantidad de unidades a mover (entre 1 y 1,000,000).',
                ],
                'direction' => [
                    'type' => 'string',
                    'enum' => ['increase', 'decrease'],
                    'description' => 'Obligatorio si type es "adjustment": increase para aumentar stock, decrease para reducirlo.',
                ],
                'reason' => [
                    'type' => 'string',
                    'description' => 'Motivo detallado del movimiento de inventario (mínimo 5 caracteres).',
                ],
                'reference_number' => [
                    'type' => 'string',
                    'description' => 'Número de documento o referencia externa (opcional, ej: FACT-1029, REM-001).',
                ],
            ],
            'required' => ['product_id', 'type', 'quantity', 'reason'],
        ];
    }

    public function execute(Organization $organization, User $user, array $arguments): array
    {
        $this->authorize($organization, $user);

        $validated = $this->validate($arguments, [
            'product_id' => [
                'required',
                'integer',
                Rule::exists('products', 'id')
                    ->where('organization_id', $organization->id)
                    ->where('active', true)
                    ->whereNull('deleted_at'),
            ],
            'type' => ['required', 'in:entry,exit,adjustment'],
            'quantity' => ['required', 'integer', 'between:1,1000000'],
            'direction' => ['required_if:type,adjustment', 'nullable', 'in:increase,decrease'],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
            'reference_number' => ['nullable', 'string', 'max:100'],
        ]);

        $movement = $this->inventoryService->adjust($organization, $user, $validated);
        $movement->load('product');

        return [
            'movement_id' => $movement->id,
            'product_id' => $movement->product_id,
            'product_name' => $movement->product->name,
            'product_sku' => $movement->product->sku,
            'type' => $movement->type->value,
            'quantity_delta' => $movement->quantity_delta,
            'balance_after' => $movement->balance_after,
            'reason' => $movement->reason,
            'reference_number' => $movement->reference_number,
            'created_at' => $movement->created_at->toIso8601String(),
        ];
    }
}
