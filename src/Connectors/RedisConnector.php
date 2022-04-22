<?php

namespace BrightAlley\LighthouseApollo\Connectors;

use BrightAlley\LighthouseApollo\TracingResult;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumTypeExtensionNode;
use GraphQL\Language\AST\ExecutableDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeExtensionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeExtensionNode;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectTypeExtensionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeExtensionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SchemaTypeExtensionNode;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\UnionTypeExtensionNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Language\Token;
use GraphQL\Type\Definition\EnumValueDefinition;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Redis\Connections\Connection as RedisConnection;

class RedisConnector
{
    private const REDIS_KEY = 'lighthouse_apollo_tracings';

    private RedisConnection $redis;

    private bool $redisSupportsLpopWithCount = false;

    /**
     * Constructor.
     *
     * @param Repository $config
     * @param RedisFactory $redis
     */
    public function __construct(Repository $config, RedisFactory $redis)
    {
        $this->redis = $redis->connection(
            $config->get('lighthouse-apollo.redis_connection'),
        );

        $redisInfo = $this->redis->command('info', ['server']);
        $this->redisSupportsLpopWithCount =
            version_compare(
                $redisInfo['Server']['redis_version'] ?? '1.0.0',
                '6.2.0',
                '>=',
            ) === true;
    }

    /**
     * Add a new tracing result to the redis queue.
     *
     * @param TracingResult $tracingResult
     */
    public function put(TracingResult $tracingResult): void
    {
        $this->putMany([$tracingResult]);
    }

    /**
     * Add multiple tracing results to the redis queue.
     *
     * @param array<int,TracingResult> $tracings
     */
    public function putMany(array $tracings): void
    {
        $this->redis->command('rpush', [
            self::REDIS_KEY,
            ...array_map(static function (TracingResult $tracingResult) {
                return serialize($tracingResult);
            }, $tracings),
        ]);
    }

    /**
     * Fetch the pending traces from Redis in chunks.
     *
     * @template T
     * @param int $chunkSize
     * @param callable(array<int,TracingResult>): T $callback
     * @return T|int
     */
    public function chunk(callable $callback, int $chunkSize = 100)
    {
        // Get the total count, and then fetch them in chunks.
        $total = (int) $this->redis->command('llen', [self::REDIS_KEY]);
        $fetched = 0;

        while ($fetched < $total) {
            $amountToFetch = min($chunkSize, $total - $fetched);
            if ($amountToFetch === 0) {
                break;
            }

            $chunk = $this->fetchTracingsFromRedis($amountToFetch);
            if (count($chunk) === 0) {
                break;
            }

            $fetched += count($chunk);
            $result = $callback($chunk);
            if ($result !== null) {
                return $result;
            }
        }

        return $fetched;
    }

    /**
     * @deprecated Use chunk() instead.
     * @return array<int,TracingResult>
     */
    public function getPending(int $limit = 100): array
    {
        // Check the number of results in the redis key, so we can take them all.
        $length = (int) $this->redis->command('llen', [self::REDIS_KEY]);
        $resultsToFetch = min($length, $limit);

        return $this->fetchTracingsFromRedis($resultsToFetch);
    }

    /**
     * @return array<int,TracingResult>
     */
    protected function fetchTracingsFromRedis(int $count): array
    {
        if ($this->redisSupportsLpopWithCount) {
            /** @var string[] $result */
            $result =
                $this->redis->command('lpop', [self::REDIS_KEY, $count]) ?? [];
        } else {
            /** @var string[] $result */
            $result = [];
            while (
                count($result) < $count &&
                ($serializedTracingResult = $this->redis->command('lpop', [
                    self::REDIS_KEY,
                ]))
            ) {
                $result[] = $serializedTracingResult;
            }
        }

        return array_map(
            static fn(
                string $serializedTracingResult
            ): TracingResult => unserialize($serializedTracingResult, [
                'allowed_classes' => [
                    // Only allow decoding tracing results.
                    TracingResult::class,

                    // And all possible nodes you may find in the GraphQL document.
                    ArgumentNode::class,
                    BooleanValueNode::class,
                    DirectiveNode::class,
                    DocumentNode::class,
                    EnumValueNode::class,
                    FieldNode::class,
                    FloatValueNode::class,
                    FragmentDefinitionNode::class,
                    FragmentSpreadNode::class,
                    InlineFragmentNode::class,
                    IntValueNode::class,
                    ListTypeNode::class,
                    ListValueNode::class,
                    Location::class,
                    NamedTypeNode::class,
                    NameNode::class,
                    NodeList::class,
                    NonNullTypeNode::class,
                    ObjectFieldNode::class,
                    ObjectValueNode::class,
                    OperationDefinitionNode::class,
                    SelectionSetNode::class,
                    SourceLocation::class,
                    StringValueNode::class,
                    Source::class,
                    Token::class,
                    VariableDefinitionNode::class,
                    VariableNode::class,

                    // XXX: It seems... kind of unlikely that these would show up in a tracing document,
                    // but maybe they could? Should figure that out at some point.
                    DirectiveDefinitionNode::class,
                    EnumTypeDefinitionNode::class,
                    EnumTypeExtensionNode::class,
                    EnumValueDefinition::class,
                    FieldDefinitionNode::class,
                    InputObjectTypeDefinitionNode::class,
                    InputObjectTypeExtensionNode::class,
                    InputValueDefinitionNode::class,
                    InterfaceTypeDefinitionNode::class,
                    InterfaceTypeExtensionNode::class,
                    ObjectTypeDefinitionNode::class,
                    ObjectTypeExtensionNode::class,
                    ScalarTypeDefinitionNode::class,
                    ScalarTypeExtensionNode::class,
                    SchemaDefinitionNode::class,
                    SchemaTypeExtensionNode::class,
                    UnionTypeDefinitionNode::class,
                    UnionTypeExtensionNode::class,
                ],
            ]),
            $result,
        );
    }
}
