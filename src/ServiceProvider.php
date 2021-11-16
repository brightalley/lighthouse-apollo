<?php

namespace BrightAlley\LighthouseApollo;

use BrightAlley\LighthouseApollo\Commands\RegisterSchema;
use BrightAlley\LighthouseApollo\Commands\SubmitTracing;
use BrightAlley\LighthouseApollo\Contracts\ClientInformationExtractor;
use BrightAlley\LighthouseApollo\Listeners\EndExecutionListener;
use BrightAlley\LighthouseApollo\Listeners\ManipulateResultListener;
use BrightAlley\LighthouseApollo\Listeners\StartExecutionListener;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\ServiceProvider as BaseServiceProvider;
use Nuwave\Lighthouse\Events\EndExecution;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Events\StartExecution;
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
        $this->app->singleton(QueryRequestStack::class);
        $this->app->bind(
            ClientInformationExtractor::class,
            DefaultClientInformationExtractor::class,
        );

        $this->mergeConfigFrom(
            __DIR__ . '/../config/lighthouse-apollo.php',
            'lighthouse-apollo',
        );

        /** @var Dispatcher $events */
        $events = $this->app->make(Dispatcher::class);
        $events->listen(StartExecution::class, StartExecutionListener::class);
        $events->listen(
            ManipulateResult::class,
            ManipulateResultListener::class,
        );
        $events->listen(EndExecution::class, EndExecutionListener::class);
    }

    private function bootForConsole(): void
    {
        $this->publishes(
            [
                __DIR__ .
                '/../config/lighthouse-apollo.php' => $this->app->configPath(
                    'lighthouse-apollo.php',
                ),
            ],
            'lighthouse-apollo',
        );

        $this->commands([RegisterSchema::class, SubmitTracing::class]);
    }
}
