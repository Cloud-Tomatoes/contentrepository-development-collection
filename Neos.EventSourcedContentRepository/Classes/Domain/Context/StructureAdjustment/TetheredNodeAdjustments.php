<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment;

use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\DimensionSpace\DimensionSpace;
use Neos\ContentRepository\Domain\Model\NodeType;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\ContentRepository\Domain\Projection\Content\NodeInterface;
use Neos\ContentRepository\Domain\Service\NodeTypeManager;
use Neos\ContentRepository\Exception\NodeException;
use Neos\EventSourcedContentRepository\Domain\Context\ContentStream\ContentStreamEventStreamName;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event\NodeAggregateWithNodeWasCreated;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Feature\NodeVariationInternals;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeAggregateClassification;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\ReadableNodeAggregateInterface;
use Neos\EventSourcedContentRepository\Domain\Context\Parameters\VisibilityConstraints;
use Neos\EventSourcedContentRepository\Domain\Context\StructureAdjustment\Dto\StructureAdjustment;
use Neos\EventSourcedContentRepository\Domain\Projection\Content\ContentGraphInterface;
use Neos\EventSourcedContentRepository\Domain\ValueObject\CommandResult;
use Neos\EventSourcedContentRepository\Domain\ValueObject\SerializedPropertyValues;
use Neos\EventSourcing\Event\DecoratedEvent;
use Neos\EventSourcing\Event\DomainEvents;
use Neos\EventSourcing\EventStore\EventStore;
use Ramsey\Uuid\Uuid;

/**
 * @Flow\Scope("singleton")
 */
class TetheredNodeAdjustments
{
    use NodeVariationInternals;

    protected EventStore $eventStore;
    protected ProjectedNodeIterator $projectedNodeIterator;
    protected NodeTypeManager $nodeTypeManager;
    protected DimensionSpace\InterDimensionalVariationGraph  $interDimensionalVariationGraph;
    protected ContentGraphInterface $contentGraph;

    public function __construct(EventStore $eventStore, ProjectedNodeIterator $projectedNodeIterator, NodeTypeManager $nodeTypeManager, DimensionSpace\InterDimensionalVariationGraph $interDimensionalVariationGraph, ContentGraphInterface $contentGraph)
    {
        $this->eventStore = $eventStore;
        $this->projectedNodeIterator = $projectedNodeIterator;
        $this->nodeTypeManager = $nodeTypeManager;
        $this->interDimensionalVariationGraph = $interDimensionalVariationGraph;
        $this->contentGraph = $contentGraph;
    }

    public function findAdjustmentsForNodeType(NodeTypeName $nodeTypeName): \Generator
    {
        $expectedTetheredNodes = $this->nodeTypeManager->getNodeType((string)$nodeTypeName)->getAutoCreatedChildNodes();

        foreach ($this->projectedNodeIterator->nodeAggregatesOfType($nodeTypeName) as $nodeAggregate) {
            foreach ($nodeAggregate->getNodes() as $node) {
                foreach ($expectedTetheredNodes as $tetheredNodeName => $expectedTetheredNodeType) {
                    $tetheredNodeName = NodeName::fromString($tetheredNodeName);

                    $subgraph = $this->contentGraph->getSubgraphByIdentifier($node->getContentStreamIdentifier(), $node->getOriginDimensionSpacePoint(), VisibilityConstraints::withoutRestrictions());
                    $tetheredNode = $subgraph->findChildNodeConnectedThroughEdgeName($node->getNodeAggregateIdentifier(), $tetheredNodeName);
                    if ($tetheredNode === null) {
                        // $nestedNode not found - so a tethered node is missing in the OriginDimensionSpacePoint of the $node
                        yield StructureAdjustment::createForNode($node, StructureAdjustment::TETHERED_NODE_MISSING, 'The tethered child node "' . $tetheredNodeName . '" is missing.', function () use ($nodeAggregate, $node, $tetheredNodeName, $expectedTetheredNodeType) {
                            return $this->createMissingTetheredNode($nodeAggregate, $node, $tetheredNodeName, $expectedTetheredNodeType);
                        });
                    } else {
                        yield from $this->ensureNodeIsTethered($tetheredNode);
                        yield from $this->ensureNodeIsOfType($tetheredNode, $expectedTetheredNodeType);
                    }
                }
            }

            $tetheredNodes = $this->contentGraph->findTetheredChildNodeAggregates($nodeAggregate->getContentStreamIdentifier(), $nodeAggregate->getIdentifier());
            $tetheredNodeTypes = [];
            foreach ($tetheredNodes as $tetheredNodeAggregate) {
                if (!isset($expectedTetheredNodes[(string)$tetheredNodeAggregate->getNodeName()])) {

                }
                $tetheredNodeTypes[] = (string)$tetheredNodeAggregate->getNodeTypeName();
            }
        }
    }

