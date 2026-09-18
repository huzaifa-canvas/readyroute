<?php

namespace App\Providers;

use App\Services\Distance\DistanceProvider;
use App\Services\Distance\HaversineProvider;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Vite;
use Illuminate\Pagination\Paginator;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Distance and ETA run behind an interface so the straight-line
        // estimate used today can be swapped for Google Directions by changing
        // config/readyroute.php alone.
        $this->app->singleton(DistanceProvider::class, function ($app) {
            $config = $app['config']->get('readyroute.distance');

            return match ($config['provider']) {
                default => new HaversineProvider(
                    (float) $config['road_factor'],
                    (float) $config['average_speed_mph'],
                ),
            };
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Paginator::useBootstrapFive();
        
        Vite::useStyleTagAttributes(function (?string $src, string $url, ?array $chunk, ?array $manifest) {
            if ($src !== null) {
                return [
                    'class' => preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?core)-?.*/i", $src) ? 'template-customizer-core-css' : (preg_match("/(resources\/assets\/vendor\/scss\/(rtl\/)?theme)-?.*/i", $src) ? 'template-customizer-theme-css' : '')
                ];
            }
            return [];
        });
    }
}
