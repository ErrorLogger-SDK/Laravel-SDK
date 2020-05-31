<?php

declare(strict_types=1);

namespace ErrorLogger;

use ErrorLogger\Commands\TestCommand;
use ErrorLogger\Http\Client;
use ErrorLogger\Logger\ErrorLoggerBugHandler;
use Illuminate\Foundation\{AliasLoader, Application};
use Illuminate\Log\LogManager;
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

        // Register facade
        if (class_exists(AliasLoader::class)) {
            $loader = AliasLoader::getInstance();
            $loader->alias('LaraBug', 'LaraBug\Facade');
        }

        // Register commands
        $this->commands([
            TestCommand::class
        ]);
    }

    /**
     * @return void
     */
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__ . '/../config/errorlogger.php', 'errorlogger');

        $this->app->singleton('errorlogger', function ($app) {
            return new ErrorLogger(new Client(
                config('errorlogger.api_key', 'api_key')
            ));
        });

        if ($this->app['log'] instanceof LogManager) {
            $this->app['log']->extend('errorlogger', function (Application $app, $config) {
                $handler = new ErrorLoggerBugHandler($app['errorlogger']);

                return new Logger('errorlogger', [$handler]);
            });
        }
    }
}
