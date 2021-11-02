<?php

namespace BrightAlley\Tests\Actions;

use BrightAlley\LighthouseApollo\Actions\SendTracingToApollo;
use BrightAlley\LighthouseApollo\TracingResult;
use BrightAlley\Tests\Support\UsesSampleData;
use Illuminate\Contracts\Config\Repository;
use Mdg\TracesAndStats;
use PHPUnit\Framework\TestCase;

class SendTracingToApolloTest extends TestCase
{
    use UsesSampleData;

    /**
     * @covers \BrightAlley\LighthouseApollo\Actions\SendTracingToApollo::getReportWithTraces
     */
    public function testGetReportWithTraces(): void
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

        $action = new SendTracingToApollo(
            $this->createMock(Repository::class),
            [$tracing],
        );
        $traces = [
            $action->normalizeQuery(
                $tracing->queryText,
                $tracing->operationName,
            ) => new TracesAndStats([
                'trace' => [$tracing->getTracingAsProtobuf()],
            ]),
        ];
        $body = $action->getReportWithTraces($traces);

        // Note that the action of serializing to a string also runs validation.
        self::assertIsString($body->serializeToJsonString());
    }
}
