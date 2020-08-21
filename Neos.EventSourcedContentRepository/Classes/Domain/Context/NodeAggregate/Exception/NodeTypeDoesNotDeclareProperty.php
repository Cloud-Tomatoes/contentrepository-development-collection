<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Exception;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\NodeType\NodeTypeName;
use Neos\EventSourcedContentRepository\Domain\ValueObject\PropertyName;
use Neos\Flow\Annotations as Flow;

/**
 * The exception to be thrown if a given node type does not declare a given property
 *
 * @Flow\Proxy(false)
 */
final class NodeTypeDoesNotDeclareProperty extends \OutOfBoundsException
{
    public static function butWasSupposedTo(NodeTypeName $nodeTypeName, PropertyName $attemptedPropertyName): self
    {
        return new self('The node type ' . $nodeTypeName . ' does not declare property ' . $attemptedPropertyName, 1597937846);
    }
}