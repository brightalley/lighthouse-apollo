<?php

namespace BrightAlley\LighthouseApollo\Actions;

use BrightAlley\LighthouseApollo\Exceptions\SendTracingRequestFailedException;
use BrightAlley\LighthouseApollo\Exceptions\SendTracingRequestInvalidResponseCode;
use BrightAlley\LighthouseApollo\TracingResult;
use Exception;
use Google\Protobuf\Internal\MapField;
use Google\Protobuf\Internal\Message;
use Google\Protobuf\Internal\RepeatedField;
use Illuminate\Contracts\Config\Repository as Config;
use LogicException;
use Mdg\Report;
use Mdg\ReportHeader;
use Mdg\Trace\Node;
use Mdg\TracesAndStats;
use ReflectionClass;

class SendTracingToApollo
{
    /**
     * @var array<int,TracingResult>
     */
    private array $tracing;

    private Config $config;

    /**
     * @var array<string,array<string,string>>
     */
    private array $messagePropertyGetters = [];

    /**
     * Constructor.
     *
     * @param Config $config
     * @param array<int,TracingResult> $tracing
     */
    public function __construct(Config $config, array $tracing)
    {
        $this->config = $config;
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
            $querySignature = $this->normalizeQuery(
                $trace->queryText,
                $trace->operationName,
            );
            if (!isset($tracesPerQuery[$querySignature])) {
                $tracesPerQuery[$querySignature] = [];
            }

            $tracesPerQuery[$querySignature][] = $trace->getTracingAsProtobuf();
        }

        $tracesPerQuery = array_map(static function (
            array $tracesAndStats
        ): TracesAndStats {
            return new TracesAndStats([
                'trace' => $tracesAndStats,
            ]);
        },
        $tracesPerQuery);

        $body = $this->getReportWithTraces($tracesPerQuery);
        $options = [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => gzencode($this->serializeProtobuf($body)),
            CURLOPT_HTTPHEADER => [
                'Content-Encoding: gzip',
                'X-Api-Key: ' .
                $this->config->get('lighthouse-apollo.apollo_key'),
                'User-Agent: Lighthouse-Apollo',
            ],
        ];

        $request = curl_init(
            $this->config->get('lighthouse-apollo.tracing_endpoint'),
        );
        curl_setopt_array($request, $options);
        $responseText = curl_exec($request);

        $errorNumber = curl_errno($request);
        if ($errorNumber) {
            throw new SendTracingRequestFailedException(
                $errorNumber,
                curl_error($request),
            );
        }

