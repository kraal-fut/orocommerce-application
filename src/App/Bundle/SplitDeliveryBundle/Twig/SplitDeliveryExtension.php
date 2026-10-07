<?php

namespace App\Bundle\SplitDeliveryBundle\Twig;

use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use Oro\Bundle\OrderBundle\Entity\Order;
use Psr\Container\ContainerInterface;
use Symfony\Contracts\Service\ServiceSubscriberInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/**
 * Provides a Twig function to get shipping addresses of order line items:
 *   - app_split_delivery_ship_addresses
 */
class SplitDeliveryExtension extends AbstractExtension implements ServiceSubscriberInterface
{
    public function __construct(
        private readonly ContainerInterface $container
    ) {
    }

    #[\Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('app_split_delivery_ship_addresses', [$this, 'getShipAddresses']),
        ];
    }

    /**
     * @return array<int,string> [order line item id => formatted shipping address, ...]
     */
    public function getShipAddresses(Order $order): array
    {
        $result = [];
        foreach ($order->getLineItems() as $lineItem) {
            $address = SplitDeliveryFields::getShippingAddress($lineItem);
            if ($address) {
                $result[$lineItem->getId()] = $this->getShipAddressProvider()->formatAddress($address);
            }
        }

        return $result;
    }

    #[\Override]
    public static function getSubscribedServices(): array
    {
        return [
            ShipAddressProvider::class,
        ];
    }

    private function getShipAddressProvider(): ShipAddressProvider
    {
        return $this->container->get(ShipAddressProvider::class);
    }
}
