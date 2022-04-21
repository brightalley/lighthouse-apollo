<?php

namespace BrightAlley\Tests;

use BrightAlley\LighthouseApollo\Listeners\ManipulateResultListener;
use BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder;
use BrightAlley\LighthouseApollo\TracingResult;
use BrightAlley\Tests\Support\UsesSampleData;
use Exception;
use GraphQL\Error\Error;
use GraphQL\Error\FormattedError;
use Illuminate\Support\Str;
use Mdg\Trace;
use PHPUnit\Framework\TestCase;

class TracingResultTest extends TestCase
{
    use UsesSampleData;

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getTracingAsProtobuf
     * @covers \BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder
     */
    public function testGetTracingAsProtobuf(): void
    {
        $tracing = new TracingResult(
            '{ hello }',
            null,
            null,
            $this->sampleClientData(),
            $this->sampleHttpData(),
            $this->sampleTracingData(),
            [],
        );
        $proto = $tracing->getTracingAsProtobuf();

        self::assertNotNull($proto->getRoot());
        self::assertCount(1, $proto->getRoot()->getChild());
        self::assertEquals('phpunit', $proto->getClientName());
    }

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getTracingAsProtobuf
     * @covers \BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder
     */
    public function testGetTracingAsProtobufWithVariables(): void
    {
        $tracing = new TracingResult(
            '{ hello }',
            ['key' => json_encode('value', JSON_THROW_ON_ERROR)],
            null,
            $this->sampleClientData(),
            $this->sampleHttpData(),
            $this->sampleTracingData(),
            [],
        );
        $proto = $tracing->getTracingAsProtobuf();

        self::assertNotNull($proto->getDetails());
        self::assertNotNull($proto->getDetails()->getVariablesJson());
    }

    public function nullableClientFields(): array
    {
        return [
            [['name' => null]],
            [['version' => null]],
            [['name' => null, 'version' => null]],
        ];
    }

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getTracingAsProtobuf
     * @covers \BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder
     * @dataProvider nullableClientFields
     */
    public function testGetTracingAsProtobufNullableClientFields(
        array $clientData
    ): void {
        $tracing = new TracingResult(
            '{ hello }',
            null,
            null,
            array_merge($this->sampleClientData(), $clientData),
            $this->sampleHttpData(),
            $this->sampleTracingData(),
            [],
        );
        $proto = $tracing->getTracingAsProtobuf();

        foreach ($clientData as $field => $_) {
            $func = 'getClient' . Str::studly($field);
            self::assertEmpty($proto->$func());
        }
    }

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getTracingAsProtobuf
     * @covers \BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder
     */
    public function testGetTracingAsProtobufWithErrors(): void
    {
        // Top level error should show up on root.
        $tracing = new TracingResult(
            '{ hello }',
            null,
            null,
            $this->sampleClientData(),
            $this->sampleHttpData(),
            $this->sampleTracingData(),
            [
                FormattedError::createFromException(
                    new Error(
                        'some error',
                        null,
                        null,
                        [],
                        null,
                        new Exception('internal error message'),
                    ),
                    ManipulateResultListener::DEBUG_FLAGS,
                ),
            ],
        );
        $proto = $tracing->getTracingAsProtobuf();

        self::assertNotNull($proto->getRoot());
        self::assertCount(1, $proto->getRoot()->getError());
        self::assertEquals(
            'some error',
            $proto
                ->getRoot()
                ->getError()
                ->offsetGet(0)
                ->getMessage(),
        );

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
            null,
            null,
            $this->sampleClientData(),
            $this->sampleHttpData(),
            $tracingData,
            [
                FormattedError::createFromException(
                    new Error(
                        'some error',
                        null,
                        null,
                        [],
                        ['hello', 'world'],
                        new Exception('internal error message'),
                    ),
                    ManipulateResultListener::DEBUG_FLAGS,
                ),
            ],
        );
        $proto = $tracing->getTracingAsProtobuf();

