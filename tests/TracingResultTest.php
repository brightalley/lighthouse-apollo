<?php

namespace BrightAlley\Tests;

use BrightAlley\LighthouseApollo\Listeners\ManipulateResultListener;
use BrightAlley\LighthouseApollo\TracingResult;
use BrightAlley\Tests\Support\QueryType;
use Exception;
use GraphQL\Error\Debug;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use GraphQL\Error\InvariantViolation;
use GraphQL\Type\Definition\ResolveInfo;
use GraphQL\Type\Definition\StringType;
use GraphQL\Type\Schema;
use Illuminate\Support\Str;
use Mdg\Trace\HTTP\Method;
use Nuwave\Lighthouse\Events\BuildExtensionsResponse;
use Nuwave\Lighthouse\Events\StartExecution;
use Nuwave\Lighthouse\Events\StartRequest;
use Nuwave\Lighthouse\Tracing\Tracing;
use PHPUnit\Framework\TestCase;

class TracingResultTest extends TestCase
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
                'hello',
                [],
                new StringType(),
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

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getTracingAsProtobuf
     */
    public function testGetTracingAsProtobuf(): void
    {
        $tracing = new TracingResult(
            '{ hello }',
            $this->sampleClientData(),
            $this->sampleHttpData(),
            $this->sampleTracingData(),
            []
        );
        $proto = $tracing->getTracingAsProtobuf();

        self::assertNotNull($proto->getRoot());
        self::assertCount(0, $proto->getRoot()->getChild());
        self::assertEquals('phpunit', $proto->getClientName());
    }

    public function nullableClientFields(): array
    {
        return [
            [['address' => null]],
            [['name' => null]],
            [['reference_id' => null]],
            [['version' => null]],
            [['address' => null, 'name' => null, 'reference_id' => null, 'version' => null]],
        ];
    }

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getTracingAsProtobuf
     * @dataProvider nullableClientFields
     */
    public function testGetTracingAsProtobufNullableClientFields(array $clientData): void
    {
        $tracing = new TracingResult(
            '{ hello }',
            array_merge($this->sampleClientData(), $clientData),
            $this->sampleHttpData(),
            $this->sampleTracingData(),
            []
        );
        $proto = $tracing->getTracingAsProtobuf();

        foreach ($clientData as $field => $_) {
            $func = 'getClient' . Str::studly($field);
            self::assertEmpty($proto->$func());
        }
    }

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getTracingAsProtobuf
     */
    public function testGetTracingAsProtobufWithErrors(): void
    {
        // Top level error should show up on root.
        $tracing = new TracingResult(
            '{ hello }',
            $this->sampleClientData(),
            $this->sampleHttpData(),
            $this->sampleTracingData(),
            [FormattedError::createFromException(
                new Error(
                    'some error',
                    null,
                    null,
                    null,
                    null,
                    new Exception('internal error message')
                ),
                ManipulateResultListener::DEBUG_FLAGS
            )]
        );
        $proto = $tracing->getTracingAsProtobuf();

        self::assertNotNull($proto->getRoot());
        self::assertCount(1, $proto->getRoot()->getError());
        self::assertEquals('some error', $proto->getRoot()->getError()->offsetGet(0)->getMessage());

        // Nested error is attached to field.
        $tracingData = $this->sampleTracingData();
        $tracingData['execution']['resolvers'][] = [
            'path' => ['hello', 'world'],
            'parentType' => 'String',
            'returnType' => 'String',
            'fieldName' => 'world',
            'startOffset' => microtime(true),
            'duration' => 500,
        ];
        $tracing = new TracingResult(
            '{ hello { world } }',
            $this->sampleClientData(),
            $this->sampleHttpData(),
            $tracingData,
            [FormattedError::createFromException(
                new Error(
                    'some error',
                    null,
                    null,
                    null,
                    ['hello', 'world'],
                    new Exception('internal error message')
                ),
                ManipulateResultListener::DEBUG_FLAGS
            )]
        );
        $proto = $tracing->getTracingAsProtobuf();

        // Top level error should show up on root.
        self::assertNotNull($proto->getRoot());
        self::assertCount(0, $proto->getRoot()->getError());
        self::assertCount(1, $proto->getRoot()->getChild());
        self::assertEquals('world', $proto->getRoot()->getChild()->offsetGet(0)->getOriginalFieldName());
        self::assertCount(1, $proto->getRoot()->getChild()->offsetGet(0)->getError());
        self::assertEquals(
            'some error',
            $proto->getRoot()->getChild()->offsetGet(0)->getError()->offsetGet(0)->getMessage()
        );
    }

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getErrorAsProtobuf
     */
    public function testGetErrorAsProtobuf(): void
    {
        $error = FormattedError::createFromException(
            new Exception('test'),
            ManipulateResultListener::DEBUG_FLAGS
        );
        $proto = TracingResult::getErrorAsProtobuf($error);

        self::assertEquals('test', $proto->getMessage());
        self::assertJson($proto->getJson());

        $json = json_decode($proto->getJSON());
        self::assertObjectHasAttribute('trace', $json);
    }
}
