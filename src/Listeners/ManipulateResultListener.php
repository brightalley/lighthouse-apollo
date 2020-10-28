<?php

namespace BrightAlley\LighthouseApollo\Listeners;

use BrightAlley\LighthouseApollo\Actions\SendTracingToApollo;
use BrightAlley\LighthouseApollo\Connectors\RedisConnector;
use BrightAlley\LighthouseApollo\Contracts\ClientInformationExtractor;
use BrightAlley\LighthouseApollo\Exceptions\InvalidTracingSendMode;
use BrightAlley\LighthouseApollo\TracingResult;
use Exception;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use LogicException;
use Mdg\Trace\HTTP\Method;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;

class ManipulateResultListener
{
    private Config $config;

    private ClientInformationExtractor $clientInformationExtractor;

    private Request $request;

    private SchemaSourceProvider $schemaSourceProvider;

    private RedisConnector $redisConnector;

    /**
     * Constructor.
     *
     * @param Config $config
     * @param ClientInformationExtractor $clientInformationExtractor
     * @param RedisConnector $redisConnector
     * @param Request $request
     * @param SchemaSourceProvider $schemaSourceProvider
     */
    public function __construct(
        Config $config,
        ClientInformationExtractor $clientInformationExtractor,
        RedisConnector $redisConnector,
        Request $request,
        SchemaSourceProvider $schemaSourceProvider
    ) {
        $this->config = $config;
        $this->clientInformationExtractor = $clientInformationExtractor;
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
        if (
            !isset($event->result->extensions['tracing']) ||
            !$this->config->get('lighthouse-apollo.apollo_key') ||
            $this->isIntrospectionQuery($event)
        ) {
            return;
        }

        $trace = new TracingResult(
            $this->request->json('query'),
            $this->extractClientInformation(),
            $this->extractHttpInformation(),
            $event->result->extensions['tracing']
        );

        $this->removeTracingFromExtensionsIfNeeded($event);

        $tracingSendMode = $this->config->get('lighthouse-apollo.send_tracing_mode');
        switch ($tracingSendMode) {
            case 'sync':
                try {
                    (new SendTracingToApollo($this->config, $this->schemaSourceProvider, [$trace]))
                        ->send();
                } catch (Exception $e) {
                    // We should probably not cause pain for the end users. Just include this in the extensions instead.
                    if (!isset($event->result->extensions['errors'])) {
                        $event->result->extensions['errors'] = [];
                    }

                    $event->result->extensions['errors'][] = [
                        'message' => $e->getMessage(),
                        'extensions' => array_merge([
                            'type' => get_class($e),
                            'code' => $e->getCode(),
                        ], $this->config->get('app.debug') ? [
                            'trace' => $e->getTraceAsString(),
                        ] : []),
                    ];
                }
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

    private function extractClientInformation(): array
    {
        return [
            'address' => $this->clientInformationExtractor->getClientAddress(),
            'name' => $this->clientInformationExtractor->getClientName(),
            'reference_id' => $this->clientInformationExtractor->getClientReferenceId(),
            'version' => $this->clientInformationExtractor->getClientVersion(),
        ];
    }

    private function extractHttpInformation(): array
    {
        // These keys should correspond with Protobuf's HTTP object.
        /** {@see \Mdg\Trace\HTTP} */
        return [
            'method' => Method::value($this->request->method()),
            'host' => $this->request->getHost(),
            'path' => $this->request->path(),
            'secure' => $this->request->secure(),
            'protocol' => $this->request->getProtocolVersion(),
            // TODO: Include request headers. These need an extra transformation before sending.
            // 'request_headers' => Arr::except(
            //     $this->request->headers->all(),
            //     $this->config->get('lighthouse-apollo.excluded_request_headers')
            // ),
        ];
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
