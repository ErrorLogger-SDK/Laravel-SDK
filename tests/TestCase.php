<?php

namespace ErrorLogger\Tests;

use Illuminate\Foundation\Application;
use ErrorLogger\ErrorLoggerServiceProvider;

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
