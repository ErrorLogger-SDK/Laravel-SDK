<p align="left">
<img src="https://error-logger.netlify.app/assets/images/official.svg" width="100" />
</p>
<h1>ErrorLogger</h1>

Laravel 7.x package for logging errors to [error-logger.netlify.app](https://error-logger.netlify.app)

[![Latest Stable Version](https://poser.pugx.org/cerealkiller/error-logger-laravel-sdk/v)](//packagist.org/packages/cerealkiller/error-logger-laravel-sdk)
[![Total Downloads](https://poser.pugx.org/cerealkiller/error-logger-laravel-sdk/downloads)](//packagist.org/packages/cerealkiller/error-logger-laravel-sdk)
[![License](https://poser.pugx.org/cerealkiller/error-logger-laravel-sdk/license)](//packagist.org/packages/cerealkiller/error-logger-laravel-sdk)

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

## Frontend
To catch frontend errors, add this include blade directive on top of the file where you want to log errors.
```php
@include('errorlogger::js-errorlogger-client')
```

## Testing

Now test to see if it works, you can do this in two ways.

##### Option 1:
 -  Run this in your terminal:
 
```shell script
php artisan errorlogger:test
```

##### Option 2:

- Run this code in your application to see if the exception is received by ErrorLogger.

```php
throw new \Exception('Testing my application!');
```

### And you are good to go! Happy coding :)

## Versioning
ErrorLogger-Laravel-SDK is versioned under the Semantic Versioning guidelines as much as possible.

Releases will be numbered with the following format:
```
<major>.<minor>.<patch>
```
And constructed with the following guidelines:

-   Breaking backward compatibility bumps the major and resets the minor and patch.

-   New additions without breaking backward compatibility bumps the minor and resets the patch.

-   Bug fixes and misc changes bumps the patch.

-   Minor versions are not maintained individually, and you're encouraged to upgrade through to the next minor version.

Major versions are maintained individually through separate branches.

## License
The ErrorLogger-Laravel-SDK package is open source software licensed under the [ Apache License 2.0](https://github.com/CerealKiller97/ErrorLogger-Laravel-SDK/blob/master/LICENSE)
