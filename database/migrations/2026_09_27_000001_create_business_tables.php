<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('organizations', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->char('currency', 3)->default('COP');
            $table->string('timezone')->default('America/Bogota');
            $table->unsignedBigInteger('next_sale_number')->default(1);
            $table->timestamps();
        });
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });
        Schema::create('permissions', function (Blueprint $table) {
            $table->id();
            $table->string('name')->unique();
        });
        Schema::create('permission_role', function (Blueprint $table) {
            $table->foreignId('permission_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->cascadeOnDelete();
            $table->primary(['permission_id', 'role_id']);
        });
        Schema::create('organization_memberships', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->timestamps();
            $table->unique(['organization_id', 'user_id']);
            $table->index('user_id');
        });
        foreach (['customers', 'suppliers'] as $name) {
            Schema::create($name, function (Blueprint $table) {
                $table->id();
                $table->foreignId('organization_id')->constrained()->restrictOnDelete();
                $table->string('name');
                $table->string('identification', 50)->nullable();
                $table->string('email')->nullable();
                $table->string('phone', 40)->nullable();
                $table->string('address', 500)->nullable();
                $table->boolean('active')->default(true);
                $table->timestamps();
                $table->softDeletes();
                $table->unique(['organization_id', 'id']);
                $table->index(['organization_id', 'name']);
            });
        }
        Schema::create('categories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->string('name');
            $table->string('description', 500)->nullable();
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'name']);
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('category_id')->nullable();
            $table->unsignedBigInteger('supplier_id')->nullable();
            $table->string('name');
            $table->string('sku', 80);
            $table->string('code', 80)->nullable();
            $table->text('description')->nullable();
            $table->decimal('purchase_price', 12, 2)->default(0);
            $table->decimal('sale_price', 12, 2);
            $table->unsignedInteger('tax_rate_bps')->default(0);
            $table->integer('current_stock')->default(0);
            $table->integer('minimum_stock')->default(0);
            $table->boolean('active')->default(true);
            $table->timestamps();
            $table->softDeletes();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'sku']);
            $table->unique(['organization_id', 'code']);
            $table->index(['organization_id', 'name']);
            $table->foreign(['organization_id', 'category_id'])->references(['organization_id', 'id'])->on('categories')->restrictOnDelete();
            $table->foreign(['organization_id', 'supplier_id'])->references(['organization_id', 'id'])->on('suppliers')->restrictOnDelete();
        });
        Schema::create('sales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->restrictOnDelete();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('seller_id');
            $table->unsignedBigInteger('number');
            $table->uuid('request_key');
            $table->char('payload_hash', 64);
            $table->string('status', 20)->default('completed');
            $table->char('currency', 3);
            $table->decimal('subtotal', 16, 2);
            $table->decimal('tax', 16, 2);
            $table->decimal('total', 16, 2);
            $table->string('customer_name')->nullable();
            $table->string('notes', 1000)->nullable();
            $table->timestampTz('sold_at');
            $table->timestampTz('cancelled_at')->nullable();
            $table->foreignId('cancelled_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->string('cancellation_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['organization_id', 'id']);
            $table->unique(['organization_id', 'number']);
            $table->unique(['organization_id', 'request_key']);
            $table->index(['organization_id', 'status', 'sold_at']);
            $table->foreign(['organization_id', 'customer_id'])->references(['organization_id', 'id'])->on('customers')->restrictOnDelete();
            $table->foreign(['organization_id', 'seller_id'])->references(['organization_id', 'user_id'])->on('organization_memberships')->restrictOnDelete();
        });
        Schema::create('sale_items', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('sale_id');
            $table->unsignedBigInteger('product_id');
            $table->string('product_name');
            $table->string('product_sku', 80);
            $table->integer('quantity');
            $table->decimal('unit_price', 12, 2);
            $table->unsignedInteger('tax_rate_bps');
            $table->decimal('subtotal', 16, 2);
            $table->decimal('tax', 16, 2);
            $table->decimal('total', 16, 2);
            $table->unique(['sale_id', 'product_id']);
            $table->foreign(['organization_id', 'sale_id'])->references(['organization_id', 'id'])->on('sales')->restrictOnDelete();
            $table->foreign(['organization_id', 'product_id'])->references(['organization_id', 'id'])->on('products')->restrictOnDelete();
        });
        Schema::create('inventory_movements', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('organization_id');
            $table->unsignedBigInteger('product_id');
            $table->unsignedBigInteger('user_id');
            $table->unsignedBigInteger('sale_id')->nullable();
            $table->string('type', 20);
            $table->integer('quantity_delta');
            $table->integer('balance_after');
            $table->string('reason', 500);
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['organization_id', 'product_id', 'created_at']);
            $table->foreign(['organization_id', 'product_id'])->references(['organization_id', 'id'])->on('products')->restrictOnDelete();
            $table->foreign(['organization_id', 'user_id'])->references(['organization_id', 'user_id'])->on('organization_memberships')->restrictOnDelete();
            $table->foreign(['organization_id', 'sale_id'])->references(['organization_id', 'id'])->on('sales')->restrictOnDelete();
        });

        // These checks are enforced by PostgreSQL, even outside HTTP validation.
        DB::statement('ALTER TABLE products ADD CONSTRAINT products_valid_values CHECK (current_stock >= 0 AND minimum_stock >= 0 AND purchase_price >= 0 AND sale_price >= 0 AND tax_rate_bps BETWEEN 0 AND 10000)');
        DB::statement("ALTER TABLE sales ADD CONSTRAINT sales_valid_values CHECK (subtotal >= 0 AND tax >= 0 AND total = subtotal + tax AND status IN ('completed', 'cancelled'))");
        DB::statement('ALTER TABLE sale_items ADD CONSTRAINT sale_items_valid_values CHECK (quantity > 0 AND unit_price >= 0 AND subtotal >= 0 AND tax >= 0 AND total = subtotal + tax AND tax_rate_bps BETWEEN 0 AND 10000)');
        DB::statement("ALTER TABLE inventory_movements ADD CONSTRAINT movements_valid_values CHECK (quantity_delta <> 0 AND balance_after >= 0 AND type IN ('entry', 'exit', 'adjustment', 'sale', 'cancellation'))");
    }

    public function down(): void
    {
        foreach (['inventory_movements', 'sale_items', 'sales', 'products', 'categories', 'suppliers', 'customers', 'organization_memberships', 'permission_role', 'permissions', 'roles', 'organizations'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
