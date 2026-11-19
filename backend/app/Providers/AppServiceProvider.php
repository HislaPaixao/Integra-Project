<?php

namespace App\Providers;

use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // As respostas da API vêm "puras" (sem o envelope {"data": ...} do Laravel).
        JsonResource::withoutWrapping();
    }
}
