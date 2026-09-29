<?php

use App\Models\Organization;
use App\Models\User;
use App\Services\SaleService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

require __DIR__.'/../../vendor/autoload.php';
$app = require __DIR__.'/../../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! str_ends_with(config('database.connections.pgsql.database'), '_test')) {
    throw new RuntimeException('Concurrency checks require an isolated _test database.');
}
try {
    app(SaleService::class)->create(
        Organization::findOrFail((int) $argv[1]),
        User::findOrFail((int) $argv[2]),
        ['request_key' => (string) Str::uuid(), 'items' => [['product_id' => (int) $argv[3], 'quantity' => 1]]]
    );
    echo 'sold';
} catch (ValidationException $e) {
    echo 'out-of-stock';
}
