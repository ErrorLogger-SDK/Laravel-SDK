<?php

namespace ErrorLogger;

use ErrorLogger\Http\Client;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{App, Cache, Request, Session};
use Illuminate\Support\Str;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Class ErrorLogger
 *
 * @package ErrorLogger
 */
class ErrorLogger
{
    /**
     * @var string
     */
    private const BACKEND = 'backend';

    /**
     * @var string
     */
    private const FRONTEND = 'frontend';

    /**
     * @var string
     */
    private const  SDK_VERSION = '1.0.2';

    private const LANGUAGES = [
        'php' => 'php',
        'javascript' => 'javascript'
    ];

    /**
     * @var Client
     */
    private $client;

    /**
     * @var string[]
     */
    private $blacklist = [];

    /**
     * @var string
     */
    private $lastExceptionId;

    /**
     * @param Client $client
     */
    public function __construct(Client $client)
    {
        $this->client = $client;

        $this->blacklist = array_map(function (string $blacklist) {
            return strtolower($blacklist);
        }, config('errorlogger.blacklist', []));
    }

    /**
     * @param Throwable $exception
     * @param string $fileType
     * @param array $customData
     *
     * @return bool|mixed
     *
     * @throws GuzzleException
     */
    public function handle(Throwable $exception, string $fileType = 'php', array $customData = [])
    {
        if ($this->isSkipEnvironment()) {
            return false;
        }

        $data = $this->getExceptionData($exception);

        if ($this->isSkipException($data['class'])) {
            return false;
        }

        if ($this->isSleepingException($data)) {
            return false;
        }

        if ($fileType === self::LANGUAGES['javascript']) {
            $data['fullUrl'] = $customData['url'];
            $data['file'] = $customData['file'];
            $data['file_type'] = self::FRONTEND;
            $data['error'] = $customData['message'];
            $data['exception'] = $customData['stack'];
            $data['line'] = $customData['line'];
            $data['class'] = null;

            $count = config('errorlogger.lines_count');

            if ($count > 20) {
                $count = 10;
            }

            $content = file_get_contents($data['file']);

            $metaData = $this->getSourceCodeFromFile($content, $count, $data['line']);

            $data['source_code'] = $metaData['source_code'];
            $data['newExceptionLine'] = $metaData['source_code_exception_line'];
            $data['language'] = self::LANGUAGES['javascript'];
        }

        $rawResponse = $this->logError($data);

        if (!$rawResponse) {
            return false;
        }

        $response = json_decode($rawResponse->getBody()->getContents());

        if (isset($response->id)) {
            $this->setLastExceptionId($response->id);
        }

        if (config('errorlogger.sleep') !== 0) {
            $this->addExceptionToSleep($data);
        }

        return $response;
    }

    /**
     * @return bool
     */
    public function isSkipEnvironment(): bool
    {
        if (count(config('errorlogger.environments')) === 0) {
            return true;
        }

        if (in_array(App::environment(), config('errorlogger.environments'))) {
            return false;
        }

        return true;
    }

    /**
     * Get exception data.
     *
     * @param Throwable $exception
     *
     * @return array
     */
    public function getExceptionData(Throwable $exception): array
    {
        $data = [];

        $data['environment'] = App::environment();
        $data['host'] = Request::server('SERVER_NAME');
        $data['method'] = Request::method();
        $data['fullUrl'] = Request::fullUrl();
        $data['exception'] = $exception->getMessage();
        $data['error'] = $exception->getTraceAsString();
        $data['line'] = $exception->getLine();
        $data['file'] = $exception->getFile();
        $data['class'] = get_class($exception);
        $data['file_type'] = self::BACKEND;
        $data['ip'] = Request::ip();
        $data['sdk_version'] = self::SDK_VERSION;
        $data['storage'] = [
            'SERVER' => [
                'USER' => Request::server('USER'),
                'HTTP_USER_AGENT' => Request::server('HTTP_USER_AGENT'),
                'SERVER_PROTOCOL' => Request::server('SERVER_PROTOCOL'),
                'SERVER_SOFTWARE' => Request::server('SERVER_SOFTWARE'),
                'PHP_VERSION' => PHP_VERSION
            ],
            'GET' => $this->filterVariables(Request::query()),
            'POST' => $this->filterVariables($_POST),
            'FILE' => Request::file(),
            'OLD' => $this->filterVariables(Request::hasSession() ? Request::old() : []),
            'COOKIE' => $this->filterVariables(Request::cookie()),
            'SESSION' => $this->filterVariables(Request::hasSession() ? Session::all() : []),
            'HEADERS' => $this->filterVariables(Request::header()),
        ];

        $data['storage'] = array_filter($data['storage']);

        $count = config('errorlogger.lines_count');

        if ($count > 20) {
            $count = 10;
        }

        $content = file_get_contents($data['file']);

        $metaData = $this->getSourceCodeFromFile($content, $count, $exception->getLine());

        $data['source_code'] = $metaData['source_code'];
        $data['newExceptionLine'] = $metaData['source_code_exception_line'];
        $data['language'] = self::LANGUAGES['php'];

        // to make symfony exception more readable
        if ($data['class'] == 'Symfony\Component\Debug\Exception\FatalErrorException') {
            preg_match("~^(.+)' in ~", $data['exception'], $matches);
            if (isset($matches[1])) {
                $data['exception'] = $matches[1];
            }
        }

        return $data;
    }

