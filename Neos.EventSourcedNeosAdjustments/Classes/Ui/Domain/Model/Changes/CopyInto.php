<?php

declare(strict_types=1);
namespace Neos\EventSourcedNeosAdjustments\Ui\Domain\Model\Changes;

/*
 * This file is part of the Neos.Neos.Ui package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\EventSourcedNeosAdjustments\Ui\Fusion\Helper\NodeInfoHelper;
use Neos\Flow\Annotations as Flow;
use Neos\ContentRepository\Domain\NodeAggregate\NodeName;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\EventSourcedContentRepository\Domain\Context\NodeDuplication\Command\CopyNodesRecursively;
use Neos\EventSourcedContentRepository\Domain\Context\NodeDuplication\NodeDuplicationCommandHandler;
use Neos\EventSourcedContentRepository\Domain\ValueObject\UserIdentifier;

class CopyInto extends AbstractStructuralChange
{

    /**
     * @Flow\Inject
     * @var NodeDuplicationCommandHandler
     */
    protected $nodeDuplicationCommandHandler;

    /**
     * @var string
     */
    protected $parentContextPath;

    /**
     * @var TraversableNodeInterface
     */
    protected $cachedParentNode;

    /**
     * @param string $parentContextPath
     */
    public function setParentContextPath($parentContextPath)
    {
        $this->parentContextPath = $parentContextPath;
    }

    /**
     * @return TraversableNodeInterface
     */
    public function getParentNode()
    {
        if ($this->cachedParentNode === null) {
            $this->cachedParentNode = $this->nodeService->getNodeFromContextPath(
                $this->parentContextPath
            );
        }

        return $this->cachedParentNode;
    }

    /**
     * "Subject" is the to-be-copied node; the "parent" node is the new parent
     *
     * @return boolean
     */
    public function canApply()
    {
        $nodeType = $this->getSubject()->getNodeType();

        return NodeInfoHelper::isNodeTypeAllowedAsChildNode($this->getParentNode(), $nodeType);
    }

    public function getMode()
    {
        return 'into';
    }

    /**
     * Applies this change
     *
     * @return void
     */
    public function apply()
    {
        if ($this->canApply()) {
            $subject = $this->getSubject();

            $command = CopyNodesRecursively::create(
                $subject,
                $subject->getDimensionSpacePoint(),
                UserIdentifier::forSystemUser(), // TODO
                $this->getParentNode()->getNodeAggregateIdentifier(),
                null,
                NodeName::fromString(uniqid('node-'))
            );

            $this->contentCacheFlusher->registerNodeChange($subject);

            $this->nodeDuplicationCommandHandler->handleCopyNodesRecursively($command)->blockUntilProjectionsAreUpToDate();

            $newlyCreatedNode = $this->getParentNode()->findNamedChildNode($command->getTargetNodeName());
            $this->finish($newlyCreatedNode);
            // NOTE: we need to run "finish" before "addNodeCreatedFeedback" to ensure the new node already exists when the last feedback is processed
            $this->addNodeCreatedFeedback($newlyCreatedNode);
        }
    }
}
