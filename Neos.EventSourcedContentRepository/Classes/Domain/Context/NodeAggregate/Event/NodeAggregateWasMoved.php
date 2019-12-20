<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event;

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\ValueObject\NodeMoveMappings;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\Flow\Annotations as Flow;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\CopyableAcrossContentStreamsInterface;

/**
 * A node aggregate was moved in a content stream as defined in the node move mappings
 *
 * @Flow\Proxy(false)
 */
final class NodeAggregateWasMoved implements DomainEventInterface, CopyableAcrossContentStreamsInterface
{
    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * @var NodeAggregateIdentifier
     */
    private $nodeAggregateIdentifier;

    /**
     * @var NodeMoveMappings|null
     */
    private $nodeMoveMappings;

    /**
     * @var DimensionSpacePointSet
     */
    private $repositionNodesWithoutAssignments;

    /**
     * @var UserIdentifier
     */
    private $initiatingUserIdentifier;

    public function __construct(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        ?NodeMoveMappings $nodeMoveMappings,
        DimensionSpacePointSet $repositionNodesWithoutAssignments,
        UserIdentifier $initiatingUserIdentifier
    ) {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->nodeMoveMappings = $nodeMoveMappings;
        $this->repositionNodesWithoutAssignments = $repositionNodesWithoutAssignments;
        $this->initiatingUserIdentifier = $initiatingUserIdentifier;
    }

    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    public function getNodeMoveMappings(): ?NodeMoveMappings
    {
        return $this->nodeMoveMappings;
    }

    public function getRepositionNodesWithoutAssignments(): DimensionSpacePointSet
    {
        return $this->repositionNodesWithoutAssignments;
    }

    public function getInitiatingUserIdentifier(): UserIdentifier
    {
        return $this->initiatingUserIdentifier;
    }

    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStreamIdentifier): NodeAggregateWasMoved
    {
        return new NodeAggregateWasMoved(
            $targetContentStreamIdentifier,
            $this->nodeAggregateIdentifier,
            $this->nodeMoveMappings,
            $this->repositionNodesWithoutAssignments,
            $this->initiatingUserIdentifier
        );
    }
}