    /**
     * Remove blacklisted variables.
     *
     * @param $variables
     *
     * @return array
     */
    public function filterVariables($variables)
    {
        if (is_array($variables)) {
            array_walk($variables, function ($val, $key) use (&$variables) {
                if (is_array($val)) {
                    $variables[$key] = $this->filterVariables($val);
                }
                if (in_array(strtolower($key), $this->blacklist)) {
                    unset($variables[$key]);
                }
            });

            return $variables;
        }

        return [];
    }

    /**
     * Determinate should skip exception.
     *
     * @param string $exceptionClass
     *
     * @return bool
     */
    public function isSkipException(string $exceptionClass): bool
    {
        return in_array($exceptionClass, config('errorlogger.except'));
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function isSleepingException(array $data): bool
    {
        if (config('errorlogger.sleep', 0) === 0) {
            return false;
        }

        return Cache::has($this->createExceptionString($data));
    }

    /**
     *
     *
     * @param array $data
     *
     * @return string
     */
    private function createExceptionString(array $data): string
    {
        return 'errorlogger.' . Str::slug($data['host'] . '_' . $data['method'] . '_' . $data['exception'] . '_' . $data['line'] . '_' . $data['file'] . '_' . $data['class']);
    }

    /**
     * @param array $exception
     *
     * @return PromiseInterface|ResponseInterface|null
     *
     * @throws GuzzleException
     */
    private function logError(array $exception)
    {
        return $this->client->report([
            'exception' => $exception,
            'user' => $this->getUser(),
        ]);
    }

    /**
     * Get authenticated user if exists, otherwise return null.
     *
     * @return array|null
     */
    public function getUser(): ?array
    {
        if (function_exists('auth') && auth()->check()) {
            /** @var Authenticatable $user */
            $user = auth()->user();

            if ($user instanceof Model) {
                return $user->toArray();
            }
        }

        return null;
    }

    /**
     * @param string|null $id
     *
     * @return void
     */
    private function setLastExceptionId(?string $id = null): void
    {
        $this->lastExceptionId = $id;
    }

    /**
     * @param array $data
     *
     * @return bool
     */
    public function addExceptionToSleep(array $data): bool
    {
        $exceptionString = $this->createExceptionString($data);

        return Cache::put($exceptionString, $exceptionString, config('errorlogger.sleep'));
    }

    /**
     * Get the last exception id given to us by the ErrorLogger API
     *
     * @return string|null
     */
    public function getLastExceptionId(): ?string
    {
        return $this->lastExceptionId;
    }

    /**
     * Extract visible part of a code from a file.
     *
     * @param string $content
     * @param int $count
     * @param int $exceptionLine
     *
     * @return array
     */
    private function getSourceCodeFromFile(string $content, int $count, int $exceptionLine): array
    {
        $exception_line = $exceptionLine - 1;
        $display = $count;

        $line_above_and_below = ceil($display / 2);

        $start_index = $exception_line - $line_above_and_below;
        $end_index = $exception_line + $line_above_and_below;

        $final_string = '';

        $arrayCode = preg_split("/\r\n|\n|\r/", $content);

        if ($start_index <= 0) {
            $start_index = 0;
        }

        if ($end_index >= count($arrayCode) - 1) {
            $end_index = count($arrayCode) - 1;
        }

        for ($i = $start_index; $i < $end_index; $i++) {
            $final_string .= $arrayCode[$i] . PHP_EOL;
        }

        $final_string_arr = explode(PHP_EOL, $final_string);

        $newExceptionLine = array_search($arrayCode[$exception_line], $final_string_arr);

        return [
            'source_code' => $final_string,
            'source_code_exception_line' => $newExceptionLine
        ];
    }
}
