<?php

namespace BrightAlley\LighthouseApollo\Actions;

use BrightAlley\LighthouseApollo\Exceptions\SendTracingRequestFailedException;
use BrightAlley\LighthouseApollo\Exceptions\SendTracingRequestInvalidResponseCode;
use BrightAlley\LighthouseApollo\TracingResult;
use DateTime;
use Exception;
use Google\Protobuf\Timestamp;
use GraphQL\Error\Error;
use Illuminate\Contracts\Config\Repository as Config;
use Mdg\Report;
use Mdg\ReportHeader;
use Mdg\Trace;
use Mdg\TracesAndStats;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;

class SendTracingToApollo
{
    /**
     * @var TracingResult[]
     */
    private array $tracing;

    private Config $config;

    private SchemaSourceProvider $schemaSourceProvider;

    /**
     * Constructor.
     *
     * @param Config $config
     * @param SchemaSourceProvider $schemaSourceProvider
     * @param TracingResult[] $tracing
     */
    public function __construct(Config $config, SchemaSourceProvider $schemaSourceProvider, array $tracing)
    {
        $this->config = $config;
        $this->schemaSourceProvider = $schemaSourceProvider;
        $this->tracing = $tracing;
    }

    /**
     * Send the traces to Apollo Studio.
     *
     * @throws Exception
     * @throws SendTracingRequestInvalidResponseCode
     * @throws SendTracingRequestFailedException
     */
    public function send(): void
    {
        // Convert tracings to map of query signature => traces.
        $tracesPerQuery = [];
        foreach ($this->tracing as $trace) {
            $querySignature = $this->normalizeQuery($trace->queryText);
            if (!isset($tracesPerQuery[$querySignature])) {
                $tracesPerQuery[$querySignature] = [];
            }

            $tracesPerQuery[$querySignature][] = $this->transformTracing($trace);
        }

        $tracesPerQuery = array_map(static function (array $tracesAndStats): TracesAndStats {
            return new TracesAndStats(['trace' => $tracesAndStats]);
        }, $tracesPerQuery);

        $body = new Report([
            'header' => new ReportHeader([
                'agent_version' => '1.0',
                'hostname' => $this->config->get('lighthouse-apollo.hostname'),
                'runtime_version' => 'PHP ' . PHP_VERSION,
                'schema_tag' => $this->config->get('lighthouse-apollo.apollo_graph_variant'),
                'uname' => php_uname(),
            ]),
            'traces_per_query' => $tracesPerQuery,
        ]);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => gzencode($body->serializeToString()),
            CURLOPT_HTTPHEADER => [
                'Content-Encoding: gzip',
                'X-Api-Key: ' . $this->config->get('lighthouse-apollo.apollo_key'),
                'User-Agent: Lighthouse-Apollo',
            ],
        ];

        $request = curl_init($this->config->get('lighthouse-apollo.tracing_endpoint'));
        curl_setopt_array($request, $options);
        $responseText = curl_exec($request);

        $errorNumber = curl_errno($request);
        if ($errorNumber) {
            throw new SendTracingRequestFailedException($errorNumber, curl_error($request));
        }

