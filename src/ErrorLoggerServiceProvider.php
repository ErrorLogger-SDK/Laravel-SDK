<?php

declare(strict_types=1);

namespace ErrorLogger;

use ErrorLogger\Commands\TestCommand;
use ErrorLogger\Http\Client;
use ErrorLogger\Logger\ErrorLoggerBugHandler;
use Illuminate\Foundation\Application;
use Illuminate\Log\LogManager;
use Illuminate\Support\Facades\{Blade, Route};
use Illuminate\Support\ServiceProvider;
use Monolog\Logger;

/**
 * Class ErrorLoggerServiceProvider
 *
 * @package ErrorLogger
 */
class ErrorLoggerServiceProvider extends ServiceProvider
{
    /**
     * @return void
     */
    public function boot(): void
    {
        // Publish configuration file
        if (function_exists('config_path')) {
            $this->publishes([
                __DIR__ . '/../config/errorlogger.php' => config_path('errorlogger.php'),
            ]);
        }

        // Register views
        $this->app['view']->addNamespace('errorlogger', __DIR__ . '/../resources/views');

        // Register commands
        $this->commands([
            TestCommand::class
        ]);

        // Map JS error report route
        $this->mapErrorLoggerApiRoute();

        // Create an alias to the errorlogger-js-client.blade.php include
        Blade::include('errorlogger::js-errorlogger-client', 'errorloggerJavaScriptClient');
    }

    /**
     * @return void
     */
    private function mapErrorLoggerApiRoute(): void
    {
        Route::namespace('\ErrorLogger\Http\Controllers')
            ->prefix('errorlogger-api')
            ->group(__DIR__ . '/../routes/api.php');
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/errorlogger.php', 'errorlogger');

        $this->app->singleton('errorlogger', function (Application $app) {
            return new ErrorLogger(new Client(
                config('errorlogger.api_key')
            ));
        });

        if ($this->app['log'] instanceof LogManager) {
            $this->app['log']->extend('errorlogger', function ($app, $config) {
                $handler = new ErrorLoggerBugHandler($app['errorlogger']);

                return new Logger('errorlogger', [$handler]);
            });
        }
    }
}
