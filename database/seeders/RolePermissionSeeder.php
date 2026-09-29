<?php

namespace Database\Seeders;

use App\Models\Permission;
use App\Models\Role;
use Illuminate\Database\Seeder;

class RolePermissionSeeder extends Seeder
{
    public function run(): void
    {
        $seller = ['customers.read', 'customers.write', 'products.read', 'sales.read', 'sales.write'];
        $manager = [...$seller, 'suppliers.read', 'suppliers.write', 'categories.read', 'categories.write', 'products.write', 'inventory.read', 'inventory.write', 'sales.cancel', 'reports.read'];
        $admin = [...$manager, 'members.manage'];
        foreach ($admin as $name) {
            Permission::firstOrCreate(['name' => $name]);
        }
        foreach (['admin' => $admin, 'manager' => $manager, 'seller' => $seller] as $roleName => $names) {
            Role::firstOrCreate(['name' => $roleName])->permissions()->sync(Permission::whereIn('name', $names)->pluck('id'));
        }
    }
}
