<?php

namespace BrightAlley\LighthouseApollo\Listeners;

use BrightAlley\LighthouseApollo\Actions\SendTracingToApollo;
use BrightAlley\LighthouseApollo\Connectors\RedisConnector;
use BrightAlley\LighthouseApollo\Contracts\ClientInformationExtractor;
use BrightAlley\LighthouseApollo\Exceptions\InvalidTracingSendMode;
use BrightAlley\LighthouseApollo\TracingResult;
use Exception;
use GraphQL\Error\Debug;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Http\Request;
use LogicException;
use Mdg\Trace\HTTP\Method;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Execution\GraphQLRequest;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;

class ManipulateResultListener
{
    public const DEBUG_FLAGS = Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE;

    private ClientInformationExtractor $clientInformationExtractor;

    private Config $config;

    private GraphQLRequest $graphQlRequest;

    private RedisConnector $redisConnector;

    private Request $request;

    private SchemaSourceProvider $schemaSourceProvider;

    /**
     * Constructor.
     *
     * @param ClientInformationExtractor $clientInformationExtractor
     * @param Config $config
     * @param GraphQLRequest $graphQlRequest
     * @param RedisConnector $redisConnector
     * @param Request $request
     * @param SchemaSourceProvider $schemaSourceProvider
     */
    public function __construct(
        ClientInformationExtractor $clientInformationExtractor,
        Config $config,
        GraphQLRequest $graphQlRequest,
        RedisConnector $redisConnector,
        Request $request,
        SchemaSourceProvider $schemaSourceProvider
    ) {
        $this->clientInformationExtractor = $clientInformationExtractor;
        $this->config = $config;
        $this->graphQlRequest = $graphQlRequest;
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
            !$this->config->get('lighthouse-apollo.apollo_key') ||
            $this->isIntrospectionQuery()
        ) {
            return;
        }

        if (
            !isset($event->result->extensions['tracing']) &&
            !count($event->result->errors)
        ) {
            return;
        }

        $trace = new TracingResult(
            $this->graphQlRequest->query(),
            $this->extractClientInformation(),
            $this->extractHttpInformation(),
            $event->result->extensions['tracing'] ?? [],
            array_map(function (Error $error) {
                return FormattedError::createFromException($error, self::DEBUG_FLAGS);
            }, $event->result->errors),
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
                    $event->result->errors[] = new Error(
                        'Failed to send tracing to Apollo',
                        null,
                        null,
                        null,
                        null,
                        $e
                    );
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

    private function isIntrospectionQuery(): bool
    {
        return (bool) preg_match('/^\s*query[^{]*{\s*(\w+:\s*)?__schema\s*{/', $this->graphQlRequest->query());
    }
}
