<?php

namespace App\Ai\Tools;

use App\Models\Organization;
use App\Models\User;
use App\Services\ReportingService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Ai\Contracts\Tool as SdkTool;
use Laravel\Ai\Tools\Request;
use Stringable;

class GetDashboardReportTool extends BaseTool implements SdkTool
{
    public function __construct(
        private ReportingService $reportingService,
        private ?Organization $organization = null,
        private ?User $user = null,
    ) {}

    public function forContext(Organization $organization, User $user): self
    {
        return new self($this->reportingService, $organization, $user);
    }

    public function name(): string
    {
        return $this->getName();
    }

    public function getName(): string
    {
        return 'get_dashboard_report';
    }

    public function description(): Stringable|string
    {
        return $this->getDescription();
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

    public function schema(JsonSchema $schema): array
    {
        return [];
    }

    public function handle(Request $request): Stringable|string
    {
        if (! $this->organization || ! $this->user) {
            throw new AuthorizationException('Se requiere un usuario y una organización válidos para consultar el dashboard.');
        }

        $report = $this->execute($this->organization, $this->user, $request->all());

        return json_encode($report, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    }

    public function execute(Organization $organization, User $user, array $arguments = []): array
    {
        $this->authorize($organization, $user);

        return $this->reportingService->dashboard($organization);
    }
}
