<?php

namespace App\Ai\Tools;

use App\Models\Organization;
use App\Models\User;
use App\Services\ReportingService;

class GetDashboardReportTool extends BaseTool
{
    public function __construct(private ReportingService $reportingService) {}

    public function getName(): string
    {
        return 'get_dashboard_report';
    }

    public function getDescription(): string
    {
        return 'Obtiene las métricas y KPIs clave de la organización: ventas de hoy, del mes, productos con stock bajo, productos más vendidos y tendencias.';
    }

    public function getRequiredPermission(): string
    {
        return 'reports.read';
    }

    public function getParameters(): array
    {
        return [
            'type' => 'object',
            'properties' => (object) [],
        ];
    }

    public function execute(Organization $organization, User $user, array $arguments): array
    {
        $this->authorize($organization, $user);

        return $this->reportingService->dashboard($organization);
    }
}
