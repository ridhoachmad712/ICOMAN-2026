<?php

use App\Providers\AppServiceProvider;
use App\Providers\Filament\AdminPanelProvider;
use App\Providers\Filament\AuthorPanelProvider;

return [
    AppServiceProvider::class,
    AdminPanelProvider::class,
    AuthorPanelProvider::class,
];
