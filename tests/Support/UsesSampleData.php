<?php

namespace BrightAlley\Tests\Support;

use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Schema;
use Mdg\Trace\HTTP\Method;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Execution\Arguments\ArgumentSet;
use Nuwave\Lighthouse\Execution\ResolveInfo as NuwaveResolveInfo;
use Nuwave\Lighthouse\Tracing\Tracing;

trait UsesSampleData
{
    private function sampleClientData(): array
    {
        return [
            'address' => '',
            'name' => 'phpunit',
            'version' => '1.0',
        ];
    }

    private function sampleHttpData(): array
    {
        return [
            'method' => Method::POST,
            'host' => 'www.example.com',
            'path' => '/graphql',
            'status_code' => 200,
            'secure' => true,
            'request_headers' => [
                'user-agent' => ['Foo'],
            ],
        ];
    }

    private function sampleTracingData(): array
    {
        $tracing = new Tracing();
        if (method_exists($tracing, 'handleStartRequest')) {
            $tracing->handleStartRequest(
                $this->createMock(StartRequest::class),
            );
        }
        $tracing->handleStartExecution(
            $this->createMock(StartExecution::class),
        );
        // Record one tracing.
        $now = microtime(true);
        $tracing->record(
            new NuwaveResolveInfo(
                new ResolveInfo(
                    new FieldDefinition([
                        'name' => 'hello',
                        'type' => new StringType(),
                    ]),
                    new \ArrayObject(),
                    ($queryType = new QueryType()),
                    ['hello'],
                    new Schema(['query' => $queryType]),
                    [],
                    null,
                    new OperationDefinitionNode([]),
                    [],
                ),
                new ArgumentSet(),
            ),
            $now - 500,
            $now,
        );

        $extensionResponse = $tracing->handleBuildExtensionsResponse(
            $this->createMock(BuildExtensionsResponse::class),
        );

        return $extensionResponse->content;
    }
}
