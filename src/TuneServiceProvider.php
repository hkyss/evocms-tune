<?php

declare(strict_types=1);

namespace hkyss\Tune;

use hkyss\Tune\Console\Commands\DoctorCommand;
use hkyss\Tune\Console\Commands\PruneCommand;
use hkyss\Tune\Console\Commands\TuneCommand;
use hkyss\Tune\Console\Commands\UntuneCommand;
use Illuminate\Support\ServiceProvider;

class TuneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/tune.php', 'tune');
    }

    public function boot(): void
    {
        $this->publishes([
            __DIR__ . '/../config/tune.php' => $this->configTarget(),
        ], 'tune-config');

        if ($this->app->runningInConsole()) {
            $this->commands([
                DoctorCommand::class,
                TuneCommand::class,
                UntuneCommand::class,
                PruneCommand::class,
            ]);
        }
    }

    private function configTarget(): string
    {
        if (!function_exists('config_path')) {
            return 'tune.php';
        }

        try {
            if ((new \ReflectionFunction('config_path'))->getNumberOfParameters() >= 2) {
                return config_path('tune.php', true);
            }
        } catch (\ReflectionException) {
            return config_path('tune.php');
        }

        return config_path('tune.php');
    }
}
