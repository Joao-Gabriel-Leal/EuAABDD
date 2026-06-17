<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;

$providers = [
    AppServiceProvider::class,
];

if ((bool) env('AABB_MODULE_FILAMENT', false)) {
    $providers[] = AdminPanelProvider::class;
}

return $providers;