        $result = curl_getinfo($request);
        if ($result['http_code'] < 200 || $result['http_code'] > 299) {
            throw new SendTracingRequestInvalidResponseCode($result['http_code'], $responseText);
        }
    }

    /**
     * Convert Lighthouse's tracing results to a tree structure that Apollo Studio understands.
     *
     * @param TracingResult $tracingResult
     * @return Trace
     * @throws Exception
     */
    private function transformTracing(TracingResult $tracingResult): Trace
    {
        // Lighthouse's format is not fully compatible with what Apollo expects.
        // In particular, Apollo expects a sort of tree structure, whereas Lighthouse produces a flat array.
        $result = new Trace([
            'client_address' => $tracingResult->client['address'],
            'client_name' => $tracingResult->client['name'],
            'client_reference_id' => $tracingResult->client['reference_id'],
            'client_version' => $tracingResult->client['version'],
            'duration_ns' => $tracingResult->tracing['duration'],
            'end_time' => $this->dateTimeStringToTimestampField($tracingResult->tracing['endTime']),
            'http' => new Trace\HTTP($tracingResult->http),
            'start_time' => $this->dateTimeStringToTimestampField($tracingResult->tracing['startTime']),
        ]);

        foreach ($tracingResult->tracing['execution']['resolvers'] as $trace) {
            $node = new Trace\Node([
                'end_time' => $trace['startOffset'] + $trace['duration'],
                'original_field_name' => $trace['fieldName'],
                'parent_type' => $trace['parentType'],
                'response_name' => $trace['path'][count($trace['path']) - 1],
                'start_time' => $trace['startOffset'],
                'type' => $trace['returnType'],
            ]);

            // Add any errors with a matching path.
            $errors = array_map(
                [$this, 'transformError'],
                array_filter($tracingResult->errors, function (Error $error) use ($trace) {
                    return $error->path === $trace['path'];
                })
            );
            if (count($errors) > 0) {
                $node->setError($errors);
            }

            if (count($trace['path']) === 1) {
                $result->setRoot($node);
            } else {
                /** @var Trace\Node $target */
                $target = $result->getRoot();
                foreach (array_slice($trace['path'], 1, -1) as $pathSegment) {
                    if ($pathSegment === 0) {
                        $matchingIndex = null;
                        foreach ($target->getChild() as $child) {
                            if ($child->getIndex() === $pathSegment) {
                                $matchingIndex = $child;
                                break;
                            }
                        }

                        if ($matchingIndex !== null) {
                            $target = $matchingIndex;
                        } else {
                            $child = $target->getChild();
                            $indexNode = new Trace\Node(['index' => $pathSegment]);
                            $target->setChild(array_merge($this->iteratorToArray($child), [$indexNode]));

                            $target = $indexNode;
                        }
                    } else {
                        /** @var Trace\Node $child */
                        foreach ($target->getChild() as $child) {
                            if ($child->getResponseName() === $pathSegment) {
                                $target = $child;
                                break;
                            }
                        }
                    }
                }

                $child = $target->getChild();
                $target->setChild(array_merge($this->iteratorToArray($child), [$node]));
            }
        }

        // Add all errors without a path to the root node.
        /** @var Trace\Error[] $rootErrors */
        $rootErrors = array_map(
            [$this, 'transformError'],
            array_filter($tracingResult->errors, function (Error $error) {
                return empty($error->path);
            })
        );
        $rootNode = $result->getRoot();
        if ($rootNode === null) {
            $result->setRoot($rootNode = new Trace\Node([
                'response_name' => '_errors',
            ]));
            $rootNode->setError($rootErrors);
        } else {
            $existingRootErrors = $rootNode->getError();
            $rootNode->setError(array_merge($this->iteratorToArray($existingRootErrors), $rootErrors));
        }

        return $result;
    }

    /**
     * Convert a GraphQL error object to a Protobuf error object.
     *
     * @param Error $error
     * @return Trace\Error
     */
    private function transformError(Error $error): Trace\Error
    {
        return new Trace\Error([
            'message' => $error->message,
            'json' => json_encode($error->jsonSerialize()),
        ]);
    }

    /**
     * Try to "normalize" the GraphQL query, by stripping whitespace. This function could be made
     * more intelligent in the future. Also adds the required "# OperationName" on the first line
     * before the rest of the query.
     *
     * @param string $query
     * @return string
     */
    private function normalizeQuery(string $query): string
    {
        $trimmed = trim(preg_replace('/[\r\n\s]+/', ' ', $query));
        if (preg_match('/^(?:query|mutation) ([\w]+)/', $query, $matches)) {
            return "# ${matches[1]}\n$trimmed";
        }

        $hash = sha1($trimmed);
        return "# ${hash}\n$trimmed";
    }

    /**
     * Turn an iterable into an array.
     *
     * @template T
     * @param iterable<T> $iter
     * @return array<T>
     */
    private function iteratorToArray(iterable $iter): array
    {
        $result = [];
        foreach ($iter as $element) {
            $result[] = $element;
        }

        return $result;
    }

    /**
     * Create a new Protobuf timestamp field from the given datetime string, which was produced
     * earlier by Lighthouse.
     *
     * @param string $dateTime
     * @return Timestamp
     * @throws Exception
     */
    private function dateTimeStringToTimestampField(string $dateTime): Timestamp
    {
        $timestamp = new Timestamp();
        $timestamp->fromDateTime(new DateTime($dateTime));

        return $timestamp;
    }
}
