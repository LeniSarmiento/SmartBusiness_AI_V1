<?php

namespace Tests\Feature\Ai;

use App\Ai\Agents\BusinessAssistantAgent;
use App\Ai\Tools\GetDashboardReportTool;
use App\Models\Organization;
use App\Services\ReportingService;
use App\Services\SaleService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Str;
use Laravel\Ai\Exceptions\AiException;
use Laravel\Ai\Exceptions\ProviderConnectionException;
use Laravel\Ai\Responses\Data\ToolCall;
use Laravel\Ai\Tools\Request;
use Tests\Feature\BusinessTestCase;

class BusinessAssistantAgentTest extends BusinessTestCase
{
    public function test_agent_returns_normal_response_and_delegates_dashboard_query_to_reporting_service(): void
    {
        $product = $this->product(10);

        app(SaleService::class)->create($this->organization, $this->admin, [
            'request_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        BusinessAssistantAgent::fake([
            new ToolCall('call_dashboard_1', 'get_dashboard_report', []),
            'Las ventas del mes suman 23.92 COP con 2 unidades vendidas.',
        ]);

        $agent = BusinessAssistantAgent::make(
            user: $this->admin,
            organization: $this->organization,
        );

        $response = $agent->prompt('¿Cómo van las ventas de este mes?');

        $this->assertSame('Las ventas del mes suman 23.92 COP con 2 unidades vendidas.', $response->text);
        BusinessAssistantAgent::assertPrompted('¿Cómo van las ventas de este mes?');

        $this->assertCount(1, $response->toolCalls);
        $this->assertSame('get_dashboard_report', $response->toolCalls->first()->name);

        $this->assertCount(1, $response->toolResults);
        $payload = json_decode((string) $response->toolResults->first()->result, true, 512, JSON_THROW_ON_ERROR);
        $expected = app(ReportingService::class)->dashboard($this->organization);

        $this->assertSame($expected['currency'], $payload['currency']);
        $this->assertSame($expected['sales_today'], $payload['sales_today']);
        $this->assertSame($expected['sales_month'], $payload['sales_month']);
        $this->assertSame($expected['products_sold_month'], $payload['products_sold_month']);
    }

    public function test_provider_and_model_are_configurable_via_environment_config(): void
    {
        config([
            'ai.assistant.provider' => 'openai',
            'ai.assistant.model' => 'gpt-4o-mini',
        ]);

        BusinessAssistantAgent::fake(['Indicadores consultados correctamente.']);

        $agent = new BusinessAssistantAgent($this->organization, $this->admin);
        $response = $agent->prompt('Resumen ejecutivo');

        $this->assertSame('openai', $agent->provider());
        $this->assertSame('gpt-4o-mini', $agent->model());
        $this->assertSame('openai', $response->meta->provider);
        $this->assertSame('gpt-4o-mini', $response->meta->model);
    }

    public function test_agent_enforces_existing_role_permissions(): void
    {
        $manager = $this->member('manager');
        $seller = $this->member('seller');

        $managerAgent = new BusinessAssistantAgent($this->organization, $manager);
        $this->assertCount(1, [...$managerAgent->tools()]);

        $sellerAgent = new BusinessAssistantAgent($this->organization, $seller);
        $this->assertSame([], [...$sellerAgent->tools()]);

        BusinessAssistantAgent::fake(['No debería responder.']);

        try {
            $sellerAgent->prompt('¿Cuánto vendimos hoy?');
            $this->fail('Se esperaba AuthorizationException para un vendedor sin permiso reports.read.');
        } catch (AuthorizationException) {
            BusinessAssistantAgent::assertNeverPrompted();
        }

        $tool = (new GetDashboardReportTool(app(ReportingService::class)))->forContext($this->organization, $seller);
        $this->expectException(AuthorizationException::class);
        $tool->handle(new Request);
    }

    public function test_agent_enforces_strict_tenant_isolation_between_organizations(): void
    {
        $otherOrganization = Organization::create([
            'name' => 'Empresa Externa',
            'currency' => 'USD',
            'timezone' => 'UTC',
        ]);
        $otherAdmin = $this->member('admin', $otherOrganization);
        $otherProduct = $this->product(10, $otherOrganization);

        app(SaleService::class)->create($otherOrganization, $otherAdmin, [
            'request_key' => (string) Str::uuid(),
            'items' => [
                ['product_id' => $otherProduct->id, 'quantity' => 3],
            ],
        ]);

        BusinessAssistantAgent::fake([
            new ToolCall('call_isolated_1', 'get_dashboard_report', []),
            'Tu organización no registra ventas hoy.',
        ]);

        // Un administrador de la empresa A no puede consultar el asistente sobre la empresa B
        $crossTenantAgent = new BusinessAssistantAgent($otherOrganization, $this->admin);
        $this->assertSame([], [...$crossTenantAgent->tools()]);

        try {
            $crossTenantAgent->prompt('Dame los ingresos de Empresa Externa');
            $this->fail('Se esperaba AuthorizationException al intentar consultar otra organización.');
        } catch (AuthorizationException) {
            BusinessAssistantAgent::assertNeverPrompted();
        }

        // Al consultar su propia organización, los resultados solo contienen datos de su empresa
        $ownAgent = new BusinessAssistantAgent($this->organization, $this->admin);
        $response = $ownAgent->prompt('Dame mis ventas de hoy');

        $payload = json_decode((string) $response->toolResults->first()->result, true, 512, JSON_THROW_ON_ERROR);
        $this->assertSame('COP', $payload['currency']);
        $this->assertSame('0', $payload['sales_today']);
        $this->assertSame(0, $payload['sales_count_month']);
    }

    public function test_agent_handles_provider_failure_and_unconfigured_provider(): void
    {
        BusinessAssistantAgent::fake([
            fn () => throw ProviderConnectionException::forProvider('gemini'),
        ]);

        $agent = new BusinessAssistantAgent($this->organization, $this->admin);

        try {
            $agent->prompt('¿Cuál es el estado del inventario?');
            $this->fail('Se esperaba ProviderConnectionException cuando falla el proveedor.');
        } catch (ProviderConnectionException $e) {
            $this->assertStringContainsString('gemini', $e->getMessage());
        }

        config([
            'ai.default' => '',
            'ai.assistant.provider' => null,
        ]);

        $this->expectException(AiException::class);
        $agent->prompt('Consulta sin proveedor configurado');
    }
}
