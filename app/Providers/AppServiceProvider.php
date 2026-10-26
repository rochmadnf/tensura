<?php

namespace App\Providers;

use App\Services\Letter\DocumentFontRegistry;
use Illuminate\Foundation\DevCommands;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->scoped(DocumentFontRegistry::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        if ($this->app->isLocal()) {
            DevCommands::artisan('queue:work letters --queue=letters --tries=3 --timeout=90', 'letters');
            DevCommands::artisan('schedule:work', 'scheduler');
            if (PHP_OS_FAMILY === 'Linux') {
                $port = (int) (parse_url(config('app.url'), PHP_URL_PORT) ?: 8000);
                DevCommands::register('PHP_CLI_SERVER_WORKERS=8 php artisan serve --no-reload --port=' . $port, 'server');
            }
        }
    }
}
