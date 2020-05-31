<?php

namespace ErrorLogger\Tests\Mocks;

use ErrorLogger\Http\Client;
use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Assert;

class LaraBugClient extends Client
{
    const RESPONSE_ID = 'test';

    /** @var array */
    protected $requests = [];

    /**
     * @param array $exception
     *
     * @return Response
     */
    public function report($exception)
    {
        $this->requests[] = $exception;

        return new Response(200, [], json_encode(['id' => self::RESPONSE_ID]));
    }

    /**
     * @param int $expectedCount
     */
    public function assertRequestsSent(int $expectedCount)
    {
        Assert::assertCount($expectedCount, $this->requests);
    }
}
