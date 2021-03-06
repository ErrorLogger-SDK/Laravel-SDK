<?php

declare(strict_types=1);

namespace ErrorLogger\Tests;

use ErrorLogger\ErrorLogger;
use ErrorLogger\Tests\Mocks\ErrorLoggerClient;

/**
 * Class TestCommandTest
 *
 * @package ErrorLogger\Tests
 */
class TestCommandTest extends TestCase
{
    /** @test */
    public function it_detects_if_the_api_key_is_set()
    {
        $this->app['config']['errorlogger.api_key'] = '';

        $this->artisan('errorlogger:test')
            ->expectsOutput('✗ [ErrorLogger] Could not find your API key, set this in your .env')
            ->assertExitCode(0);

        $this->app['config']['errorlogger.api_key'] = 'test';

        $this->artisan('errorlogger:test')
            ->expectsOutput('✓ [ErrorLogger] Found API key')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_detects_that_its_running_in_the_correct_environment()
    {
        $this->app['config']['app.env'] = 'production';
        $this->app['config']['errorlogger.environments'] = [];

        $this->artisan('errorlogger:test')
            ->expectsOutput('✗ [ErrorLogger] Environment not allowed to send errors to ErrorLogger, set this in your config')
            ->assertExitCode(0);

        $this->app['config']['errorlogger.environments'] = ['production'];

        $this->artisan('errorlogger:test')
            ->expectsOutput('✓ [ErrorLogger] Correct environment found')
            ->assertExitCode(0);
    }

    /** @test */
    public function it_detects_that_it_fails_to_send_to_errorlogger()
    {
        $this->app['config']['errorlogger.environments'] = [
            'testing'
        ];
        $this->app['errorlogger'] = new ErrorLogger($this->client = new ErrorLoggerClient(
            'api_key'
        ));

        $this->artisan('errorlogger:test')
            ->expectsOutput('✓ [ErrorLogger] Sent exception to ErrorLogger with ID: ' . ErrorLoggerClient::RESPONSE_ID)
            ->assertExitCode(0);

        $this->assertEquals(ErrorLoggerClient::RESPONSE_ID, $this->app['errorlogger']->getLastExceptionId());
    }
}
