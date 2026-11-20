<?php

namespace Tests\Feature\Ai;

use App\Ai\ToolExecutor;
use App\Ai\ToolRegistry;
use App\Models\Organization;
use App\Models\Sale;
use Tests\Feature\BusinessTestCase;

class ToolCallingTest extends BusinessTestCase
{
    private ToolRegistry $registry;

    private ToolExecutor $executor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->registry = app(ToolRegistry::class);
        $this->executor = app(ToolExecutor::class);
    }

    public function test_registry_registers_all_service_tools(): void
    {
        $this->assertNotNull($this->registry->get('adjust_inventory'));
        $this->assertNotNull($this->registry->get('create_sale'));
        $this->assertNotNull($this->registry->get('cancel_sale'));
        $this->assertNotNull($this->registry->get('get_dashboard_report'));

        $tools = $this->registry->all();
        $this->assertCount(4, $tools);

        $schemas = $this->registry->toFunctionDeclarations();
        $this->assertCount(4, $schemas);
        $this->assertSame('function', $schemas[0]['type']);
        $this->assertNotEmpty($schemas[0]['function']['name']);
    }

    public function test_tools_are_filtered_strictly_by_role_permissions(): void
    {
        $manager = $this->member('manager');
        $seller = $this->member('seller');

        // Manager has permissions: inventory.write, sales.write, sales.cancel, reports.read
        $managerTools = $this->registry->forUser($this->organization, $manager);
        $this->assertCount(4, $managerTools);

        // Seller only has sales.write; lacks inventory.write, sales.cancel, reports.read
        $sellerTools = $this->registry->forUser($this->organization, $seller);
        $this->assertCount(1, $sellerTools);
        $this->assertSame('create_sale', $sellerTools[0]->getName());

        $sellerSchemas = $this->registry->toFunctionDeclarations($this->organization, $seller);
        $this->assertCount(1, $sellerSchemas);
        $this->assertSame('create_sale', $sellerSchemas[0]['function']['name']);
    }

    public function test_seller_cannot_execute_unauthorized_tools(): void
    {
        $seller = $this->member('seller');
        $product = $this->product(10);

        // Attempt adjust_inventory
        $result = $this->executor->execute($this->organization, $seller, 'adjust_inventory', [
            'product_id' => $product->id,
            'type' => 'entry',
            'quantity' => 5,
            'reason' => 'Intento no autorizado',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('FORBIDDEN', $result['code']);

        // Attempt cancel_sale
        $result = $this->executor->execute($this->organization, $seller, 'cancel_sale', [
            'sale_id' => 1,
            'reason' => 'Intento no autorizado',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('FORBIDDEN', $result['code']);

        // Attempt get_dashboard_report
        $result = $this->executor->execute($this->organization, $seller, 'get_dashboard_report', []);
        $this->assertFalse($result['success']);
        $this->assertSame('FORBIDDEN', $result['code']);
    }

    public function test_admin_can_adjust_inventory_via_tool(): void
    {
        $product = $this->product(10);

        $result = $this->executor->execute($this->organization, $this->admin, 'adjust_inventory', [
            'product_id' => $product->id,
            'type' => 'entry',
            'quantity' => 5,
            'reason' => 'Llegada de reposición por proveedor',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame(15, $result['data']['balance_after']);
        $this->assertSame(15, $product->refresh()->current_stock);
        $this->assertDatabaseHas('inventory_movements', [
            'product_id' => $product->id,
            'quantity_delta' => 5,
            'balance_after' => 15,
            'reason' => 'Llegada de reposición por proveedor',
        ]);
    }

    public function test_inventory_tool_enforces_tenant_isolation(): void
    {
        $otherOrg = Organization::create(['name' => 'Empresa Rival']);
        $otherProduct = $this->product(10, $otherOrg);

        $result = $this->executor->execute($this->organization, $this->admin, 'adjust_inventory', [
            'product_id' => $otherProduct->id,
            'type' => 'entry',
            'quantity' => 5,
            'reason' => 'Intento cross-tenant',
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('VALIDATION_ERROR', $result['code']);
        $this->assertSame(10, $otherProduct->refresh()->current_stock);
    }

    public function test_seller_can_create_sale_and_inventory_is_decremented(): void
    {
        $seller = $this->member('seller');
        $product = $this->product(10);

        $result = $this->executor->execute($this->organization, $seller, 'create_sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 3],
            ],
            'notes' => 'Venta asistida por IA',
        ]);

        $this->assertTrue($result['success']);
        $this->assertSame('completed', $result['data']['status']);
        $this->assertSame('30.15', $result['data']['subtotal']);
        $this->assertSame(7, $product->refresh()->current_stock);
        $this->assertDatabaseHas('sales', [
            'id' => $result['data']['sale_id'],
            'organization_id' => $this->organization->id,
            'seller_id' => $seller->id,
        ]);
    }

    public function test_create_sale_tool_rejects_insufficient_stock(): void
    {
        $seller = $this->member('seller');
        $product = $this->product(2);

        $result = $this->executor->execute($this->organization, $seller, 'create_sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 5],
            ],
        ]);

        $this->assertFalse($result['success']);
        $this->assertSame('VALIDATION_ERROR', $result['code']);
        $this->assertSame(2, $product->refresh()->current_stock);
        $this->assertDatabaseCount('sales', 0);
    }

    public function test_manager_can_cancel_sale_and_reintegrate_stock(): void
    {
        $manager = $this->member('manager');
        $product = $this->product(10);

        // First make a sale
        $saleResult = $this->executor->execute($this->organization, $manager, 'create_sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 4],
            ],
        ]);
        $this->assertTrue($saleResult['success']);
        $this->assertSame(6, $product->refresh()->current_stock);

        // Now cancel it
        $cancelResult = $this->executor->execute($this->organization, $manager, 'cancel_sale', [
            'sale_id' => $saleResult['data']['sale_id'],
            'reason' => 'Cliente canceló el pedido antes de la entrega',
        ]);

        $this->assertTrue($cancelResult['success']);
        $this->assertSame('cancelled', $cancelResult['data']['status']);
        $this->assertSame(10, $product->refresh()->current_stock);
        $this->assertDatabaseHas('sales', [
            'id' => $saleResult['data']['sale_id'],
            'status' => 'cancelled',
            'cancellation_reason' => 'Cliente canceló el pedido antes de la entrega',
        ]);
    }

    public function test_get_dashboard_report_tool_returns_metrics(): void
    {
        $manager = $this->member('manager');
        $product = $this->product(10);

        $this->executor->execute($this->organization, $manager, 'create_sale', [
            'items' => [
                ['product_id' => $product->id, 'quantity' => 2],
            ],
        ]);

        $result = $this->executor->execute($this->organization, $manager, 'get_dashboard_report', []);

        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('sales_today', $result['data']);
        $this->assertArrayHasKey('sales_month', $result['data']);
        $this->assertArrayHasKey('top_products', $result['data']);
        $this->organization->refresh();
        $this->assertSame('COP', $result['data']['currency']);
    }

    public function test_api_endpoints_work_with_authentication_and_permissions(): void
    {
        $seller = $this->member('seller');
        $product = $this->product(5);

        // GET /api/v1/organizations/{org}/ai/tools
        $response = $this->actingAs($seller)
            ->getJson($this->endpoint('ai/tools'))
            ->assertOk();

        // Seller only gets create_sale
        $tools = $response->json('data.tools');
        $this->assertCount(1, $tools);
        $this->assertSame('create_sale', $tools[0]['function']['name']);

        // POST /api/v1/organizations/{org}/ai/tools/execute -> create_sale succeeds
        $execResponse = $this->actingAs($seller)
            ->postJson($this->endpoint('ai/tools/execute'), [
                'tool' => 'create_sale',
                'arguments' => [
                    'items' => [
                        ['product_id' => $product->id, 'quantity' => 1],
                    ],
                ],
            ])
            ->assertOk();

        $this->assertTrue($execResponse->json('success'));
        $this->assertSame(4, $product->refresh()->current_stock);

        // POST /api/v1/organizations/{org}/ai/tools/execute -> adjust_inventory forbidden for seller
        $forbiddenResponse = $this->actingAs($seller)
            ->postJson($this->endpoint('ai/tools/execute'), [
                'tool' => 'adjust_inventory',
                'arguments' => [
                    'product_id' => $product->id,
                    'type' => 'entry',
                    'quantity' => 10,
                    'reason' => 'Intento de vendedor',
                ],
            ])
            ->assertStatus(403);

        $this->assertFalse($forbiddenResponse->json('success'));
    }
}
