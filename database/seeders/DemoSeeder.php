<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\OrganizationMembership;
use App\Models\Product;
use App\Models\Role;
use App\Models\Supplier;
use App\Models\User;
use App\Services\InventoryService;
use App\Services\SaleService;
use Carbon\Carbon;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            throw new \RuntimeException('Demo only allowed in local/testing.');
        }
        $this->call(RolePermissionSeeder::class);
        if (User::where('email', 'demo@smartbusiness.test')->exists()) {
            $this->command?->info('La empresa demo ya existe; no se modifica.');

            return;
        }
        DB::transaction(function () {
            $organization = Organization::create(['name' => 'La Oficina · Demo', 'currency' => 'COP', 'timezone' => 'America/Bogota']);
            $users = [];
            foreach (['admin' => ['Laura Martínez', 'demo'], 'manager' => ['Carlos Ríos', 'gerente'], 'seller' => ['Sofía Torres', 'vendedor']] as $role => [$name,$email]) {
                $user = User::create(['name' => $name, 'email' => $email.'@smartbusiness.test', 'password' => 'SmartBusiness2026!']);
                OrganizationMembership::create(['organization_id' => $organization->id, 'user_id' => $user->id, 'role_id' => Role::where('name', $role)->firstOrFail()->id]);
                $users[$role] = $user;
            }
            $categories = collect(['Tecnología', 'Accesorios', 'Audio', 'Escritorio'])->map(fn ($name) => Category::create(['organization_id' => $organization->id, 'name' => $name]));
            $suppliers = collect(['TecnoDistribuciones', 'Accesorios del Norte', 'Soluciones Office'])->map(fn ($name, $i) => Supplier::create(['organization_id' => $organization->id, 'name' => $name, 'email' => 'contacto'.$i.'@proveedor.example', 'phone' => '300 555 00'.($i + 10)]));
            $customers = collect(['Estudio Creativo Norte', 'Ana Ramírez', 'Punto Digital SAS', 'Andrés Molina', 'Café Horizonte', 'Valentina Ruiz', 'Grupo Nova', 'Camila Herrera'])->map(fn ($name, $i) => Customer::create(['organization_id' => $organization->id, 'name' => $name, 'email' => 'cliente'.($i + 1).'@example.com', 'phone' => '310 555 00'.($i + 10), 'identification' => '900'.str_pad((string) ($i + 1), 6, '0', STR_PAD_LEFT)]));
            $catalog = [
                ['Teclado inalámbrico', 'TEC-001', '145000.00', '89000.00', 1],
                ['Mouse ergonómico', 'MOU-001', '85000.00', '42000.00', 1],
                ['Audífonos Bluetooth', 'AUD-001', '189000.00', '115000.00', 2],
                ['Soporte para portátil', 'SOP-001', '79000.00', '38000.00', 3],
                ['Hub USB-C 7 en 1', 'HUB-001', '135000.00', '82000.00', 0],
                ['Monitor 24 pulgadas', 'MON-001', '690000.00', '450000.00', 0],
                ['Webcam Full HD', 'CAM-001', '210000.00', '140000.00', 0],
                ['Agenda semanal', 'AGE-001', '35000.00', '17000.00', 3],
                ['Cable USB-C 2 metros', 'CAB-001', '29000.00', '11000.00', 1],
                ['Lámpara de escritorio', 'LAM-001', '99000.00', '58000.00', 3],
                ['Parlante portátil', 'PAR-001', '155000.00', '90000.00', 2],
                ['Mousepad de escritorio', 'PAD-001', '49000.00', '23000.00', 1],
            ];
            $inventory = app(InventoryService::class);
            $products = collect($catalog)->map(function ($entry, $i) use ($organization, $categories, $suppliers, $users, $inventory) {
                [$name,$sku,$price,$cost,$category] = $entry;
                $product = Product::create(['organization_id' => $organization->id, 'name' => $name, 'sku' => $sku, 'sale_price' => $price, 'purchase_price' => $cost, 'tax_rate_bps' => 1900, 'minimum_stock' => 10, 'category_id' => $categories[$category]->id, 'supplier_id' => $suppliers[$i % 3]->id]);
                $opening = $inventory->adjust($organization, $users['admin'], ['product_id' => $product->id, 'type' => 'entry', 'quantity' => 150, 'reason' => 'Inventario inicial de demostración']);
                $opening->update(['created_at' => now()->subDays(30)]);

                return $product;
            });
            $now = Carbon::now('America/Bogota');
            try {
                for ($i = 0; $i < 68; $i++) {
                    $time = $now->copy()->subDays(29 - intdiv($i * 30, 68))->setTime(9 + $i % 7, 10 + $i % 40);
                    Carbon::setTestNow($time->min($now->copy()->subMinute()));
                    app(SaleService::class)->create($organization, $users[$i % 2 ? 'seller' : 'admin'], [
                        'customer_id' => $customers[$i % 8]->id, 'request_key' => (string) Str::uuid(), 'notes' => 'Venta de demostración',
                        'items' => [['product_id' => $products[$i % 12]->id, 'quantity' => 1 + $i % 4], ['product_id' => $products[($i + 3) % 12]->id, 'quantity' => 1 + $i % 2]],
                    ]);
                }
            } finally {
                Carbon::setTestNow();
            }
            foreach ([2 => 4, 4 => 7, 8 => 3] as $index => $stock) {
                $product = $products[$index]->refresh();
                $inventory->adjust($organization, $users['admin'], ['product_id' => $product->id, 'type' => 'adjustment', 'direction' => 'decrease', 'quantity' => $product->current_stock - $stock, 'reason' => 'Ajuste de demostración para alerta de stock bajo']);
            }
        });
    }
}
