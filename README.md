<p align="left">
<img src="https://error-logger.netlify.app/assets/images/official.svg" width="100" />
</p>
<h1>ErrorLogger</h1>

Laravel 7.x package for logging errors to [error-logger.netlify.app](https://error-logger.netlify.app)

# TODO Bagdes

## Installation 
You can install the package through Composer.
```bash
composer require cerealkiller/error-logger-laravel-sdk
```

Then publish the config and migration file of the package using artisan.
```bash
php artisan vendor:publish --provider="ErrorLogger\ErrorLoggerServiceProvider"
```
And adjust config file (`config/errorlogger.php`) with your desired settings.

Note: by default only local environments will report errors. To modify this edit your errorlogger configuration.

## Configuration variables
All that is left to do is to define  env configuration variable in .env

```
ERRORLOGGER_API_KEY=
```
`ERRORLOGGER_API_KEY` is your profile key which authorises your account to the API.

Get API_KEY at [error-logger.netlify.app](https://error-logger.netlify.app)

## Setup

Next is to add the ```errorlogger``` driver to the logging.php file:
```php
'channels' => [
    'errorlogger' => [
        'driver' => 'errorlogger',
    ],
],
```

After that you have configured the ErrorLogger channel you can add it to the stack section:
```php
'channels' => [
    'stack' => [
        'driver' => 'stack',
        'channels' => ['single', 'errorlogger'],
    ],
],
```

Now test to see if it works, you can do this in two ways.

### Option 1:
 -  Run this in your terminal:
 
```shell script
php artisan errorlogger:test
```

Option 2:

- Run this code in your application to see if the exception is received by ErrorLogger.

```php
throw new \Exception('Testing my application!');
```

### And you are good to go! Happy coding :)


## License
The ErrorLogger-Laravel-SDK package is open source software licensed under the [ Apache License 2.0]
