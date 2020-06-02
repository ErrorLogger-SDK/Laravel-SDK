<?php

namespace ErrorLogger;

use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\Promise\PromiseInterface;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\{App, Cache, Request, Session};
use Illuminate\Support\Str;
use ErrorLogger\Http\Client;
use Psr\Http\Message\ResponseInterface;
use Throwable;

/**
 * Class ErrorLogger
 *
 * @package ErrorLogger
 */
class ErrorLogger
{
    /** @var Client */
    private $client;

    /** @var array */
    private $blacklist = [];

    /** @var null|string */
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

        if ($fileType === 'javascript') {
            $data['fullUrl'] = $customData['url'];
            $data['file'] = $customData['file'];
            $data['file_type'] = $fileType;
            $data['error'] = $customData['message'];
            $data['exception'] = $customData['stack'];
            $data['line'] = $customData['line'];
            $data['class'] = null;

            $count = config('errorlogger.lines_count');

            if ($count > 50) {
                $count = 10;
            }

            $lines = file($data['file']);
            $data['executor'] = [];


            for ($i = -1 * abs($count); $i <= abs($count); $i++) {
                $currentLine = $data['line'] + $i;

                $index = $currentLine - 1;

                if (!array_key_exists($index, $lines)) {
                    continue;
                }

                $data['executor'][] = [
                    'line_number' => $currentLine,
                    'line' => $lines[$index]
                ];
            }

            $data['executor'] = array_filter($data['executor']);
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
     * @param string|null $id
     *
     * @return void
     */
    private function setLastExceptionId(?string $id = null): void
    {
        $this->lastExceptionId = $id;
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
        $data['release'] = config('errorlogger.release', null);
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

        if ($count > 50) {
            $count = 10;
        }

        $lines = file($data['file']);
        $data['executor'] = [];

        for ($i = -1 * abs($count); $i <= abs($count); $i++) {
            $data['executor'][] = $this->getLineInfo($lines, $data['line'], $i);
        }
        $data['executor'] = array_filter($data['executor']);

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
     * @param $variables
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
     * Gets information from the line
     *
     * @param $lines
     * @param $line
     * @param $i
     *
     * @return array|void
     */
    private function getLineInfo(array $lines, int $line, int $i)
    {
        $currentLine = $line + $i;

        $index = $currentLine - 1;

        if (!array_key_exists($index, $lines)) {
            return;
        }
        return [
            'line_number' => $currentLine,
            'line' => $lines[$index]
        ];
    }

    /**
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
     * @param array $data
     *
     * @return bool
     */
    public function addExceptionToSleep(array $data): bool
    {
        $exceptionString = $this->createExceptionString($data);

        return Cache::put($exceptionString, $exceptionString, config('errorlogger.sleep'));
    }
}
