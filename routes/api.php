<?php

use App\Http\Controllers\Api\V1\AdjustmentReasonController;
use App\Http\Controllers\Api\V1\AiToolController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\ReportController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SecurityAuditController;
use App\Http\Controllers\Api\V1\StockAlertController;
use App\Http\Controllers\Api\V1\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::post('auth/register', [AuthController::class, 'register'])->middleware('throttle:registration');
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');
    Route::post('auth/forgot-password', [AuthController::class, 'forgot'])->middleware('throttle:password-recovery');
    Route::post('auth/reset-password', [AuthController::class, 'reset'])->middleware('throttle:password-recovery');

    Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->middleware(['signed', 'throttle:email-verification'])->name('verification.verify');
    Route::get('invitations/{token}', [InvitationController::class, 'show'])->middleware('throttle:invitation-inspect');
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept'])->middleware('throttle:invitation-accept');

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::patch('auth/profile', [AuthController::class, 'profile'])->middleware('throttle:sensitive');
        Route::post('auth/change-password', [AuthController::class, 'changePassword'])->middleware('throttle:sensitive');
        Route::post('auth/email/resend', [AuthController::class, 'resendVerification'])->middleware('throttle:email-verification');

        Route::prefix('organizations/{organization}')->whereNumber('organization')->middleware('organization')->group(function () {
            Route::get('dashboard', DashboardController::class);
            Route::get('customers/{customer}/sales', [CustomerController::class, 'sales'])->whereNumber('customer');
            Route::get('suppliers/{supplier}/products', [SupplierController::class, 'products'])->whereNumber('supplier');
            Route::apiResource('customers', CustomerController::class)->whereNumber('customer')->middlewareFor(['store', 'update', 'destroy'], 'throttle:business-write');
            Route::apiResource('suppliers', SupplierController::class)->whereNumber('supplier')->middlewareFor(['store', 'update', 'destroy'], 'throttle:business-write');
            Route::apiResource('categories', CategoryController::class)->whereNumber('category')->middlewareFor(['store', 'update', 'destroy'], 'throttle:business-write');
            Route::apiResource('products', ProductController::class)->whereNumber('product')->middlewareFor(['store', 'update', 'destroy'], 'throttle:business-write');
            Route::get('inventory-movements/export', [InventoryController::class, 'export']);
            Route::get('inventory-movements', [InventoryController::class, 'index']);
            Route::post('inventory-movements', [InventoryController::class, 'store'])->middleware('throttle:business-write');
            Route::apiResource('adjustment-reasons', AdjustmentReasonController::class)->whereNumber('adjustment_reason')->middlewareFor(['store', 'update', 'destroy'], 'throttle:business-write');
            Route::get('stock-alerts', [StockAlertController::class, 'index']);
            Route::get('reports/inventory-valuation', [ReportController::class, 'inventoryValuation']);
            Route::apiResource('sales', SaleController::class)->only('index', 'show', 'store')->whereNumber('sale')->middlewareFor('store', 'throttle:business-write');
            Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->whereNumber('sale')->middleware('throttle:business-write');
            Route::get('members', [MemberController::class, 'index']);
            Route::get('security-audit-logs', [SecurityAuditController::class, 'index']);
            Route::post('members', [MemberController::class, 'store'])->middleware('throttle:sensitive');
            Route::patch('members/{member}', [MemberController::class, 'update'])->whereNumber('member')->middleware('throttle:sensitive');
            Route::delete('members/{member}', [MemberController::class, 'destroy'])->whereNumber('member')->middleware('throttle:sensitive');
            Route::get('invitations', [InvitationController::class, 'index']);
            Route::post('invitations', [InvitationController::class, 'store'])->middleware('throttle:sensitive');
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->whereNumber('invitation')->middleware('throttle:sensitive');
            Route::get('ai/tools', [AiToolController::class, 'index']);
            Route::post('ai/tools/execute', [AiToolController::class, 'execute'])->middleware('throttle:ai-tools');
        });
    });
});
