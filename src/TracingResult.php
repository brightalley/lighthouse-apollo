<?php

namespace BrightAlley\LighthouseApollo;

use DateTime;
use Exception;
use Google\Protobuf\Timestamp;
use Mdg\Trace;

class TracingResult
{
    public string $queryText;

    public array $client;

    public array $http;

    /**
     * @var array{
     *     duration: int,
     *     endTime: string,
     *     startTime: string,
     *     version: int,
     *     execution: array{
     *         resolvers: array{
     *             duration: int,
     *             fieldName: string,
     *             parentType: string,
     *             path: (int|string)[],
     *             returnType: string,
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

    /**
     * Convert Lighthouse's tracing results to a tree structure that Apollo Studio understands.
     *
     * @return Trace
     * @throws Exception
     */
    public function getTracingAsProtobuf(): Trace
    {
        // Lighthouse's format is not fully compatible with what Apollo expects.
        // In particular, Apollo expects a sort of tree structure, whereas Lighthouse produces a flat array.
        $tracingData = [
            'duration_ns' => $this->tracing['duration'],
            'end_time' => $this->dateTimeStringToTimestampField($this->tracing['endTime']),
            'http' => new Trace\HTTP($this->http),
            'start_time' => $this->dateTimeStringToTimestampField($this->tracing['startTime']),
        ];
        if (!empty($this->client['address'])) {
            $tracingData['client_address'] = $this->client['address'];
        }
        if (!empty($this->client['name'])) {
            $tracingData['client_name'] = $this->client['name'];
        }
        if (!empty($this->client['reference_id'])) {
            $tracingData['client_reference_id'] = $this->client['reference_id'];
        }
        if (!empty($this->client['version'])) {
            $tracingData['client_version'] = $this->client['version'];
        }
        $result = new Trace($tracingData);

        foreach ($this->tracing['execution']['resolvers'] as $trace) {
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
                [$this, 'getErrorAsProtobuf'],
                array_filter($this->errors, function (array $error) use ($trace) {
                    return isset($error['path']) && $error['path'] === $trace['path'];
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
            [$this, 'getErrorAsProtobuf'],
            array_filter($this->errors, function (array $error) {
                return empty($error['path']) || empty($this->tracing['execution']['resolvers']);
            })
        );
        /** @var Trace\Node|null $rootNode */
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
     * @param array $error
     * @return Trace\Error
     */
    public static function getErrorAsProtobuf(array $error): Trace\Error
    {
        return new Trace\Error([
            'message' => $error['debugMessage'] ?? $error['message'],
            'location' => array_map(function (array $location) {
                return new Trace\Location($location);
            }, $error['locations'] ?? []),
            'json' => json_encode($error, JSON_THROW_ON_ERROR),
        ]);
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
}
