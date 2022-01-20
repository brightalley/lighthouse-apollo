<?php

namespace BrightAlley\LighthouseApollo;

use DateTime;
use Exception;
use Google\Protobuf\Timestamp;
use LogicException;
use Mdg\Trace;

class TracingResult
{
    public string $queryText;

    public ?array $variables;

    public ?string $operationName;

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
     * @param array|null $variables
     * @param string|null $operationName
     * @param array $client
     * @param array $http
     * @param array $tracing
     * @param array $errors
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
        // Lighthouse's format is not fully compatible with what Apollo expects.
        // In particular, Apollo expects a sort of tree structure, whereas Lighthouse produces a flat array.
        $tracingData = [
            'duration_ns' => $this->tracing['duration'],
            'end_time' => $this->dateTimeStringToTimestampField(
                $this->tracing['endTime'],
            ),
            'http' => $this->getHttpAsProtobuf(),
            'start_time' => $this->dateTimeStringToTimestampField(
                $this->tracing['startTime'],
            ),
        ];
        if (!empty($this->client['address'])) {
            $tracingData['client_address'] = $this->client['address'];
        }
        if (!empty($this->client['name'])) {
            $tracingData['client_name'] = $this->client['name'];
        }
        if (!empty($this->client['version'])) {
            $tracingData['client_version'] = $this->client['version'];
        }
        if ($this->variables !== null) {
            $tracingData['details'] = new Trace\Details([
                'variables_json' => $this->variables,
            ]);
        }

        $result = new Trace($tracingData);
        /** @var array<string,Trace\Node> $pathTargets */
        $pathTargets = [];

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
                array_filter($this->errors, function (array $error) use (
                    $trace
                ) {
                    return isset($error['path']) &&
                        $error['path'] === $trace['path'];
                }),
            );
            if (count($errors) > 0) {
                $node->setError($errors);
            }

            $selfPathKey = implode('.', $trace['path']);
            if (count($trace['path']) === 1) {
                $result->setRoot($node);
            } else {
                $directParent = $trace['path'][count($trace['path']) - 2];
                $parentPathKey = implode(
                    '.',
                    is_numeric($directParent)
                        ? array_slice($trace['path'], 0, -2)
                        : array_slice($trace['path'], 0, -1),
                );

                // It seems that in some cases, a node is missing in Lighthouse's tracings. Not sure if that's a
                // bug or if it's on purpose. In any case, we need to deal with that to insert the missing node.
                if (!isset($pathTargets[$parentPathKey])) {
                    // First, find all segments of the path that are missing in pathTargets. Keep track of the list
                    // of missing nodes, and where to start adding them (the closest existing parent). Usually this
                    // will end up being one node higher than the direct parent.
                    /** @var array<string,string> $missingNodes */
                    $missingNodes = [];
                    $addNodesTo = $result->getRoot();
                    for (
                        $i =
                            count($trace['path']) -
                            (is_numeric($directParent) ? 2 : 1);
                        $i > ($addNodesTo === null ? 0 : 1);
                        --$i
                    ) {
                        $partialPath = implode(
                            '.',
                            array_slice($trace['path'], 0, $i),
                        );
                        if (!isset($pathTargets[$partialPath])) {
                            // This is like array_unshift, but keeping the array keys intact.
                            $missingNodes =
                                [$partialPath => $trace['path'][$i - 1]] +
                                $missingNodes;
                        } else {
                            $addNodesTo = $pathTargets[$partialPath];
                            break;
                        }
                    }

                    // Then just iterate over the missing nodes and add them.
                    foreach ($missingNodes as $path => $missingNode) {
                        if (is_numeric($missingNode)) {
                            throw new LogicException(
                                'The missing node should never be numeric.',
                            );
                        }

                        $nodeToInsert = new Trace\Node([
                            'response_name' => $missingNode,
                        ]);
                        if ($addNodesTo === null) {
                            $result->setRoot($nodeToInsert);
                        } else {
                            $addNodesTo->getChild()[] = $nodeToInsert;
                        }

                        $pathTargets[$path] = $nodeToInsert;
                        $addNodesTo = $nodeToInsert;
                    }
                }

                /** @var Trace\Node $target */
                $target = $pathTargets[$parentPathKey];

                // If the node is part of a list, find the correct index node. Note that there are
                // no entries in the tracing from Lighthouse for the individual list elements, that's
                // why they need to be resolved separately from the pathTargets lookup, as there would
                // be no entry in the pathTargets lookup table for the direct parent of this node.
                if (is_numeric($directParent)) {
                    $matchingIndex = null;
                    foreach ($target->getChild() as $child) {
                        if ($child->getIndex() === $directParent) {
                            $matchingIndex = $child;
                            break;
                        }
                    }

                    if ($matchingIndex !== null) {
                        $target = $matchingIndex;
                    } else {
                        $indexNode = new Trace\Node(['index' => $directParent]);
                        $target->getChild()[] = $indexNode;

                        $target = $indexNode;
                    }
                }

                $target->getChild()[] = $node;
            }

            // Finally, store this node in the lookup for any descendents.
            $pathTargets[$selfPathKey] = $node;
        }

        // Add all errors without a path to the root node.
        /** @var Trace\Error[] $rootErrors */
        $rootErrors = array_map(
            [$this, 'getErrorAsProtobuf'],
            array_filter($this->errors, function (array $error) {
                return empty($error['path']) ||
                    empty($this->tracing['execution']['resolvers']);
            }),
        );
        /** @var Trace\Node|null $rootNode */
        $rootNode = $result->getRoot();
        if ($rootNode === null) {
            $result->setRoot(
                $rootNode = new Trace\Node([
                    'response_name' => '_errors',
                ]),
            );
            $rootNode->setError($rootErrors);
        } else {
            foreach ($rootErrors as $rootError) {
                $rootNode->getError()[] = $rootError;
            }
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

    protected function getHttpAsProtobuf(): Trace\HTTP
    {
        $http = $this->http;
        if (isset($http['request_headers'])) {
            $http['request_headers'] = array_map(function (array $values) {
                return new Trace\HTTP\Values(['value' => $values]);
            }, $http['request_headers']);
        }

        return new Trace\HTTP($http);
    }
}
