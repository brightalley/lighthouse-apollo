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
use Illuminate\Support\Arr;
use JsonException;
use LogicException;
use Mdg\Trace\HTTP\Method;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Execution\GraphQLRequest;

class ManipulateResultListener
{
    public const DEBUG_FLAGS = Debug::INCLUDE_DEBUG_MESSAGE | Debug::INCLUDE_TRACE;

    private ClientInformationExtractor $clientInformationExtractor;

    private Config $config;

    private GraphQLRequest $graphQlRequest;

    private RedisConnector $redisConnector;

    private Request $request;

    /**
     * Constructor.
     *
     * @param ClientInformationExtractor $clientInformationExtractor
     * @param Config $config
     * @param GraphQLRequest $graphQlRequest
     * @param RedisConnector $redisConnector
     * @param Request $request
     */
    public function __construct(
        ClientInformationExtractor $clientInformationExtractor,
        Config $config,
        GraphQLRequest $graphQlRequest,
        RedisConnector $redisConnector,
        Request $request
    ) {
        $this->clientInformationExtractor = $clientInformationExtractor;
        $this->config = $config;
        $this->graphQlRequest = $graphQlRequest;
        $this->redisConnector = $redisConnector;
        $this->request = $request;
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
            $this->variables(),
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
                    (new SendTracingToApollo($this->config, [$trace]))
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
        return array_merge([
            'method' => Method::value($this->request->method()),
            'host' => $this->request->getHost(),
            'path' => $this->request->path(),
            'secure' => $this->request->secure(),
            'protocol' => $this->request->getProtocolVersion(),
        ], $this->config->get('lighthouse-apollo.include_request_headers') ? [
            'request_headers' => Arr::except(
                $this->request->headers->all(),
                $this->config->get('lighthouse-apollo.excluded_request_headers')
            ),
        ] : []);
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

    private function variables(): ?array
    {
        if (!$this->config->get('lighthouse-apollo.include_variables')) {
            return null;
        }

        $variables = [];
        /** @var string[] $only */
        $only = $this->config->get('lighthouse-apollo.variables_only_names');
        /** @var string[] $except */
        $except = $this->config->get('lighthouse-apollo.variables_except_names');
        foreach ($this->graphQlRequest->variables() as $key => $value) {
            if (
                (count($only) > 0 && !in_array($key, $only, true)) ||
                (count($except) > 0 && in_array($key, $except, true))
            ) {
                // Special case for private variables. Note that this is a different
                // representation from a variable containing the empty string, as that
                // will be sent as '""'.
                $value = '';
            } else {
                try {
                    $value = json_encode($value, JSON_THROW_ON_ERROR);
                } catch (JsonException $e) {
                    $value = '"[Unable to convert value to JSON]"';
                }
            }

            $variables[$key] = $value;
        }

        return $variables;
    }
}
