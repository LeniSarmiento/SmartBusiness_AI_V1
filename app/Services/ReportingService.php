<?php

namespace App\Services;

use App\Enums\SaleStatus;
use App\Models\Customer;
use App\Models\Organization;
use App\Models\Product;
use App\Models\Sale;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

class ReportingService
{
    public function dashboard(Organization $organization): array
    {
        $now = CarbonImmutable::now($organization->timezone);
        $monthStart = $now->startOfMonth()->utc();
        $nextMonth = $now->startOfMonth()->addMonth()->utc();
        $base = Sale::forOrganization($organization)->where('status', SaleStatus::Completed);
        $month = (clone $base)->where('sold_at', '>=', $monthStart)->where('sold_at', '<', $nextMonth);
        $lowStock = Product::forOrganization($organization)->where('active', true)->whereColumn('current_stock', '<=', 'minimum_stock');
        $daily = (clone $base)->where('sold_at', '>=', $now->subDays(29)->startOfDay()->utc())
            ->selectRaw('DATE(sold_at AT TIME ZONE ?) AS day, SUM(total) AS total', [$organization->timezone])
            ->groupBy('day')->pluck('total', 'day');
        $trend = [];
        for ($i = 29; $i >= 0; $i--) {
            $day = $now->subDays($i)->toDateString();
            $trend[] = ['day' => $day, 'total' => (string) ($daily[$day] ?? '0.00')];
        }
        $items = DB::table('sale_items')->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.organization_id', $organization->id)->where('sales.status', 'completed')
            ->where('sales.sold_at', '>=', $monthStart)->where('sales.sold_at', '<', $nextMonth);

        return [
            'currency' => $organization->currency, 'timezone' => $organization->timezone,
            'sales_today' => (string) (clone $base)->where('sold_at', '>=', $now->startOfDay()->utc())->where('sold_at', '<', $now->addDay()->startOfDay()->utc())->sum('total'),
            'sales_month' => (string) (clone $month)->sum('total'),
            'sales_count_month' => (clone $month)->count(),
            'customers_count' => Customer::forOrganization($organization)->count(),
            'products_sold_month' => (int) (clone $items)->sum('sale_items.quantity'),
            'low_stock_count' => (clone $lowStock)->count(),
            'low_stock_products' => $lowStock->orderBy('current_stock')->limit(5)->get(['id', 'name', 'sku', 'current_stock', 'minimum_stock']),
            'trend' => $trend,
            'top_products' => (clone $items)->selectRaw('sale_items.product_id, MAX(sale_items.product_name) AS name, SUM(sale_items.quantity) AS quantity, SUM(sale_items.total) AS total')->groupBy('sale_items.product_id')->orderByDesc('quantity')->limit(5)->get(),
            'top_customers' => (clone $month)->whereNotNull('customer_id')->selectRaw('customer_id, MAX(customer_name) AS name, SUM(total) AS total')->groupBy('customer_id')->orderByDesc('total')->limit(5)->get(),
            'recent_sales' => Sale::forOrganization($organization)->with('seller')->latest('id')->limit(5)->get(),
        ];
    }
}
