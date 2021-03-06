<?php
declare(strict_types=1);
namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Event;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\DimensionSpace\DimensionSpace\DimensionSpacePointSet;
use Neos\ContentRepository\Domain\ContentStream\ContentStreamIdentifier;
use Neos\ContentRepository\Domain\NodeAggregate\NodeAggregateIdentifier;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\OriginDimensionSpacePoint;
use Neos\EventSourcing\Event\DomainEventInterface;
use Neos\Flow\Annotations as Flow;

/**
 * A node specialization was created
 *
 * @Flow\Proxy(false)
 */
final class NodeSpecializationVariantWasCreated implements DomainEventInterface, PublishableToOtherContentStreamsInterface
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
     * @var OriginDimensionSpacePoint
     */
    private $sourceOrigin;

    /**
     * @var OriginDimensionSpacePoint
     */
    private $specializationOrigin;

    /**
     * @var DimensionSpacePointSet
     */
    private $specializationCoverage;

    public function __construct(
        ContentStreamIdentifier $contentStreamIdentifier,
        NodeAggregateIdentifier $nodeAggregateIdentifier,
        OriginDimensionSpacePoint $sourceOrigin,
        OriginDimensionSpacePoint $specializationOrigin,
        DimensionSpacePointSet $specializationCoverage
    ) {
        $this->contentStreamIdentifier = $contentStreamIdentifier;
        $this->nodeAggregateIdentifier = $nodeAggregateIdentifier;
        $this->sourceOrigin = $sourceOrigin;
        $this->specializationOrigin = $specializationOrigin;
        $this->specializationCoverage = $specializationCoverage;
    }

    /**
     * @return ContentStreamIdentifier
     */
    public function getContentStreamIdentifier(): ContentStreamIdentifier
    {
        return $this->contentStreamIdentifier;
    }

    /**
     * @return NodeAggregateIdentifier
     */
    public function getNodeAggregateIdentifier(): NodeAggregateIdentifier
    {
        return $this->nodeAggregateIdentifier;
    }

    /**
     * @return OriginDimensionSpacePoint
     */
    public function getSourceOrigin(): OriginDimensionSpacePoint
    {
        return $this->sourceOrigin;
    }

    /**
     * @return OriginDimensionSpacePoint
     */
    public function getSpecializationOrigin(): OriginDimensionSpacePoint
    {
        return $this->specializationOrigin;
    }

    /**
     * @return DimensionSpacePointSet
     */
    public function getSpecializationCoverage(): DimensionSpacePointSet
    {
        return $this->specializationCoverage;
    }

    /**
     * @param ContentStreamIdentifier $targetContentStreamIdentifier
     * @return NodeSpecializationVariantWasCreated
     */
    public function createCopyForContentStream(ContentStreamIdentifier $targetContentStreamIdentifier): NodeSpecializationVariantWasCreated
    {
        return new NodeSpecializationVariantWasCreated(
            $targetContentStreamIdentifier,
            $this->nodeAggregateIdentifier,
            $this->sourceOrigin,
            $this->specializationOrigin,
            $this->specializationCoverage
        );
    }
}
