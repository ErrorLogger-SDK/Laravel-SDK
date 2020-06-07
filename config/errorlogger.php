<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Api key
    |--------------------------------------------------------------------------
    |
    | This is your api key which you get from your profile.
    | Retrieve your key from https://www.error-logger.netlify.app
    |
    */

    'api_key' => env('ERRORLOGGER_API_KEY', 'API-KEY-HERE'),

    /*
    |--------------------------------------------------------------------------
    | Environment setting
    |--------------------------------------------------------------------------
    |
    | This setting determines if the exception should be send over or not.
    | Supported: local, production
    |
    */

    'environments' => [
        'local'
    ],

    /*
    |--------------------------------------------------------------------------
    | Lines near exception
    |--------------------------------------------------------------------------
    |
    | How many lines to show near exception line. The more you specify the bigger
    | the displayed code will be. Max value can be 20, will be defaulted to
    | 10 if higher than 20 automatically.
    |
    */

    'lines_count' => 10,

    /*
    |--------------------------------------------------------------------------
    | Prevent duplicates
    |--------------------------------------------------------------------------
    |
    | Set the sleep time between duplicate exceptions.
    | This value is in seconds.
    | Default: 60 seconds (1 minute)
    |
    */

    'sleep' => 60,

    /*
    |--------------------------------------------------------------------------
    | Skip exceptions
    |--------------------------------------------------------------------------
    |
    | List of exceptions to skip sending.
    |
    */

    'except' => [
        'Symfony\Component\HttpKernel\Exception\NotFoundHttpException',
    ],

    /*
    |--------------------------------------------------------------------------
    | Key filtering
    |--------------------------------------------------------------------------
    |
    | Filter out these variables before sending them to ErrorLogger
    |
    */

    'blacklist' => [
        'password',
        'authorization'
    ],
];
