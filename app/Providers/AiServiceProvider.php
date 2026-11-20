<?php

namespace App\Providers;

use App\Ai\ToolExecutor;
use App\Ai\ToolRegistry;
use App\Ai\Tools\AdjustInventoryTool;
use App\Ai\Tools\CancelSaleTool;
use App\Ai\Tools\CreateSaleTool;
use App\Ai\Tools\GetDashboardReportTool;
use Illuminate\Support\ServiceProvider;

class AiServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ToolRegistry::class, function ($app) {
            $registry = new ToolRegistry;

            $registry->register($app->make(AdjustInventoryTool::class));
            $registry->register($app->make(CreateSaleTool::class));
            $registry->register($app->make(CancelSaleTool::class));
            $registry->register($app->make(GetDashboardReportTool::class));

            return $registry;
        });

        $this->app->singleton(ToolExecutor::class, function ($app) {
            return new ToolExecutor($app->make(ToolRegistry::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
