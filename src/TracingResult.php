<?php

namespace BrightAlley\LighthouseApollo;

use GraphQL\Error\Error;

class TracingResult
{
    public string $queryText;

    public array $client;

    public array $http;

    /**
     * @var array{
     *     duration: int
     *     endTime: string
     *     startTime: string
     *     version: int
     *     execution: array{
     *         resolvers: array{
     *             duration: int
     *             parentType: string
     *             path: string
     *             returnType: string
     *             startOffset: int
     *         }[]
     *     }
     * }
     */
    public array $tracing;

    /**
     * @var array
     */
    public array $errors;

    /**
     * Constructor.
     *
     * @param string $queryText
     * @param array $client
     * @param array $http
     * @param array $tracing
     * @param array $errors
     */
    public function __construct(string $queryText, array $client, array $http, array $tracing, array $errors)
    {
        $this->queryText = $queryText;
        $this->client = $client;
        $this->http = $http;
        $this->tracing = $tracing;
        $this->errors = $errors;
    }
}