    private function ensureNodeIsTethered(NodeInterface $node): \Generator
    {
        if (!$node->isTethered()) {
            yield StructureAdjustment::createForNode($node, StructureAdjustment::NODE_IS_NOT_TETHERED_BUT_SHOULD_BE, 'This node should be a tethered node, but is not.');
        }
    }

    private function ensureNodeIsOfType(NodeInterface $node, NodeType $expectedNodeType): \Generator
    {
        if ($node->getNodeTypeName()->getValue() !== $expectedNodeType->getName()) {
            yield StructureAdjustment::createForNode($node, StructureAdjustment::TETHERED_NODE_TYPE_WRONG, 'should be of type "' . $expectedNodeType . '", but was "' . $node->getNodeTypeName()->getValue() . '".');
        }
    }

    protected function getInterDimensionalVariationGraph(): DimensionSpace\InterDimensionalVariationGraph
    {
        return $this->interDimensionalVariationGraph;
    }

    protected function getContentGraph(): ContentGraphInterface
    {
        $this->contentGraph;
    }

    /**
     * This is the remediation action for non-existing tethered nodes.
     * It handles two cases:
     * - there is no tethered node IN ANY DimensionSpacePoint -> we can simply create it
     * - there i
     *
     * @param \Neos\EventSourcedContentRepository\Domain\Projection\Content\NodeAggregate $parentNodeAggregate the node aggregate of the parent node
     * @param NodeInterface $parentNode the parent node underneath the tethered node should be.
     * @param NodeName $tetheredNodeName name of the edge towards the tethered node
     * @param NodeType $expectedTetheredNodeType expected node type of the tethered node
     * @param $command
     * @return CommandResult
     * @throws \Exception
     */
    private function createMissingTetheredNode(ReadableNodeAggregateInterface $parentNodeAggregate, NodeInterface $parentNode, NodeName $tetheredNodeName, NodeType $expectedTetheredNodeType): CommandResult
    {
        $childNodeAggregates = $this->contentGraph->findChildNodeAggregatesByName($parentNode->getContentStreamIdentifier(), $parentNode->getNodeAggregateIdentifier(), $tetheredNodeName);
        if (count($childNodeAggregates) === 0) {

            // there is no tethered child node aggregate already; let's create it!
            $events = DomainEvents::withSingleEvent(
                DecoratedEvent::addIdentifier(
                    new NodeAggregateWithNodeWasCreated(
                        $parentNode->getContentStreamIdentifier(),
                        NodeAggregateIdentifier::forAutoCreatedChildNode($tetheredNodeName, $parentNode->getNodeAggregateIdentifier()),
                        NodeTypeName::fromString($expectedTetheredNodeType->getName()),
                        $parentNode->getOriginDimensionSpacePoint(),
                        $parentNodeAggregate->getCoverageByOccupant($parentNode->getOriginDimensionSpacePoint()),
                        $parentNode->getNodeAggregateIdentifier(),
                        $tetheredNodeName,
                        SerializedPropertyValues::fromArray([]), // TODO
                        NodeAggregateClassification::tethered()
                    ),
                    Uuid::uuid4()->toString()
                )
            );

        } elseif (count($childNodeAggregates) === 1) {
            $childNodeAggregate = current($childNodeAggregates);
            if (!$childNodeAggregate->isTethered()) {
                throw new \RuntimeException("TODO");
            }

            $childNodeSource = $childNodeAggregate->getNodes()[0];
            $events = $this->createEventsForVariations($parentNode->getContentStreamIdentifier(), $childNodeSource->getOriginDimensionSpacePoint(), $parentNode->getOriginDimensionSpacePoint(), $parentNodeAggregate);
        } else {
            throw new \RuntimeException("TODO: more than one ChildNodeAggregate within the name - what's this? :D ");
        }

        $streamName = ContentStreamEventStreamName::fromContentStreamIdentifier($parentNode->getContentStreamIdentifier());
        $this->eventStore->commit($streamName->getEventStreamName(), $events);
        return CommandResult::fromPublishedEvents($events);
    }
}