        $result = curl_getinfo($request);
        if ($result['http_code'] < 200 || $result['http_code'] > 299) {
            throw new SendTracingRequestInvalidResponseCode(
                $result['http_code'],
                $responseText,
            );
        }
    }

    /**
     * @param array<string,TracesAndStats> $tracesPerQuery
     * @return Report
     */
    public function getReportWithTraces(array $tracesPerQuery): Report
    {
        return new Report([
            'header' => new ReportHeader([
                'agent_version' => '1.0',
                'hostname' => $this->config->get('lighthouse-apollo.hostname'),
                'runtime_version' => 'PHP ' . PHP_VERSION,
                'graph_ref' =>
                    $this->config->get('lighthouse-apollo.apollo_graph_id') .
                    '@' .
                    $this->config->get(
                        'lighthouse-apollo.apollo_graph_variant',
                    ),
                'uname' => php_uname(),
            ]),
            'traces_per_query' => $tracesPerQuery,
        ]);
    }

    /**
     * Try to "normalize" the GraphQL query, by stripping whitespace. This function could be made
     * more intelligent in the future. Also adds the required "# OperationName" on the first line
     * before the rest of the query.
     *
     * @param string $query
     * @param string|null $operationName
     * @return string
     */
    public function normalizeQuery(
        string $query,
        ?string $operationName
    ): string {
        $trimmed = trim(preg_replace('/[\r\n\s]+/', ' ', $query));
        if ($operationName !== null) {
            return "# $operationName\n$trimmed";
        }

        if (preg_match('/^(?:query|mutation) ([\w]+)/', $query, $matches)) {
            return "# ${matches[1]}\n$trimmed";
        }

        $hash = sha1($trimmed);
        return "# ${hash}\n$trimmed";
    }

    private function serializeProtobuf(Report $report): string
    {
        // If protoc is installed, use that, as it's much faster than the pure PHP version.
        if (exec('protoc --version') !== false) {
            try {
                return $this->serializeProtobufWithProtoc($report);
            } catch (Exception $e) {
                // Fall back to PHP-serialization below.
            }
        }

        return $report->serializeToString();
    }

    /**
     * Using the protoc binary, serialize the given report to a binary representation.
     */
    private function serializeProtobufWithProtoc(Report $report): string
    {
        $basePath = dirname(__DIR__, 2);
        $protoPath = escapeshellarg($basePath . '/resources');
        $protoFile = escapeshellarg($basePath . '/resources/reports.proto');

        $process = proc_open(
            'protoc --encode=mdg.engine.proto.Report --proto_path=' .
                $protoPath .
                ' ' .
                $protoFile,
            [
                0 => ['pipe', 'rb'],
                1 => ['pipe', 'wb'],
                2 => ['pipe', 'wb'],
            ],
            $pipes,
        );
        if ($process === false) {
            throw new Exception('Failed to open process.');
        }

        // Write the report in a format that protoc will understand, then close stdin so protoc knows we're done.
        $this->writeProtobufValue($pipes[0], $report, true);
        fclose($pipes[0]);

        // Read the output.
        $contents = stream_get_contents($pipes[1]);
        $err = stream_get_contents($pipes[2]);

        // Close the stdout and stderr pipes and close the process. If the return code is something other
        // than 0, an error occurred, so throw an error.
        fclose($pipes[1]);
        fclose($pipes[2]);
        $return = proc_close($process);
        if ($return !== 0) {
            throw new LogicException(
                'protoc returned error code ' . $return . ':' . $err,
            );
        }

        return $contents;
    }

    /**
     * Write the given value in a protobuf like manner to the stdin of the process (a pipe resource).
     * Yes, this function is horrible. However, using protoc with this is at least 2x faster than the
     * native PHP serialization, so it seems worth it.
     *
     * @param resource $process
     * @param mixed $value
     * @param bool $isRootObject
     */
    private function writeProtobufValue(
        $process,
        $value,
        bool $isRootObject = false
    ): void {
        if (is_bool($value)) {
            fwrite($process, $value ? 'true' : 'false');
        } elseif (is_float($value) || is_int($value)) {
            fwrite($process, (string) $value);
        } elseif (is_string($value)) {
            fwrite(
                $process,
                json_encode(
                    $value,
                    JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES,
                ),
            );
        } elseif ($value instanceof RepeatedField) {
            fwrite($process, '[');
            $count = count($value);
            foreach ($value as $key => $item) {
                $this->writeProtobufValue($process, $item);
                fwrite($process, $key === $count - 1 ? '' : ',');
            }
            fwrite($process, ']');
        } elseif ($value instanceof Message) {
            if (!$isRootObject) {
                fwrite($process, '{');
            }

            $first = true;
            foreach ($this->getMessageProperties($value) as $name => $getter) {
                $propertyValue = $value->$getter();
                if (
                    $propertyValue === null ||
                    $propertyValue === '' ||
                    ($propertyValue instanceof RepeatedField &&
                        count($propertyValue) === 0)
                ) {
                    continue;
                }

                fwrite($process, ($first ? '' : ';') . $name . ': ');
                $this->writeProtobufValue($process, $propertyValue);

                $first = false;
            }
            if (!$isRootObject) {
                fwrite($process, '}');
            }
        } elseif ($value instanceof MapField) {
            fwrite($process, '[');
            $first = true;
            foreach ($value as $key => $child) {
                fwrite($process, ($first ? '' : ',') . '{');
                $first = false;

                fwrite($process, 'key: ');
                $this->writeProtobufValue($process, $key);

                fwrite($process, 'value: ');
                $this->writeProtobufValue($process, $child);

                fwrite($process, '}');
            }
            fwrite($process, ']');
        } else {
            throw new LogicException(
                'Unsupported value type: ' .
                    (is_object($value) ? get_class($value) : gettype($value)),
            );
        }
    }

    /**
     * @param Message $value
     * @return array<string,string>
     */
    private function getMessageProperties(Message $value): array
    {
        $class = get_class($value);
        if (isset($this->messagePropertyGetters[$class])) {
            return $this->messagePropertyGetters[$class];
        }

        $result = [];
        $reflection = new ReflectionClass($value);
        foreach ($reflection->getProperties() as $property) {
            // Should have a getter, otherwise it's not relevant for us.
            $getter = 'get' . str_replace('_', '', $property->getName());
            if (!$reflection->hasMethod($getter)) {
                continue;
            }

            // Special-case this "oneof" property.
            if ($value instanceof Node && $property->getName() === 'id') {
                continue;
            }

            $result[$property->getName()] = $getter;
        }

        return $this->messagePropertyGetters[$class] = $result;
    }
}
