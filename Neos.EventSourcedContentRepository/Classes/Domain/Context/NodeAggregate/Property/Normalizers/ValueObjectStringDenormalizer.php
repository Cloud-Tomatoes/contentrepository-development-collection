<?php
declare(strict_types=1);

namespace Neos\EventSourcedContentRepository\Domain\Context\NodeAggregate\Property\Normalizers;

use Symfony\Component\Serializer\Normalizer\DenormalizerInterface;

class ValueObjectStringDenormalizer implements DenormalizerInterface
{
    public function denormalize($data, $type, string $format = null, array $context = [])
    {
        return $type::fromString($data);
    }

    public function supportsDenormalization($data, $type, string $format = null)
    {
        return is_string($data) && class_exists($type) && method_exists($type, 'fromString');
    }
}