<?php

namespace BrightAlley\LighthouseApollo\Commands;

use BrightAlley\LighthouseApollo\Exceptions\RegisterSchemaFailedException;
use BrightAlley\LighthouseApollo\Exceptions\RegisterSchemaRequestFailedException;
use BrightAlley\LighthouseApollo\Graph\GitContextInput;
use BrightAlley\LighthouseApollo\Graph\UploadSchemaVariables;
use CzProject\GitPhp\Git;
use CzProject\GitPhp\GitException;
use GraphQL\Utils\SchemaPrinter;
use Illuminate\Console\Command;
use Illuminate\Contracts\Config\Repository as Config;
use Illuminate\Contracts\Foundation\Application;
use JsonException;
use Nuwave\Lighthouse\Schema\SchemaBuilder;

/**
 * @psalm-suppress PropertyNotSetInConstructor
 */
class RegisterSchema extends Command
{
    private const UPLOAD_SCHEMA_MUTATION = <<<'EOT'
    mutation UploadSchema(
      $id: ID!
      $schemaDocument: String!
      $tag: String!
      $gitContext: GitContextInput
    ) {
      service(id: $id) {
        uploadSchema(schemaDocument: $schemaDocument, tag: $tag, gitContext: $gitContext) {
          code
          message
          success
          tag {
            tag
            schema {
              hash
            }
          }
        }
      }
    }
    EOT;

    /**
     * The name and signature of the console command.
     */
    protected $signature = 'lighthouse-apollo:register-schema';

    /**
     * The console command description.
     */
    protected $description = 'Send the current schema to Apollo Studio';

    private Config $config;

    private Application $app;

    /**
     * Create a new console command instance.
     *
     * @param Application $app
     * @param Config $config
     */
    public function __construct(Application $app, Config $config)
    {
        parent::__construct();

        $this->app = $app;
        $this->config = $config;
    }

    /**
     * Execute the console command.
     *
     * @throws JsonException
     * @throws RegisterSchemaFailedException
     * @throws RegisterSchemaRequestFailedException
     */
    public function handle(SchemaBuilder $schemaBuilder): void
    {
        $variables = new UploadSchemaVariables(
            $this->config->get('lighthouse-apollo.apollo_graph_id'),
            SchemaPrinter::doPrint($schemaBuilder->schema()),
            $this->config->get('lighthouse-apollo.apollo_graph_variant'),
            $this->getGitContext(),
        );

        $response = $this->sendSchemaToApollo($variables);
        if (!empty($response['errors'])) {
            throw new RegisterSchemaFailedException(
                implode(
                    ', ',
                    array_map(function (array $error): string {
                        return $error['message'];
                    }, $response['errors']),
                ),
            );
        }

        $this->output->success(
            'Upload schema succeeded. Response from Apollo Studio: ' .
                var_export($response['data']['service']['uploadSchema'], true),
        );
    }

    /**
     * @param UploadSchemaVariables $variables
     * @return array
     * @throws RegisterSchemaRequestFailedException
     * @throws JsonException
     */
    protected function sendSchemaToApollo(
        UploadSchemaVariables $variables,
    ): array {
        $request = curl_init(
            $this->config->get('lighthouse-apollo.schema_reporting_endpoint'),
        );
        curl_setopt_array($request, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => json_encode(
                [
                    'query' => self::UPLOAD_SCHEMA_MUTATION,
                    'variables' => $variables->toArray(),
                ],
                JSON_THROW_ON_ERROR,
            ),
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'X-Api-Key: ' .
                $this->config->get('lighthouse-apollo.apollo_key'),
                'User-Agent: Lighthouse-Apollo',
            ],
        ]);

        $response = curl_exec($request);

        $errorCode = curl_errno($request);
        if ($errorCode || !is_string($response)) {
            throw new RegisterSchemaRequestFailedException(
                $errorCode,
                curl_error($request),
            );
        }

        return json_decode($response, true, 512, JSON_THROW_ON_ERROR);
    }

    private function getGitContext(): ?GitContextInput
    {
        $repo = (new Git())->open($this->app->basePath());

        try {
            $currentBranchName = $repo->getCurrentBranchName();
        } catch (GitException $e) {
            $currentBranchName = null;
        }

        try {
            $lastCommitId = $repo->getLastCommitId();
            $commitData = $repo->getCommit($lastCommitId);

            $lastCommitHash = $lastCommitId->toString();
            $author = $commitData->getAuthorName();
            $subject = $commitData->getSubject();
        } catch (GitException $e) {
            $lastCommitHash = null;
            $author = null;
            $subject = null;
        }

        return new GitContextInput(
            $currentBranchName,
            $lastCommitHash,
            $author,
            $subject,
            null,
        );
    }
}
