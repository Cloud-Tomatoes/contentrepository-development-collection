<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property;

/*
 * This file is part of the Neos.ContentRepository package.
 *
 * (c) Contributors of the Neos Project - www.neos.io
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property\Normalizers\NoOperationEncoder;
use Neos\Flow\Annotations as Flow;
use Neos\Utility\PositionalArraySorter;
use Symfony\Component\Serializer\Serializer;

/**
 * @Flow\Scope("singleton")
 * @internal
 */
final class PropertyConversionSerializerFactory
{

    /**
     * @var array
     * @Flow\InjectConfiguration(path="propertyConverters")
     */
    protected $propertyConvertersConfiguration;

    public function buildSerializer(): Serializer
    {
        $propertyConvertersConfiguration = (new PositionalArraySorter($this->propertyConvertersConfiguration))->toArray();

        $normalizers = [];
        foreach ($propertyConvertersConfiguration as $propertyConverterConfiguration) {
            $normalizers[] = new $propertyConverterConfiguration['className'];
        }

        return new Serializer($normalizers);
    }
}