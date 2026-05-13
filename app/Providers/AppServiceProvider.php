<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\URL;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // Paksa HTTPS jika dijalankan di production/dokploy
        if (config('app.env') !== 'local') {
            URL::forceScheme('https');
        }

        Blade::directive('money', function ($expression) {
            return "<?php echo format_money($expression); ?>";
        });
    }
}
