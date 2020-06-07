<?php

declare(strict_types=1);

namespace ErrorLogger\Commands;

use ErrorLogger\ErrorLogger;
use Exception;
use Illuminate\Config\Repository as Config;
use Illuminate\Console\Command;

/**
 * Class TestCommand
 *
 * @package ErrorLogger\Commands
 */
class TestCommand extends Command
{
    /**
     * @var string
     */
    protected $signature = 'errorlogger:test';

    /**
     * @var string
     */
    protected $description = 'Generate a test exception and send it to ErrorLogger';

    /**
     * @var Config
     */
    private $config;

    /**
     * TestCommand constructor.
     *
     * @param Config $config
     */
    public function __construct(Config $config)
    {
        parent::__construct();

        $this->config = $config;
    }

    /**
     * @throws \GuzzleHttp\Exception\GuzzleException
     *
     * @return void
     */
    public function handle(): void
    {
        try {
            /** @var ErrorLogger $errorlogger */
            $errorlogger = app('errorlogger');

            if ($this->config->get('errorlogger.api_key')) {
                $this->info('✓ [ErrorLogger] Found API key');
            } else {
                $this->error('✗ [ErrorLogger] Could not find your API key, set this in your .env');
                return;
            }

            if (in_array($this->config->get('app.env'), $this->config->get('errorlogger.environments'))) {
                $this->info('✓ [ErrorLogger] Correct environment found');
            } else {
                $this->error('✗ [ErrorLogger] Environment not allowed to send errors to ErrorLogger, set this in your config');
                return;
            }

            $response = $errorlogger->handle($this->generateException());

            if (isset($response->message)) {
                $this->error('✗ [ErrorLogger] Failed to send exception to ErrorLogger. Reason: ' . $response->message);
                $this->warn('[ErrorLogger] Hint: Check your .env file.');
                return;
            }

            if (isset($response->id)) {
                $this->info('✓ [ErrorLogger] Sent exception to ErrorLogger with ID: ' . $response->id);
            } else {
                $this->error('✗ [ErrorLogger] Failed to send exception to ErrorLogger');
            }
        } catch (\Exception $ex) {
            $this->error("✗ [ErrorLogger] {$ex->getMessage()}");
        }
    }

    /**
     * Generates a test exception to send it to ErrorLogger.
     *
     * @return Exception|null
     */
    public function generateException(): ?Exception
    {
        try {
            throw new Exception('This is a test exception from the ErrorLogger console.');
        } catch (Exception $ex) {
            return $ex;
        }
    }
}
