<?php

namespace BrightAlley\LighthouseApollo\Listeners;

use BrightAlley\LighthouseApollo\Actions\SendTracingToApollo;
use BrightAlley\LighthouseApollo\Connectors\RedisConnector;
use BrightAlley\LighthouseApollo\Exceptions\InvalidTracingSendMode;
use BrightAlley\LighthouseApollo\TracingResult;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use LogicException;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;

class ManipulateResultListener
{
    /**
     * @var Config
     */
    private $config;

    /**
     * @var Request
     */
    private $request;

    /**
     * @var SchemaSourceProvider
     */
    private $schemaSourceProvider;

    /**
     * @var RedisConnector
     */
    private $redisConnector;

    /**
     * Constructor.
     *
     * @param Config $config
     * @param RedisConnector $redisConnector
     * @param Request $request
     * @param SchemaSourceProvider $schemaSourceProvider
     */
    public function __construct(Config $config, RedisConnector $redisConnector, Request $request, SchemaSourceProvider $schemaSourceProvider)
    {
        $this->config = $config;
        $this->redisConnector = $redisConnector;
        $this->request = $request;
        $this->schemaSourceProvider = $schemaSourceProvider;
    }

    /**
     * Handle the event at the end of a GraphQL request.
     *
     * @param ManipulateResult $event
     * @throws InvalidTracingSendMode
     */
    public function handle(ManipulateResult $event): void
    {
        if (!isset($event->result->extensions['tracing'])) {
            return;
        }

        if ($this->isIntrospectionQuery($event)) {
            return;
        }

        $trace = new TracingResult($this->request->json('query'), $event->result->extensions['tracing']);

        $this->removeTracingFromExtensionsIfNeeded($event);

        $tracingSendMode = $this->config->get('lighthouse-apollo.send_tracing_mode');
        switch ($tracingSendMode) {
            case 'sync':
                (new SendTracingToApollo($this->config, $this->schemaSourceProvider, [$trace]))
                    ->send();
                break;
            case 'redis':
                $this->redisConnector->put($trace);
                break;
            case 'database':
                throw new LogicException('Not yet implemented.');
            default:
                throw new InvalidTracingSendMode($tracingSendMode);
        }
    }

    private function removeTracingFromExtensionsIfNeeded(ManipulateResult $event): void
    {
        if ($this->config->get('lighthouse-apollo.mute_tracing_extensions')) {
            unset($event->result->extensions['tracing']);
        }
    }

    private function isIntrospectionQuery(ManipulateResult $event): bool
    {
        return ($event->result->extensions['tracing']['execution']['resolvers'][0]['fieldName'] ?? '') === '__schema';
    }
}
