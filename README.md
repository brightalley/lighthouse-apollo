# Laravel Lighthouse Apollo Integration

This library allows integrating your Lighthouse GraphQL project with Apollo Studio,
sending tracing statistics and allowing you to send your schema to Apollo for 
breaking changes notifications.

## Requirements

- PHP 7.4 or newer
- Tested with Laravel 8 and Lighthouse 4.17

## Installation

First, install the Composer package:

`composer require bright-alley/lighthouse-apollo`

Next, publish the config file and adjust it as desired:

`php artisan vendor:publish --provider="BrightAlley\LighthouseApollo\ServiceProvider"`

The service provider for this package is automatically registered. If you have disabled
auto-discovery of service providers, make sure to add `\BrightAlley\LighthouseApollo\ServiceProvider`
to your service providers. Lighthouse's TracingServiceProvider is automatically registered.
By default, the tracing results are stripped from the actual GraphQL response when not
in debug mode.

When using the redis or database send tracing mode (*highly recommended for production
usage*), make sure to add the `lighthouse-apollo:publish-tracing` artisan command to your
console kernel's schedule, so it runs frequently to send queued trace results to Apollo.
You can adjust the schedule to run more or less often based on your traffic volume.

```php
public function schedule(\Illuminate\Console\Scheduling\Schedule $schedule)
{
    $schedule->command('lighthouse-apollo:submit-tracing')
        ->everyMinute();
}
```

## Client tracing

You can gather information about which clients are calling your GraphQL API. If you have
control over the clients, add the `x-apollo-client-name` and `x-apollo-client-version` 
headers to your GraphQL requests, and they will be gathered and sent to Apollo Studio.

If you need more control over client tracing on the server side, you can create your own
custom logic by implementing the `BrightAlley\LighthouseApollo\Contracts\ClientInformationExtractor`
interface, and binding your own implementation to the app container in your service
provider, like so:

```php
$this->app->bind(ClientInformationExtractor::class, MyCustomClientInformationExtractor::class);
```

## Development

Protobuf is used for sending traces to Apollo Studio. To generate new stubs, use the 
following command:

`protoc -I resources --php_out=generated/ resources/*.proto`
