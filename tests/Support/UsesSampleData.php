<?php

namespace BrightAlley\Tests\Support;

use GraphQL\Type\Definition\FieldDefinition;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Schema;
use Mdg\Trace\HTTP\Method;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Tracing\Tracing;

trait UsesSampleData
{
    private function sampleClientData(): array
    {
        return [
            'address' => '',
            'name' => 'phpunit',
            'version' => '1.0',
            'reference_id' => 'ref',
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
        $tracing->handleStartRequest($this->createMock(StartRequest::class));
        $tracing->handleStartExecution($this->createMock(StartExecution::class));
        // Record one tracing.
        $now = microtime(true);
        $tracing->record(
            new ResolveInfo(
                FieldDefinition::create([
                    'name' => 'hello',
                    'type' => new StringType(),
                ]),
                [],
                $queryType = new QueryType(),
                ['hello'],
                new Schema(['query' => $queryType]),
                [],
                null,
                null,
                []
            ),
            $now - 500,
            $now
        );

        $extensionResponse = $tracing->handleBuildExtensionsResponse(
            $this->createMock(BuildExtensionsResponse::class)
        );

        return $extensionResponse->content();
    }

}
