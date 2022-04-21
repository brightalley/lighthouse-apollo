<?php

namespace BrightAlley\LighthouseApollo\Tracing;

use DateTime;
use Google\Protobuf\Timestamp;
use JsonException;
use Mdg\Trace;
use Mdg\Trace\Error;
use Mdg\Trace\Node;

/**
 * @psalm-type TracingPath = (int|string)[]
 *
 * @psalm-type TracingClient = array{
 *     name: string|null,
 *     version: string|null
 * }
 * @psalm-type TracingError = array{
 *     debugMessage?: string,
 *     locations: array{line: int, column: int}[],
 *     message: string,
 *     path: TracingPath
 * }
 * @psalm-type TracingHttp = array{
 *     request_headers?: array<string, (string|null)[]>,
 *     method: int,
 *     host: string,
 *     path: string,
 *     secure: bool,
 *     protocol: string
 * }
 * @psalm-type ResolverInfo = array{
 *     duration: int,
 *     fieldName: string,
 *     parentType: string,
 *     path: TracingPath,
 *     returnType: string,
 *     startOffset: int
 * }
 * @psalm-type TracingInfo = array{
 *     duration: int,
 *     endTime: string,
 *     startTime: string,
 *     version: int,
 *     execution: array{
 *         resolvers: ResolverInfo[]
 *     }
 * }
 */
class TraceTreeBuilder
{
    private Node $rootNode;
    public Trace $trace;
    /** @var array<string, Node> */
    private array $nodes;

    public function __construct()
    {
        $this->rootNode = new Node();
        $this->trace = new Trace(['root' => $this->rootNode]);
        $this->nodes = [
            $this->responsePathAsString() => $this->rootNode,
        ];
    }

    /**
     * @psalm-param TracingClient $client
     */
    public function applyClient(array $client): void
    {
        if ($client['name'] !== null) {
            $this->trace->setClientName($client['name']);
        }
        if ($client['version'] !== null) {
            $this->trace->setClientVersion($client['version']);
        }
    }

    /**
     * @psalm-param TracingHttp $http
     */
    public function applyHttp(array $http): void
    {
        if (isset($http['request_headers'])) {
            $http['request_headers'] = array_map(static function (
                array $values
            ) {
                return new Trace\HTTP\Values(['value' => $values]);
            },
            $http['request_headers']);
        }

        $this->trace->setHttp(new Trace\HTTP($http));
    }

    /**
     * @psalm-param TracingInfo $tracing
     */
    public function applyTracing(array $tracing): void
    {
        $this->trace->setDurationNs($tracing['duration']);
        $this->trace->setEndTime(
            self::dateTimeStringToTimestampField($tracing['endTime']),
        );
        $this->trace->setStartTime(
            self::dateTimeStringToTimestampField($tracing['startTime']),
        );

        foreach ($tracing['execution']['resolvers'] as $trace) {
            $this->addResolvedField($trace);
        }
    }

    public function applyVariables(array $variables): void
    {
        $this->trace->setDetails(
            new Trace\Details([
                'variables_json' => $variables,
            ]),
        );
    }

    /**
     * @psalm-param ResolverInfo $info
     */
    public function addResolvedField(array $info): void
    {
        $path = $info['path'];
        $node = $this->newNode($path);
        $node->setType($info['returnType']);
        $node->setParentType($info['parentType']);
        $node->setStartTime($info['startOffset']);
        $node->setEndTime($info['startOffset'] + $info['duration']);
        if (
            !is_numeric($path[count($path) - 1]) &&
            $path[count($path) - 1] !== $info['fieldName']
        ) {
            // This field was aliased; send the original field name too (for FieldStats).
            $node->setOriginalFieldName($info['fieldName']);
        }
    }

    /**
     * @psalm-param TracingError[] $errors
     */
    public function addErrors(array $errors): void
    {
        foreach ($errors as $err) {
            // In terms of reporting, errors can be re-written by the user by
            // utilizing the `rewriteError` parameter.  This allows changing
            // the message or stack to remove potentially sensitive information.
            // Returning `null` will result in the error not being reported at all.
            $this->addProtobufError(
                $err['path'] ?? null,
                self::errorToProtobufError($err),
            );
        }
    }

    /**
     * @psalm-param TracingPath|null $path
     */
    private function addProtobufError(?array $path, Error $error): void
    {
        // By default, put errors on the root node.
        $node = $this->rootNode;
        // If a non-GraphQLError Error sneaks in here somehow with a non-array
        // path, don't crash.
        if (is_array($path)) {
            $specificNode =
                $this->nodes[$this->responsePathAsString($path)] ?? null;
            if ($specificNode) {
                $node = $specificNode;
            }
        }

        $node->getError()[] = $error;
    }

    /**
     * @psalm-param TracingPath $path
     */
    private function newNode(array $path): Trace\Node
    {
        $node = new Node();
        $id = $path[count($path) - 1];
        if (is_numeric($id)) {
            $node->setIndex((int) $id);
        } else {
            $node->setResponseName($id);
        }
        $this->nodes[$this->responsePathAsString($path)] = $node;
        $parentNode = $this->ensureParentNode($path);
        $parentNode->getChild()[] = $node;

        return $node;
    }

    /**
     * @psalm-param TracingPath $path
     */
    private function ensureParentNode(array $path): Node
    {
        $previousPath = array_slice($path, 0, -1);
        $parentPath = $this->responsePathAsString($previousPath);
        $parentNode = $this->nodes[$parentPath] ?? null;
        if ($parentNode) {
            return $parentNode;
        }
        // Because we set up the root path when creating $this->>nodes, we now know
        // that path.prev isn't undefined.
        return $this->newNode($previousPath);
    }

    /**
     * Convert from the linked-list ResponsePath format to a dot-joined
     * string. Includes the full path (field names and array indices).
     *
     * @psalm-param TracingPath|null $p
     */
    private function responsePathAsString(?array $p = null): string
    {
        if ($p === null) {
            return '';
        }

        return implode('.', $p);
    }

    /**
     * @psalm-param TracingError $error
     * @throws JsonException
     */
    public static function errorToProtobufError(array $error): Error
    {
        return new Error([
            'message' => $error['debugMessage'] ?? $error['message'],
            'location' => !empty($error['locations'])
                ? array_map(
                    static fn(array $location) => new Trace\Location($location),
                    $error['locations'],
                )
                : [],
            'json' => json_encode($error, JSON_THROW_ON_ERROR),
        ]);
    }

    /**
     * Create a new Protobuf timestamp field from the given datetime string, which was produced
     * earlier by Lighthouse.
     */
    private static function dateTimeStringToTimestampField(
        string $dateTime
    ): Timestamp {
        $timestamp = new Timestamp();
        $timestamp->fromDateTime(new DateTime($dateTime));

        return $timestamp;
    }
}
