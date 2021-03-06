<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePoint;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\MatchableWithNodeAddressInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\NodeVariantSelectionStrategyIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAddress\NodeAddress;

/**
 * Disable the given node aggregate in the given content stream in a dimension space point using a given strategy
 */
final class DisableNodeAggregate implements \JsonSerializable, RebasableToOtherContentStreamsInterface, MatchableWithNodeAddressInterface
{
    /**
     * @var ContentStreamIdentifier
     */
    private $contentStreamIdentifier;

    /**
     * Node aggregate identifier of the node the user intends to disable
     *
     * @var NodeAggregateIdentifier
     */
    private $nodeAggregateIdentifier;

    /**
     * One of the dimension space points covered by the node aggregate in which the user intends to disable it
     *
     * @var DimensionSpacePoint
     */
    private $coveredDimensionSpacePoint;

    /**
     * The strategy the user chose to determine which specialization variants will also be disabled
     *
     * @var NodeVariantSelectionStrategyIdentifier
     */
    private $nodeVariantSelectionStrategy;

    public function __construct(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        DimensionSpacePoint $coveredDimensionSpacePoint,
        NodeVariantSelectionStrategyIdentifier $nodeVariantSelectionStrategy
    ) {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->coveredDimensionSpacePoint = $coveredDimensionSpacePoint;
        $this->nodeVariantSelectionStrategy = $nodeVariantSelectionStrategy;
    }

    public static function fromArray(array $array): self
    {
        return new static(
            ContentStreamIdentifier::fromString($array['contentStreamIdentifier']),
            NodeAggregateIdentifier::fromString($array['nodeAggregateIdentifier']),
            new DimensionSpacePoint($array['coveredDimensionSpacePoint']),
            NodeVariantSelectionStrategyIdentifier::fromString($array['nodeVariantSelectionStrategy'])
        );
    }

    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    public function getCoveredDimensionSpacePoint(): DimensionSpacePoint
    {
        return $this->coveredDimensionSpacePoint;
    }

    public function getNodeVariantSelectionStrategy(): NodeVariantSelectionStrategyIdentifier
    {
        return $this->nodeVariantSelectionStrategy;
    }

    public function jsonSerialize(): array
    {
        return [
            'contentStreamIdentifier' => $this->contentStreamIdentifier,
            'nodeAggregateIdentifier' => $this->nodeAggregateIdentifier,
            'coveredDimensionSpacePoint' => $this->coveredDimensionSpacePoint,
            'nodeVariantSelectionStrategy' => $this->nodeVariantSelectionStrategy,
        ];
    }

    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStreamIdentifier): self
    {
        return new static(
            $targetContentStreamIdentifier,
            $this->nodeAggregateIdentifier,
            $this->coveredDimensionSpacePoint,
            $this->nodeVariantSelectionStrategy
        );
    }

    public function matchesNodeAddress(NodeAddress $nodeAddress): bool
    {
        return (
            (string)$this->getContentStreamIdentifier() === (string)$nodeAddress->getContentStreamIdentifier()
            && $this->getCoveredDimensionSpacePoint()->equals($nodeAddress->getDimensionSpacePoint())
            && $this->getNodeAggregateIdentifier()->equals($nodeAddress->getNodeAggregateIdentifier())
        );
    }
}
