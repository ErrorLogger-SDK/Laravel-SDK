<?php

declare(strict_types=1);

namespace ErrorLogger\Http\Controllers;

use ErrorException;
use ErrorLogger\ErrorLogger;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Class LaraBugReportController
 *
 * @package LaraBug\Http\Controllers
 */
class ErrorLoggerReportController
{
    /**
     * @var string
     */
    private const JAVASCRIPT = 'javascript';

    /**
     * @param Request $request
     *
     * @return JsonResponse
     *
     * @throws GuzzleException
     */
    public function report(Request $request): JsonResponse
    {
        /** @var ErrorLogger $errorLogger */
        $errorLogger = app('errorlogger');

        $errorLogger->handle(
            new ErrorException($request->input('message')),
            self::JAVASCRIPT,
            [
                'file' => $request->input('file'),
                'line' => $request->input('line'),
                'message' => $request->input('message'),
                'stack' => $request->input('stack'),
                'url' => $request->input('url'),
            ]
        );

        return response()->json(['message' => 'ok']);
    }
}
