<?php

use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    Tymon\JWTAuth\Providers\LaravelServiceProvider::class,
    Spatie\Permission\PermissionServiceProvider::class,
];