        // Top level error should show up on root.
        $root = $proto->getRoot();
        self::assertNotNull($root);
        self::assertCount(0, $root->getError());
        self::assertCount(1, $root->getChild());

        $helloNode = $root->getChild()->offsetGet(0);
        self::assertEquals('hello', $helloNode->getResponseName());
        self::assertCount(1, $helloNode->getChild());
        self::assertCount(0, $helloNode->getError());

        $worldNode = $helloNode->getChild()->offsetGet(0);
        self::assertEquals('world', $worldNode->getResponseName());
        self::assertCount(1, $worldNode->getError());
        self::assertEquals(
            'some error',
            $worldNode
                ->getError()
                ->offsetGet(0)
                ->getMessage(),
        );
    }

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getTracingAsProtobuf
     * @covers \BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder
     */
    public function testGetTracingAsProtobufMissingLink(): void
    {
        $tracingData = $this->sampleTracingData();
        $tracingData['execution']['resolvers'][] = [
            'path' => ['hello', 'nested', 'world'],
            'parentType' => 'String',
            'returnType' => 'String',
            'fieldName' => 'world',
            'startOffset' => microtime(true),
            'duration' => 500,
        ];

        $tracing = new TracingResult(
            '{ hello { nested { world } } }',
            null,
            null,
            $this->sampleClientData(),
            $this->sampleHttpData(),
            $tracingData,
            [],
        );
        $proto = $tracing->getTracingAsProtobuf();

        $this->assertInstanceOf(Trace::class, $proto);
    }

    /**
     * @covers \BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder::errorToProtobufError
     */
    public function testGetErrorAsProtobuf(): void
    {
        $error = FormattedError::createFromException(
            new Exception('test'),
            ManipulateResultListener::DEBUG_FLAGS,
        );
        $proto = TraceTreeBuilder::errorToProtobufError($error);

        self::assertEquals('test', $proto->getMessage());
        self::assertJson($proto->getJson());

        $json = json_decode($proto->getJSON());
        self::assertObjectHasAttribute('trace', $json);
    }

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getTracingAsProtobuf
     * @covers \BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder
     */
    public function testGetTracingAsProtobufMultipleRootFields(): void
    {
        $tracingData = $this->sampleTracingData();
        $tracingData['execution']['resolvers'][] = [
            'path' => ['world'],
            'parentType' => 'Query',
            'returnType' => 'String',
            'fieldName' => 'world',
            'startOffset' => microtime(true),
            'duration' => 600,
        ];

        $tracing = new TracingResult(
            '{ hello foo }',
            null,
            null,
            $this->sampleClientData(),
            $this->sampleHttpData(),
            $tracingData,
            [],
        );
        $proto = $tracing->getTracingAsProtobuf();

        $this->assertInstanceOf(Trace::class, $proto);
        $this->assertNotNull($proto->getRoot());
        $this->assertEquals(2, count($proto->getRoot()->getChild()));
    }

    /**
     * @covers \BrightAlley\LighthouseApollo\TracingResult::getTracingAsProtobuf
     * @covers \BrightAlley\LighthouseApollo\Tracing\TraceTreeBuilder
     */
    public function testGetTracingAsProtobufMultipleRootFieldsMissingRootNode(): void
    {
        $tracingData = $this->sampleTracingData();
        $tracingData['execution']['resolvers'][] = [
            'path' => ['node', 'world'],
            'parentType' => 'Query',
            'returnType' => 'String',
            'fieldName' => 'world',
            'startOffset' => microtime(true),
            'duration' => 600,
        ];

        $tracing = new TracingResult(
            '{ hello node { world } }',
            null,
            null,
            $this->sampleClientData(),
            $this->sampleHttpData(),
            $tracingData,
            [],
        );
        $proto = $tracing->getTracingAsProtobuf();

        $this->assertInstanceOf(Trace::class, $proto);
        $this->assertNotNull($proto->getRoot());
        $this->assertEquals(2, count($proto->getRoot()->getChild()));
    }
}
