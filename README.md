# Laravel Lighthouse Apollo Integration

This library allows integrating your Lighthouse GraphQL project with Apollo Studio,
sending tracing statistics and allowing you to send your schema to Apollo for 
breaking changes notifications.

## Installation

First, install the Composer package:

`composer require bright-ally/lighthouse-apollo`

Next, publish the config file and adjust it as desired:

`php artisan vendor:publish --config lighthouse-apollo`

When using the redis or database send tracing mode (highly recommended for production
usage), make sure to add the `lighthouse-apollo:publish-tracing` artisan command to your
console kernel's schedule, so it runs frequently to send queued trace results to Apollo.

```php
public function schedule(\Illuminate\Console\Scheduling\Schedule $schedule)
{
    $schedule->command('lighthouse-apollo:submit-tracing')
        ->everyFiveMinutes();
}
```

## Requirements

- PHP 7.4 or newer
- Tested with Laravel 8 and Lighthouse 4.17
