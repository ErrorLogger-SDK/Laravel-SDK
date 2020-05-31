<?php

namespace ErrorLogger\Tests;

use Carbon\Carbon;
use ErrorLogger\ErrorLogger;
use Exception;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\Psr7\Response;
use ErrorLogger\Tests\Mocks\LaraBugClient;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class LaraBugTest extends TestCase
{
    /** @var LaraBug */
    protected $larabug;

    /** @var Mocks\LaraBugClient */
    protected $client;

    public function setUp(): void
    {
        parent::setUp();

        $this->larabug = new ErrorLogger($this->client = new LaraBugClient(
            'api_key'
        ));
    }

    /** @test */
    public function is_will_not_crash_if_errorlogger_returns_error_bad_response_exception()
    {
        $this->larabug = new ErrorLogger($this->client = new \ErrorLogger\Http\Client(
            'login_key'
        ));

        //
        $this->app['config']['errorlogger.environments'] = ['testing'];

        $this->client->setGuzzleHttpClient(new Client([
            'handler' => MockHandler::createWithMiddleware([
                new Response(500, [], '{}')
            ]),
        ]));

        $this->assertInstanceOf(get_class(new \stdClass()), $this->larabug->handle(new Exception('is_will_not_crash_if_errorlogger_returns_error_bad_response_exception')));
    }

    /** @test */
    public function is_will_not_crash_if_errorlogger_returns_normal_exception()
    {
        $this->larabug = new ErrorLogger($this->client = new \ErrorLogger\Http\Client(
            'api_key'
        ));

        //
        $this->app['config']['errorlogger.environments'] = ['testing'];

        $this->client->setGuzzleHttpClient(new Client([
            'handler' => MockHandler::createWithMiddleware([
                new \Exception()
            ]),
        ]));

        $this->assertFalse($this->larabug->handle(new Exception('is_will_not_crash_if_errorlogger_returns_normal_exception')));
    }

    /** @test */
    public function it_can_skip_exceptions_based_on_class()
    {
        $this->app['config']['errorlogger.except'] = [];

        $this->assertFalse($this->larabug->isSkipException(NotFoundHttpException::class));

        $this->app['config']['errorlogger.except'] = [
            NotFoundHttpException::class
        ];

        $this->assertTrue($this->larabug->isSkipException(NotFoundHttpException::class));
    }

    /** @test */
    public function it_can_skip_exceptions_based_on_environment()
    {
        $this->app['config']['errorlogger.environments'] = [];

        $this->assertTrue($this->larabug->isSkipEnvironment());

        $this->app['config']['errorlogger.environments'] = ['production'];

        $this->assertTrue($this->larabug->isSkipEnvironment());

        $this->app['config']['errorlogger.environments'] = ['testing'];

        $this->assertFalse($this->larabug->isSkipEnvironment());
    }

    /** @test */
    public function it_will_return_false_for_sleeping_cache_exception_if_disabled()
    {
        $this->app['config']['errorlogger.sleep'] = 0;

        $this->assertFalse($this->larabug->isSleepingException([]));
    }

    /** @test */
    public function it_can_check_if_is_a_sleeping_cache_exception()
    {
        $data = ['host' => 'localhost', 'method' => 'GET', 'exception' => 'it_can_check_if_is_a_sleeping_cache_exception', 'line' => 2, 'file' => '/tmp/ErrorLogger/tests/ErrorLogger.php', 'class' => 'Exception'];

        Carbon::setTestNow('2019-10-12 13:30:00');

        $this->assertFalse($this->larabug->isSleepingException($data));

        Carbon::setTestNow('2019-10-12 13:30:00');

        $this->larabug->addExceptionToSleep($data);

        $this->assertTrue($this->larabug->isSleepingException($data));

        Carbon::setTestNow('2019-10-12 13:31:00');

        $this->assertTrue($this->larabug->isSleepingException($data));

        Carbon::setTestNow('2019-10-12 13:31:01');

        $this->assertFalse($this->larabug->isSleepingException($data));
    }

    /** @test */
    public function it_can_get_formatted_exception_data()
    {
        $data = $this->larabug->getExceptionData(new Exception(
            'it_can_get_formatted_exception_data'
        ));

        $this->assertSame('testing', $data['environment']);
        $this->assertSame('localhost', $data['host']);
        $this->assertSame('GET', $data['method']);
        $this->assertSame('http://localhost', $data['fullUrl']);
        $this->assertSame('it_can_get_formatted_exception_data', $data['exception']);

        $this->assertCount(12, $data);
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

        $this->assertArrayNotHasKey('password', $this->larabug->filterVariables($data));
        $this->assertArrayHasKey('not_password', $this->larabug->filterVariables($data));
        $this->assertArrayNotHasKey('password', $this->larabug->filterVariables($data)['not_password2']);
        $this->assertArrayNotHasKey('password', $this->larabug->filterVariables($data)['not_password_3']['nah']);
        $this->assertArrayNotHasKey('Password', $this->larabug->filterVariables($data));
    }

    /** @test */
    public function it_can_report_an_exception_to_errorlogger()
    {
        $this->app['config']['errorlogger.environments'] = ['testing'];

        $this->larabug->handle(new Exception('it_can_report_an_exception_to_errorlogger'));

        $this->client->assertRequestsSent(1);
    }
}
