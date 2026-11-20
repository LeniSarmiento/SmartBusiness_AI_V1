<?php

use App\Http\Controllers\Api\V1\AiToolController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\CategoryController;
use App\Http\Controllers\Api\V1\CustomerController;
use App\Http\Controllers\Api\V1\DashboardController;
use App\Http\Controllers\Api\V1\InventoryController;
use App\Http\Controllers\Api\V1\InvitationController;
use App\Http\Controllers\Api\V1\MemberController;
use App\Http\Controllers\Api\V1\ProductController;
use App\Http\Controllers\Api\V1\SaleController;
use App\Http\Controllers\Api\V1\SupplierController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->middleware('throttle:api')->group(function () {
    Route::middleware('throttle:login')->group(function () {
        Route::post('auth/register', [AuthController::class, 'register']);
        Route::post('auth/login', [AuthController::class, 'login']);
        Route::post('auth/forgot-password', [AuthController::class, 'forgot']);
        Route::post('auth/reset-password', [AuthController::class, 'reset']);
    });

    Route::get('auth/email/verify/{id}/{hash}', [AuthController::class, 'verifyEmail'])->name('verification.verify');
    Route::get('invitations/{token}', [InvitationController::class, 'show']);
    Route::post('invitations/{token}/accept', [InvitationController::class, 'accept']);

    Route::middleware('auth:sanctum')->group(function () {
        Route::get('auth/me', [AuthController::class, 'me']);
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::patch('auth/profile', [AuthController::class, 'profile']);
        Route::post('auth/change-password', [AuthController::class, 'changePassword']);
        Route::post('auth/email/resend', [AuthController::class, 'resendVerification']);

        Route::prefix('organizations/{organization}')->whereNumber('organization')->middleware('organization')->group(function () {
            Route::get('dashboard', DashboardController::class);
            Route::get('customers/{customer}/sales', [CustomerController::class, 'sales'])->whereNumber('customer');
            Route::get('suppliers/{supplier}/products', [SupplierController::class, 'products'])->whereNumber('supplier');
            Route::apiResource('customers', CustomerController::class)->whereNumber('customer');
            Route::apiResource('suppliers', SupplierController::class)->whereNumber('supplier');
            Route::apiResource('categories', CategoryController::class)->whereNumber('category');
            Route::apiResource('products', ProductController::class)->whereNumber('product');
            Route::get('inventory-movements', [InventoryController::class, 'index']);
            Route::post('inventory-movements', [InventoryController::class, 'store']);
            Route::apiResource('sales', SaleController::class)->only('index', 'show', 'store')->whereNumber('sale');
            Route::post('sales/{sale}/cancel', [SaleController::class, 'cancel'])->whereNumber('sale');
            Route::get('members', [MemberController::class, 'index']);
            Route::post('members', [MemberController::class, 'store']);
            Route::patch('members/{member}', [MemberController::class, 'update'])->whereNumber('member');
            Route::delete('members/{member}', [MemberController::class, 'destroy'])->whereNumber('member');
            Route::get('invitations', [InvitationController::class, 'index']);
            Route::post('invitations', [InvitationController::class, 'store']);
            Route::delete('invitations/{invitation}', [InvitationController::class, 'destroy'])->whereNumber('invitation');
            Route::get('ai/tools', [AiToolController::class, 'index']);
            Route::post('ai/tools/execute', [AiToolController::class, 'execute']);
        });
    });
});
