<?php

namespace App\Ai\Tools;

use App\Models\Organization;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Validation\Rule;

class CancelSaleTool extends BaseTool
{
    public function __construct(private SaleService $saleService) {}

    public function getName(): string
    {
        return 'cancel_sale';
    }

    public function getDescription(): string
    {
        return 'Anula una venta completada previamente en la organización, reintegrando las unidades al inventario y auditando el motivo.';
    }

    public function getRequiredPermission(): string
    {
        return 'sales.cancel';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => [
                'sale_id' => [
                    'type' => 'integer',
                    'description' => 'ID de la venta que se desea anular.',
                ],
                'reason' => [
                    'type' => 'string',
                    'description' => 'Motivo detallado de la anulación (mínimo 5 caracteres, máximo 500).',
                ],
            ],
            'required' => ['sale_id', 'reason'],
        ];
    }

    public function execute(Organization $organization, User $user, array $arguments): array
    {
        $this->authorize($organization, $user);

        $validated = $this->validate($arguments, [
            'sale_id' => [
                'required',
                'integer',
                Rule::exists('sales', 'id')
                    ->where('organization_id', $organization->id),
            ],
            'reason' => ['required', 'string', 'min:5', 'max:500'],
        ]);

        $sale = $this->saleService->cancel($organization, $user, $validated['sale_id'], $validated['reason']);

        return [
            'sale_id' => $sale->id,
            'number' => $sale->number,
            'status' => $sale->status->value,
            'cancellation_reason' => $sale->cancellation_reason,
            'cancelled_at' => $sale->cancelled_at?->toIso8601String(),
        ];
    }
}
