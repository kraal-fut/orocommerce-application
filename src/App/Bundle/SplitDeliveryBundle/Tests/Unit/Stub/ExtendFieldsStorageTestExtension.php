<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit\Stub;

use Oro\Bundle\EntityExtendBundle\EntityExtend\EntityFieldExtensionInterface;
use Oro\Bundle\EntityExtendBundle\EntityExtend\EntityFieldProcessTransport;

/**
 * Keeps values of the given extend fields in the entity extend storage.
 */
class ExtendFieldsStorageTestExtension implements EntityFieldExtensionInterface
{
    /**
     * @param array<string,string[]> $fields [class name => [field name, ...], ...]
     */
    public function __construct(
        private readonly array $fields
    ) {
    }

    #[\Override]
    public function get(EntityFieldProcessTransport $transport): void
    {
        if ($this->isSupported($transport)) {
            $storage = $transport->getStorage();
            $name = $transport->getName();
            $transport->setResult($storage->offsetExists($name) ? $storage[$name] : null);
            $transport->setProcessed(true);
        }
    }

    #[\Override]
    public function set(EntityFieldProcessTransport $transport): void
    {
        if ($this->isSupported($transport)) {
            $transport->getStorage()->offsetSet($transport->getName(), $transport->getValue());
            $transport->setProcessed(true);
        }
    }

    #[\Override]
    public function call(EntityFieldProcessTransport $transport): void
    {
    }

    #[\Override]
    public function isset(EntityFieldProcessTransport $transport): void
    {
    }

    #[\Override]
    public function propertyExists(EntityFieldProcessTransport $transport): void
    {
    }

    #[\Override]
    public function methodExists(EntityFieldProcessTransport $transport): void
    {
    }

    #[\Override]
    public function getMethods(EntityFieldProcessTransport $transport): array
    {
        return [];
    }

    #[\Override]
    public function getMethodInfo(EntityFieldProcessTransport $transport): void
    {
    }

    private function isSupported(EntityFieldProcessTransport $transport): bool
    {
        return \in_array($transport->getName(), $this->fields[\get_class($transport->getObject())] ?? [], true);
    }
}
