<?php

declare(strict_types=1);

namespace ErrorLogger\Logger;

use ErrorLogger\ErrorLogger;
use GuzzleHttp\Exception\GuzzleException;
use Monolog\Handler\AbstractProcessingHandler;
use Monolog\Logger;
use Throwable;

class ErrorLoggerBugHandler extends AbstractProcessingHandler
{
    /**
     * @var ErrorLogger
     */
    private $errorLogger;

    /**
     * @param ErrorLogger $errorLogger
     * @param int $level
     * @param bool $bubble
     */
    public function __construct(ErrorLogger $errorLogger, int $level = Logger::ERROR, bool $bubble = true)
    {
        $this->errorLogger = $errorLogger;

        parent::__construct($level, $bubble);
    }

    /**
     * @param array $record
     *
     * @throws GuzzleException
     */
    protected function write(array $record): void
    {
        if (isset($record['context']['exception']) && $record['context']['exception'] instanceof Throwable) {
            $this->errorLogger->handle($record['context']['exception']);

            return;
        }
    }
}
