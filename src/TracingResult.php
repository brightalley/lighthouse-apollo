<?php

namespace BrightAlley\LighthouseApollo;

use BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder;
use Exception;
use Mdg\Trace;

/**
 * @psalm-import-type TracingClient from TraceTreeBuilder
 * @psalm-import-type TracingError from TraceTreeBuilder
 * @psalm-import-type TracingHttp from TraceTreeBuilder
 * @psalm-import-type TracingInfo from TraceTreeBuilder
 */
class TracingResult
{
    public string $queryText;

    public ?array $variables;

    public ?string $operationName;

    /**
     * @psalm-var TracingClient
     */
    public array $client;

    /**
     * @psalm-var TracingHttp
     */
    public array $http;

    /**
     * @psalm-var TracingInfo
     */
    public array $tracing;

    /**
     * @psalm-var TracingError[]
     */
    public array $errors;

    /**
     * Constructor.
     *
     * @psalm-param TracingClient $client
     * @psalm-param TracingHttp $http
     * @psalm-param TracingInfo $tracing
     * @psalm-param TracingError[] $errors
     */
    public function __construct(
        string $queryText,
        ?array $variables,
        ?string $operationName,
        array $client,
        array $http,
        array $tracing,
        array $errors
    ) {
        $this->queryText = $queryText;
        $this->variables = $variables;
        $this->operationName = $operationName;
        $this->client = $client;
        $this->http = $http;
        $this->tracing = $tracing;
        $this->errors = $errors;
    }

    /**
     * Convert Lighthouse's tracing results to a tree structure that Apollo Studio understands.
     *
     * @return Trace
     * @throws Exception
     */
    public function getTracingAsProtobuf(): Trace
    {
        $treeBuilder = new TraceTreeBuilder();
        $treeBuilder->applyClient($this->client);
        $treeBuilder->applyHttp($this->http);
        $treeBuilder->applyTracing($this->tracing);
        $treeBuilder->addErrors($this->errors);
        if ($this->variables !== null) {
            $treeBuilder->applyVariables($this->variables);
        }

        return $treeBuilder->trace;
    }
}
