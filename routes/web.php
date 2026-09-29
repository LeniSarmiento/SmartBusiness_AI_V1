<?php

use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return response()->json(['application' => 'SmartBusiness', 'version' => '1.0.0', 'api' => '/api/v1', 'frontend' => config('app.frontend_url')]);
});
