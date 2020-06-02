<?php

declare(strict_types=1);

namespace ErrorLogger\Tests;

use Illuminate\Foundation\Application;
use ErrorLogger\ErrorLoggerServiceProvider;

/**
 * Class TestCase
 *
 * @package ErrorLogger\Tests
 */
class TestCase extends \Orchestra\Testbench\TestCase
{
    /**
     * Setup the test environment.
     *
     * @return void
     */
    public function setUp(): void
    {
        parent::setUp();
    }

    /**
     * @param Application $app
     * @return array
     */
    protected function getPackageProviders($app)
    {
        return [ErrorLoggerServiceProvider::class];
    }
}
