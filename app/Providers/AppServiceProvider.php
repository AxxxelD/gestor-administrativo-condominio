<?php

namespace App\Providers;

use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Registra los servicios de la aplicación.
     */
    public function register(): void
    {
        //
    }

    /**
     * Inicializa los servicios de la aplicación.
     */
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            DevCommands::withoutDefaultCommands();
            DevCommands::register('node iniciar-desarrollo.mjs', 'condominio');
        }
    }
}
