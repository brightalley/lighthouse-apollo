<?php

namespace BrightAlley\LighthouseApollo\Actions;

use BrightAlley\LighthouseApollo\Exceptions\SendTracingRequestFailedException;
use BrightAlley\LighthouseApollo\Exceptions\SendTracingRequestInvalidResponseCode;
use BrightAlley\LighthouseApollo\TracingResult;
use DateTime;
use Exception;
use Google\Protobuf\Timestamp;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Support\Arr;
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
            $querySignature = $this->normalizeQuery($trace->queryText, $trace->operationName);
            if (!isset($tracesPerQuery[$querySignature])) {
                $tracesPerQuery[$querySignature] = [];
            }

            $tracesPerQuery[$querySignature][] = $trace->getTracingAsProtobuf();
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
     * Try to "normalize" the GraphQL query, by stripping whitespace. This function could be made
     * more intelligent in the future. Also adds the required "# OperationName" on the first line
     * before the rest of the query.
     *
     * @param string $query
     * @param string|null $operationName
     * @return string
     */
    private function normalizeQuery(string $query, ?string $operationName): string
    {
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
}
