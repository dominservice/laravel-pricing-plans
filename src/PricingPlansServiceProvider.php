<?php

namespace Dominservice\PricingPlans;

use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Collection;
use Illuminate\Support\ServiceProvider;

/**
 * Class PricingPlansServiceProvider
 *
 * @package Dominservice\PricingPlans
 */
class PricingPlansServiceProvider extends ServiceProvider
{
    private  $lpMigration = 0;

    /**
     * Bootstrap the application services.
     *
     * @return void
     */
    public function boot(Filesystem $filesystem)
    {
        $pkg = __DIR__ . '/../resources';

        $this->loadTranslationsFrom($pkg . '/lang', 'plans');



        $this->publishes([
            __DIR__ . '/../config/plans.php' => config_path('plans.php'),
        ], 'config');

        $this->publishes([
            __DIR__.'/../database/migrations/create_plans_tables.php.stub' => $this->getMigrationFileName($filesystem, 'create_plans_tables'),
        ], 'migrations');


        $this->publishes([
            $pkg . '/migrations/2018_01_01_000000_create_plans_tables.php'
            => database_path('migrations/' . date('Y_m_d_His') . '_create_plans_tables.php')
        ], 'migrations');

        $this->publishes([
            $pkg . '/config/plans.php' => config_path('plans.php')
        ], 'config');

        $this->publishes([
            $pkg . '/lang' => resource_path('lang/vendor/plans'),
        ]);
    }

    /**
     * Register the application services.
     *
     * @return void
     */
    public function register()
    {
        $this->mergeConfigFrom(__DIR__.'/../config/config/plans.php', 'plans');
    }

    /**
     * Returns existing migration file if found, else uses the current timestamp.
     *
     * @param Filesystem $filesystem
     * @return string
     */
    protected function getMigrationFileName(Filesystem $filesystem, $name): string
    {
        $this->lpMigration++;
        $timestamp = date('Y_m_d_Hi'.str_pad($this->lpMigration, 10, "0", STR_PAD_RIGHT));

        return Collection::make($this->app->databasePath().DIRECTORY_SEPARATOR.'migrations'.DIRECTORY_SEPARATOR)
            ->flatMap(function ($path) use ($filesystem, $name) {
                return $filesystem->glob($path.'*'.$name.'.php');
            })->push($this->app->databasePath()."/migrations/{$timestamp}_{$name}.php")
            ->first();
    }
}
