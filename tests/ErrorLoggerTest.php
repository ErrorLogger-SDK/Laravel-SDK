<?php

declare(strict_types=1);

namespace ErrorLogger\Tests;

use Carbon\Carbon;
use ErrorLogger\ErrorLogger;
use ErrorLogger\Tests\Mocks\ErrorLoggerClient;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

/**
 * Class ErrorLoggerTest
 *
 * @package ErrorLogger\Tests
 */
class ErrorLoggerTest extends TestCase
{
    /**
     * @var ErrorLogger
     */
    protected $errorLogger;

    /**
     * @var ErrorLoggerClient
     */
    protected $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->errorLogger = new ErrorLogger($this->client = new ErrorLoggerClient(
            'api_key'
        ));
    }

    /**
     * @test
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function is_will_not_crash_if_errorlogger_returns_error_bad_response_exception()
    {
        $this->errorLogger = new ErrorLogger($this->client = new \ErrorLogger\Http\Client(
            'login_key'
        ));

        //
        $this->app['config']['errorlogger.environments'] = ['testing'];

        $this->client->setGuzzleHttpClient(new Client([
            'handler' => MockHandler::createWithMiddleware([
                new Response(500, [], '{}')
            ]),
        ]));

        $this->assertInstanceOf(get_class(new \stdClass()), $this->errorLogger->handle(new Exception('is_will_not_crash_if_errorlogger_returns_error_bad_response_exception')));
    }

    /**
     * @test
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function is_will_not_crash_if_errorlogger_returns_normal_exception()
    {
        $this->errorLogger = new ErrorLogger($this->client = new \ErrorLogger\Http\Client(
            'api_key'
        ));

        //
        $this->app['config']['errorlogger.environments'] = ['testing'];

        $this->client->setGuzzleHttpClient(new Client([
            'handler' => MockHandler::createWithMiddleware([
                new \Exception()
            ]),
        ]));

        $this->assertFalse($this->errorLogger->handle(new Exception('is_will_not_crash_if_errorlogger_returns_normal_exception')));
    }

    /** @test */
    public function it_can_skip_exceptions_based_on_class()
    {
        $this->app['config']['errorlogger.except'] = [];

        $this->assertFalse($this->errorLogger->isSkipException(NotFoundHttpException::class));

        $this->app['config']['errorlogger.except'] = [
            NotFoundHttpException::class
        ];

        $this->assertTrue($this->errorLogger->isSkipException(NotFoundHttpException::class));
    }

    /** @test */
    public function it_can_skip_exceptions_based_on_environment()
    {
        $this->app['config']['errorlogger.environments'] = [];

        $this->assertTrue($this->errorLogger->isSkipEnvironment());

        $this->app['config']['errorlogger.environments'] = ['production'];

        $this->assertTrue($this->errorLogger->isSkipEnvironment());

        $this->app['config']['errorlogger.environments'] = ['testing'];

        $this->assertFalse($this->errorLogger->isSkipEnvironment());
    }

    /** @test */
    public function it_will_return_false_for_sleeping_cache_exception_if_disabled()
    {
        $this->app['config']['errorlogger.sleep'] = 0;

        $this->assertFalse($this->errorLogger->isSleepingException([]));
    }

    /** @test */
    public function it_can_check_if_is_a_sleeping_cache_exception()
    {
        $data = ['host' => 'localhost', 'method' => 'GET', 'exception' => 'it_can_check_if_is_a_sleeping_cache_exception', 'line' => 2, 'file' => '/tmp/ErrorLogger/tests/ErrorLogger.php', 'class' => 'Exception'];

        Carbon::setTestNow('2019-10-12 13:30:00');

        $this->assertFalse($this->errorLogger->isSleepingException($data));

        Carbon::setTestNow('2019-10-12 13:30:00');

        $this->errorLogger->addExceptionToSleep($data);

        $this->assertTrue($this->errorLogger->isSleepingException($data));

        Carbon::setTestNow('2019-10-12 13:31:00');

        $this->assertTrue($this->errorLogger->isSleepingException($data));

        Carbon::setTestNow('2019-10-12 13:31:01');

        $this->assertFalse($this->errorLogger->isSleepingException($data));
    }

    /** @test */
    public function it_can_get_formatted_exception_data()
    {
        $data = $this->errorLogger->getExceptionData(new Exception(
            'it_can_get_formatted_exception_data'
        ));

        $this->assertSame('testing', $data['environment']);
        $this->assertSame('localhost', $data['host']);
        $this->assertSame('GET', $data['method']);
        $this->assertSame('http://localhost', $data['fullUrl']);
        $this->assertSame('it_can_get_formatted_exception_data', $data['exception']);

        $this->assertCount(16, $data);
    }

    /** @test */
    public function it_filters_the_data_based_on_the_configuration()
    {
        $this->assertContains('password', $this->app['config']['errorlogger.blacklist']);

        $data = [
            'password' => 'testing',
            'not_password' => 'testing',
            'not_password2' => [
                'password' => 'testing'
            ],
            'not_password_3' => [
                'nah' => [
                    'password' => 'testing'
                ]
            ],
            'Password' => 'testing'
        ];

        $this->assertArrayNotHasKey('password', $this->errorLogger->filterVariables($data));
        $this->assertArrayHasKey('not_password', $this->errorLogger->filterVariables($data));
        $this->assertArrayNotHasKey('password', $this->errorLogger->filterVariables($data)['not_password2']);
        $this->assertArrayNotHasKey('password', $this->errorLogger->filterVariables($data)['not_password_3']['nah']);
        $this->assertArrayNotHasKey('Password', $this->errorLogger->filterVariables($data));
    }

    /**
     * @test
     *
     * @throws \GuzzleHttp\Exception\GuzzleException
     */
    public function it_can_report_an_exception_to_errorlogger()
    {
        $this->app['config']['errorlogger.environments'] = ['testing'];

        $this->errorLogger->handle(new Exception('it_can_report_an_exception_to_errorlogger'));

        $this->client->assertRequestsSent(1);
    }
}
