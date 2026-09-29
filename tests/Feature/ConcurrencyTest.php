<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Product;
use App\Models\Role;
use App\Models\Sale;
use App\Models\User;
use App\Services\InventoryService;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Symfony\Component\Process\Process;
use Tests\TestCase;

class ConcurrencyTest extends TestCase
{
    use DatabaseMigrations;

    public function test_two_processes_cannot_sell_the_last_unit_twice(): void
    {
        $this->seed(RolePermissionSeeder::class);
        $organization = Organization::create(['name' => 'Concurrent company']);
        $user = User::factory()->create();
        OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => Role::where('name', 'admin')->firstOrFail()->id]);
        $product = Product::create(['organization_id' => $organization->id, 'name' => 'Last unit', 'sku' => 'LAST', 'purchase_price' => '1.00', 'sale_price' => '2.00']);
        app(InventoryService::class)->adjust($organization, $user, ['product_id' => $product->id, 'type' => 'entry', 'quantity' => 1, 'reason' => 'One available unit']);
        $command = [PHP_BINARY];
        if (PHP_OS_FAMILY === 'Windows') {
            $command = [...$command, '-d', 'extension=pdo_pgsql'];
        }
        $command = [...$command, base_path('tests/Support/attempt-sale.php'), (string) $organization->id, (string) $user->id, (string) $product->id];
        $connection = config('database.connections.pgsql');
        $env = ['APP_ENV' => 'testing', 'DB_CONNECTION' => 'pgsql', 'DB_DATABASE' => 'smartbusiness_test', 'DB_HOST' => $connection['host'], 'DB_PORT' => (string) $connection['port'], 'DB_USERNAME' => $connection['username'], 'DB_PASSWORD' => $connection['password'], 'QUEUE_CONNECTION' => 'sync'];
        $first = new Process($command, base_path(), $env);
        $second = new Process($command, base_path(), $env);
        $first->start();
        $second->start();
        $first->wait();
        $second->wait();
        $this->assertTrue($first->isSuccessful(), $first->getErrorOutput());
        $this->assertTrue($second->isSuccessful(), $second->getErrorOutput());
        $results = [trim($first->getOutput()), trim($second->getOutput())];
        sort($results);
        $this->assertSame(['out-of-stock', 'sold'], $results);
        $this->assertSame(0, $product->refresh()->current_stock);
        $this->assertSame(1, Sale::count());
    }
}
