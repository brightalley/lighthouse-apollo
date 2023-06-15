<?php

namespace BrightAlley\LighthouseApollo\Listeners;

use BrightAlley\LighthouseApollo\Actions\SendTracingToApollo;
use BrightAlley\LighthouseApollo\Connectors\RedisConnector;
use BrightAlley\LighthouseApollo\Contracts\ClientInformationExtractor;
use BrightAlley\LighthouseApollo\Exceptions\InvalidTracingSendMode;
use BrightAlley\LighthouseApollo\QueryRequestStack;
use BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder;
use BrightAlley\LighthouseApollo\TracingResult;
use Exception;
use GraphQL\Error\DebugFlag;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Language\Printer;
use GraphQL\Type\Schema;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Container\Container;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use JsonException;
use LogicException;
use Mdg\Trace\HTTP\Method;
use Nuwave\Lighthouse\Events\ManipulateResult;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Schema\SchemaBuilder;

/**
 * @psalm-import-type TracingClient from TraceTreeBuilder
 * @psalm-import-type TracingError from TraceTreeBuilder
 * @psalm-import-type TracingHttp from TraceTreeBuilder
 */
class ManipulateResultListener
{
    public const DEBUG_FLAGS =
        DebugFlag::INCLUDE_DEBUG_MESSAGE | DebugFlag::INCLUDE_TRACE;

    private ClientInformationExtractor $clientInformationExtractor;

    private Config $config;

    private Container $container;

    private Request $request;

    private QueryRequestStack $requestStack;

    private SchemaBuilder $schemaBuilder;

    /**
     * Constructor.
     *
     * @param ClientInformationExtractor $clientInformationExtractor
     * @param Config $config
     * @param Container $container
     * @param QueryRequestStack $requestStack
     * @param Request $request
     */
    public function __construct(
        ClientInformationExtractor $clientInformationExtractor,
        Config $config,
        Container $container,
        QueryRequestStack $requestStack,
        Request $request,
        SchemaBuilder $schemaBuilder
    ) {
        $this->clientInformationExtractor = $clientInformationExtractor;
        $this->config = $config;
        $this->container = $container;
        $this->requestStack = $requestStack;
        $this->request = $request;
        $this->schemaBuilder = $schemaBuilder;
    }

    /**
     * Handle the event at the end of a GraphQL request.
     *
     * @param ManipulateResult $event
     * @throws InvalidTracingSendMode
     */
    public function handle(ManipulateResult $event): void
    {
        $currentQuery = $this->requestStack->current();
        if ($currentQuery === null) {
            return;
        }

        $queryText = Printer::doPrint($currentQuery->query->cloneDeep());
        if (
            !$this->config->get('lighthouse-apollo.apollo_key') ||
            $this->isIntrospectionQuery($queryText)
        ) {
            return;
        }

        if (!isset($event->result->extensions['tracing'])) {
            return;
        }

        $trace = new TracingResult(
            $currentQuery->query,
            $queryText,
            $this->variables($currentQuery),
            $currentQuery->operationName,
            $this->extractClientInformation(),
            $this->extractHttpInformation(),
            $event->result->extensions['tracing'],
            array_map(static function (Error $error) {
                return FormattedError::createFromException(
                    $error,
                    self::DEBUG_FLAGS,
                );
            }, $event->result->errors),
        );

        $this->removeTracingFromExtensionsIfNeeded($event);

        $tracingSendMode = (string) $this->config->get(
            'lighthouse-apollo.send_tracing_mode',
        );
        switch ($tracingSendMode) {
            case 'sync':
                try {
                    (new SendTracingToApollo($this->config, [$trace]))->send(
                        $this->schemaBuilder,
                    );
                } catch (Exception $e) {
                    // We should probably not cause pain for the end users. Just include this in the extensions instead.
                    $event->result->errors[] = new Error(
                        'Failed to send tracing to Apollo',
                        null,
                        null,
                        [],
                        null,
                        $e,
                    );
                }
                break;
            case 'redis':
                /** @var RedisConnector $redisConnector */
                $redisConnector = $this->container->make(RedisConnector::class);
                $redisConnector->put($trace);
                break;
            case 'database':
                throw new LogicException('Not yet implemented.');
            default:
                throw new InvalidTracingSendMode($tracingSendMode);
        }
    }

    /**
     * @psalm-return TracingClient
     */
    private function extractClientInformation(): array
    {
        return [
            'name' => $this->clientInformationExtractor->getClientName(),
            'version' => $this->clientInformationExtractor->getClientVersion(),
        ];
    }

    /**
     * @psalm-return TracingHttp
     */
    private function extractHttpInformation(): array
    {
        // These keys should correspond with Protobuf's HTTP object.
        /** {@see \Mdg\Trace\HTTP} */
        return array_merge(
            [
                'method' => (int) Method::value($this->request->method()),
                'host' => $this->request->getHost(),
                'path' => $this->request->path(),
                'secure' => $this->request->secure(),
                'protocol' => $this->request->getProtocolVersion() ?? 'unknown',
            ],
            $this->config->get('lighthouse-apollo.include_request_headers')
                ? [
                    'request_headers' => $this->getRequestHeaders(),
                ]
                : [],
        );
    }

    private function removeTracingFromExtensionsIfNeeded(
        ManipulateResult $event
    ): void {
        if ($this->config->get('lighthouse-apollo.mute_tracing_extensions') && $event->result->extensions) {
            unset($event->result->extensions['tracing']);
        }
    }

    private function isIntrospectionQuery(string $query): bool
    {
        return (bool) preg_match(
            '/^\s*query[^{]*{\s*(\w+:\s*)?__schema\s*{/',
            $query,
        );
    }

    private function variables(StartExecution $execution): ?array
    {
        if (!$this->config->get('lighthouse-apollo.include_variables')) {
            return null;
        }

        $variables = [];
        /** @var string[] $only */
        $only = $this->config->get('lighthouse-apollo.variables_only_names');
        /** @var string[] $except */
        $except = $this->config->get(
            'lighthouse-apollo.variables_except_names',
        );

        /**
         * @var string|int $key
         * @var mixed $value
         */
        foreach ($execution->variables ?? [] as $key => $value) {
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

    /**
     * @return array<string, (string|null)[]>
     */
    private function getRequestHeaders(): array
    {
        return Arr::except(
            $this->request->headers->all(),
            $this->config->get('lighthouse-apollo.excluded_request_headers'),
        );
    }
}
