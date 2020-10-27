<?php

namespace BrightAlley\LighthouseApollo;

use BrightAlley\LighthouseApollo\Commands\RegisterSchema;
use BrightAlley\LighthouseApollo\Commands\SubmitTracing;
use BrightAlley\LighthouseApollo\Contracts\ClientInformationExtractor;
use BrightAlley\LighthouseApollo\Listeners\ManipulateResultListener;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Tracing\TracingServiceProvider;

class ServiceProvider extends BaseServiceProvider
{
    public function boot(): void
    {
        if ($this->app->runningInConsole()) {
            $this->bootForConsole();
        }
    }

    public function provides(): array
    {
        return ['lighthouse-apollo'];
    }

    public function register(): void
    {
        $this->app->register(TracingServiceProvider::class);
        $this->app->bind(ClientInformationExtractor::class, DefaultClientInformationExtractor::class);

        $this->mergeConfigFrom(__DIR__ . '/../config/lighthouse-apollo.php', 'lighthouse-apollo');

        Event::listen(ManipulateResult::class, ManipulateResultListener::class);
    }

    private function bootForConsole(): void
    {
        $this->publishes([
            __DIR__ . '/../config/lighthouse-apollo.php' => config_path('lighthouse-apollo.php')
        ], 'lighthouse-apollo');

        $this->commands([
            RegisterSchema::class,
            SubmitTracing::class,
        ]);
    }
}
