<?php

namespace ErrorLogger;

/**
 * @method static void assertSent($throwable, $callback = null)
 * @method static void assertRequestsSent(int $count)
 * @method static void assertNotSent($throwable, $callback = null)
 * @method static void assertNothingSent()
 */
class Facade extends \Illuminate\Support\Facades\Facade
{
    /**
     * Get the registered name of the component.
     *
     * @return string
     */
    protected static function getFacadeAccessor()
    {
        return 'errorlogger';
    }
}
