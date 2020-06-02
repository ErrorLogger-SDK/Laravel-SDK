<?php

declare(strict_types=1);

namespace ErrorLogger\Http\Controllers;

use ErrorException;
use ErrorLogger\ErrorLogger;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

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
    private const JAVASCRIPT ='javascript';

    /**
     * @param Request $request
     *
     * @return Response|ResponseFactory
     *
     * @throws GuzzleException
     */
    public function report(Request $request)
    {
        /** @var ErrorLogger $errorLogger */
        $errorLogger = app('errorlogger');

        $errorLogger->handle(
            new ErrorException($request->post('message')),
            self::JAVASCRIPT,
            [
                'file' => $request->post('file'),
                'line' => $request->post('line'),
                'message' => $request->post('message'),
                'stack' => $request->post('stack'),
                'url' => $request->post('url'),
            ]
        );

        return response('ok', 200);
    }
}
