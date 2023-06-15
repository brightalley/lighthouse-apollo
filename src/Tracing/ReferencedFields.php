<?php

namespace BrightAlley\LighthouseApollo\Tracing;

use GraphQL\Language\AST\DefinitionNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\Node;
use GraphQL\Language\AST\NodeKind;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\Visitor;
use GraphQL\Type\Definition\InterfaceType;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\UnionType;
use GraphQL\Type\Schema;
use GraphQL\Utils\TypeInfo;
use LogicException;
use Mdg\ReferencedFieldsForType;

class ReferencedFields
{
    /**
     * @return array<string, ReferencedFieldsForType>
     * @throws \Exception
     */
    public static function calculateReferencedFieldsByType(
        DocumentNode $document,
        Schema $schema,
        ?string $resolvedOperationName
    ): array {
        // If the document contains multiple operations, we only care about fields
        // referenced in the operation we're using and in fragments that are
        // (transitively) spread by that operation. (This is because Studio's field
        // usage accounting is all by operation, not by document.) This does mean that
        // a field can be textually present in a GraphQL document (and need to exist
        // for validation) without being represented in the reported referenced fields
        // structure, but we'd need to change the data model of Studio to be based on
        // documents rather than fields if we wanted to improve that.
        $documentSeparatedByOperation = self::separateOperations($document);
        $filteredDocument =
            $documentSeparatedByOperation[$resolvedOperationName ?? ''] ??
            (count($documentSeparatedByOperation) === 1
                ? reset($documentSeparatedByOperation)
                : null);
        if ($filteredDocument === null) {
            // This shouldn't happen because we only should call this function on
            // properly executable documents.
            throw new LogicException(
                "shouldn't happen: operation '${resolvedOperationName}' not found",
            );
        }

        $typeInfo = new TypeInfo($schema);
        /** @var array<string, true> $interfaces */
        $interfaces = [];
        /** @var array<string, array<string, true>> $referencedFieldSetByType */
        $referencedFieldSetByType = [];
        Visitor::visit(
            $filteredDocument,
            Visitor::visitWithTypeInfo($typeInfo, [
                NodeKind::FIELD => function (Node $field) use (
                    $filteredDocument,
                    &$interfaces,
                    &$referencedFieldSetByType,
                    $typeInfo
                ): void {
                    /** @var FieldNode $field */
                    $fieldName = $field->name->value;
                    /** @var ObjectType|InterfaceType|UnionType|null $parentType */
                    $parentType = $typeInfo->getParentType();
                    if ($parentType === null) {
                        throw new LogicException(
                            "shouldn't happen: missing parent type for field $fieldName",
                        );
                    }

                    $parentTypeName = $parentType->name;
                    if (!isset($referencedFieldSetByType[$parentTypeName])) {
                        $referencedFieldSetByType[$parentTypeName] = [];
                        if ($parentType instanceof InterfaceType) {
                            $interfaces[$parentTypeName] = true;
                        }
                    }

                    $referencedFieldSetByType[$parentTypeName][
                        $fieldName
                    ] = true;
                },
            ]),
        );

        // Convert from initial representation (which uses Sets to avoid quadratic
        // behavior) to the protobufjs objects. (We could also use js_use_toArray here
        // but that seems a little overkill.)
        $referencedFieldsByType = [];
        foreach ($referencedFieldSetByType as $typeName => $fieldNames) {
            $fieldNames = array_keys($fieldNames);
            $referencedFieldsByType[$typeName] = new ReferencedFieldsForType([
                'field_names' => $fieldNames,
                'is_interface' => isset($interfaces[$typeName]),
            ]);
        }
        return $referencedFieldsByType;
    }

    /**
     * @return array<string, DocumentNode>
     * @throws \Exception
     */
    private static function separateOperations(DocumentNode $document): array
    {
        /** @var OperationDefinitionNode[] $operations */
        $operations = [];
        /** @var array<string, FragmentDefinitionNode> $fragments */
        $fragments = [];
        /** @var array<string, int> $positions */
        $positions = [];
        /** @var array<string, array<string, true>> $depGraph */
        $depGraph = [];
        $fromName = '';
        $idx = 0;

        // Populate metadata and build a dependency graph.
        Visitor::visit($document, [
            NodeKind::OPERATION_DEFINITION => function (
                Node $node
            ) use (&$fromName, &$idx, &$operations, &$positions): void {
                /** @var OperationDefinitionNode $node */
                $fromName = $node->name->value ?? '';
                $operations[] = $node;
                $positions[$fromName] = $idx++;
            },
            NodeKind::FRAGMENT_DEFINITION => function (
                Node $node
            ) use (&$fromName, &$idx, &$fragments, &$positions): void {
                /** @var FragmentDefinitionNode $node */
                $fromName = $node->name->value;
                $fragments[$fromName] = $node;
                $positions[$fromName] = $idx++;
            },
            NodeKind::FRAGMENT_SPREAD => function (
                Node $node
            ) use (&$fromName, &$depGraph): void {
                /** @var FragmentSpreadNode $node */
                $toName = $node->name->value;
                if (!isset($depGraph[$fromName])) {
                    $depGraph[$fromName] = [];
                }

                $depGraph[$fromName][$toName] = true;
            },
        ]);

        // For each operation, produce a new synthesized AST which includes only what
        // is necessary for completing that operation.
        /** @var array<string, DocumentNode> $separatedDocumentASTs */
        $separatedDocumentASTs = [];

        foreach ($operations as $operation) {
            $operationName = $operation->name->value ?? '';
            $dependencies = [];

            // The list of definition nodes to be included for this operation, sorted
            // to retain the same order as the original document.
            self::collectTransitiveDependencies(
                $dependencies,
                $depGraph,
                $operationName,
            );

            /** @var array<DefinitionNode&Node> $definitions */
            $definitions = [$operation];
            foreach (array_keys($dependencies) as $name) {
                $definitions[] = $fragments[$name];
            }

            usort(
                $definitions,
                /**
                 * @param DefinitionNode&Node $a
                 * @param DefinitionNode&Node $b
                 */
                static fn($a, $b): int => ($positions[$a->name->value ?? ''] ??
                    0) -
                    ($positions[$b->name->value ?? ''] ?? 0),
            );

            $separatedDocumentASTs[$operationName] = new DocumentNode([]);
            $separatedDocumentASTs[$operationName]->definitions = new NodeList(
                $definitions,
            );
        }

        return $separatedDocumentASTs;
    }

    /**
     * @param array<string, true> $collected
     * @param array<string, array<string, true>> $depGraph $depGraph
     */
    private static function collectTransitiveDependencies(
        array &$collected,
        array $depGraph,
        string $fromName
    ): void {
        $immediateDeps = $depGraph[$fromName] ?? null;

        if ($immediateDeps) {
            foreach (array_keys($immediateDeps) as $toName) {
                if (!isset($collected[$toName])) {
                    $collected[$toName] = true;
                    self::collectTransitiveDependencies(
                        $collected,
                        $depGraph,
                        $toName,
                    );
                }
            }
        }
    }
}
