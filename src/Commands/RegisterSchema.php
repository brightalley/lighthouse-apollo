<?php

namespace BrightAlley\LighthouseApollo\Commands;

use BrightAlley\LighthouseApollo\Exceptions\ConfigurationException;
use BrightAlley\LighthouseApollo\Exceptions\RegisterSchemaFailedException;
use BrightAlley\LighthouseApollo\Exceptions\RegisterSchemaRequestFailedException;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Console\Command;
use Illuminate\Support\Str;
use JsonException;
use LogicException;
use Nuwave\Lighthouse\Schema\Source\SchemaSourceProvider;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class RegisterSchema extends Command
{
    private const MUTATION = <<<'EOT'
mutation ReportServerInfo($info: EdgeServerInfo!, $executableSchema: String) {
  me {
    __typename
    ... on ServiceMutation {
      reportServerInfo(info: $info, executableSchema: $executableSchema) {
        __typename
        ... on ReportServerInfoError {
          message
          code
        }
        ... on ReportServerInfoResponse {
          inSeconds
          withExecutableSchema
        }
      }
    }
  }
}
EOT;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'lighthouse-apollo:register-schema';

    /**
     * The console command description.
     *
     * @var string|null
     */
    protected $description = 'Send the current schema to Apollo Studio';

    private Config $config;

    private SchemaSourceProvider $schemaSourceProvider;

    /**
     * Create a new console command instance.
     *
     * @param Config $config
     * @param SchemaSourceProvider $schemaSourceProvider
     */
    public function __construct(Config $config, SchemaSourceProvider $schemaSourceProvider)
    {
        parent::__construct();

        $this->config = $config;
        $this->schemaSourceProvider = $schemaSourceProvider;
    }

    /**
     * Execute the console command.
     *
     * @throws ConfigurationException
     * @throws JsonException
     * @throws RegisterSchemaFailedException
     * @throws RegisterSchemaRequestFailedException
     */
    public function handle(): void
    {
        $schemaString = $this->schemaSourceProvider->getSchemaString();
        $variables = [
            'info' => [
                'bootId' => Str::uuid()->toString(),
                'executableSchemaId' => hash('sha256', $schemaString),
                'graphVariant' => $this->config->get('lighthouse-apollo.apollo_graph_variant'),
            ],
            'executableSchema' => null,
        ];

        $this->trySend($schemaString, $variables);
    }

    /**
     * @param array $variables
     * @return array
     * @throws RegisterSchemaRequestFailedException
     * @throws JsonException
     */
    protected function sendSchemaToApollo(array $variables): array
    {
        $request = curl_init($this->config->get('lighthouse-apollo.schema_reporting_endpoint'));
        curl_setopt_array($request, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode([
                'query' => self::MUTATION,
                'operationName' => 'ReportServerInfo',
                'variables' => $variables,
            ], JSON_THROW_ON_ERROR),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Api-Key: ' . $this->config->get('lighthouse-apollo.apollo_key'),
                'User-Agent: Lighthouse-Apollo',
            ],
        ]);

        $response = curl_exec($request);

        $errorCode = curl_errno($request);
        if ($errorCode) {
            throw new RegisterSchemaRequestFailedException($errorCode, curl_error($request));
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    /**
     * @param string $schemaString
     * @param array $variables
     * @param bool $waited
     * @throws ConfigurationException
     * @throws JsonException
     * @throws RegisterSchemaFailedException
     * @throws RegisterSchemaRequestFailedException
     */
    protected function trySend(string $schemaString, array $variables, bool $waited = false): void
    {
        $data = $this->sendSchemaToApollo($variables);
        if (!empty($data['errors'])) {
            throw new RegisterSchemaFailedException(implode(', ', array_map(function ($error) {
                return $error['message'];
            }, $data['errors'])));
        }

        if (empty($data) || !isset($data['data']['me']['__typename'])) {
            throw new LogicException(
                'Invalid response when registering schema with Apollo. Data received: ' . var_export($data, true)
            );
        }

        $data = $data['data'];
        if ($data['me']['__typename'] === 'UserMutation') {
            throw new ConfigurationException(
                'This server was configured with an API key for a user. ' .
                "Only a service's API key may be used for schema reporting. " .
                'Please visit the settings for this graph at ' .
                'https://studio.apollographql.com/ to obtain an API key for a service.'
            );
        }

        if ($data['me']['__typename'] === 'ServiceMutation' && isset($data['me']['reportServerInfo'])) {
            if ($data['me']['reportServerInfo']['__typename'] === 'ReportServerInfoResponse') {
                if ($waited) {
                    // I guess this means we're done now!
                    $this->output->writeln('Schema in Apollo Studio is up-to-date.');

                    return;
                }

                $this->output->writeln(
                    'Sending schema to Apollo Studio in ' . $data['me']['reportServerInfo']['inSeconds'] . ' seconds'
                );

                $progress = $this->output->createProgressBar($data['me']['reportServerInfo']['inSeconds']);
                for ($i = 0; $i < $data['me']['reportServerInfo']['inSeconds']; ++$i) {
                    sleep(1);

                    $progress->advance();
                }

                $progress->finish();

                $this->output->writeln('');
                $this->output->writeln('Sending schema now.');
                $this->trySend($schemaString, array_merge($variables, [
                    'executableSchema' => $data['me']['reportServerInfo']['withExecutableSchema']
                        ? $schemaString
                        : null,
                ]), true);

                return;
            }

            throw new RegisterSchemaFailedException(
                'Received input validation error from Apollo: ' . $data['me']['reportServerInfo']['message'],
                $data['me']['reportServerInfo']['code']
            );
        }

        throw new LogicException(
            'Invalid response when registering schema with Apollo. Data received: ' . var_export($data, true)
        );
    }
}
