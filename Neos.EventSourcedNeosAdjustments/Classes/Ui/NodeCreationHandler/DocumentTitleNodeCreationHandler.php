<?php
declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\Ui\NodeCreationHandler;

/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Command\SetNodeProperties;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValue;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyValues;
use Neos\EventSourcedContentRepository\Service\Infrastructure\CommandBus\CommandBusInterface;
use Neos\EventSourcedNeosAdjustments\Ui\Service\NodeUriPathSegmentGenerator;
use Neos\Flow\Annotations as Flow;

class DocumentTitleNodeCreationHandler implements NodeCreationHandlerInterface
{
    /**
     * @Flow\Inject
     * @var NodeUriPathSegmentGenerator
     */
    protected $nodeUriPathSegmentGenerator;

    /**
     * @Flow\Inject
     * @var CommandBusInterface
     */
    protected $commandBus;

    /**
     * Set the node title for the newly created Document node
     *
     * @param TraversableNodeInterface $node The newly created node
     * @param array $data incoming data from the creationDialog
     * @return void
     */
    public function handle(TraversableNodeInterface $node, array $data)
    {
        if ($node->getNodeType()->isOfType('Neos.Neos:Document')) {
            if (isset($data['title'])) {
                $this->commandBus->handle(new SetNodeProperties(
                    $node->getContentStreamIdentifier(),
                    $node->getNodeAggregateIdentifier(),
                    $node->getOriginDimensionSpacePoint(),
                    PropertyValues::fromArray(
                        [
                            'title' => new PropertyValue($data['title'], 'string')
                        ]
                    )
                ));
            }

            $uriPathSegment = $this->nodeUriPathSegmentGenerator->generateUriPathSegment($node, $data['title']);
            $this->commandBus->handle(new SetNodeProperties(
                $node->getContentStreamIdentifier(),
                $node->getNodeAggregateIdentifier(),
                $node->getOriginDimensionSpacePoint(),
                PropertyValues::fromArray(
                    [
                        'uriPathSegment' => new PropertyValue($uriPathSegment, 'string')
                    ]
                )
            ))->blockUntilProjectionsAreUpToDate();
            // TODO: re-enable line below
            // $node->setProperty('uriPathSegment', $this->nodeUriPathSegmentGenerator->generateUriPathSegment($node, (isset($data['title']) ? $data['title'] : null)));
        }
    }
}
