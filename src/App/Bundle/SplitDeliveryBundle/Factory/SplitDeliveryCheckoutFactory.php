<?php

namespace App\Bundle\SplitDeliveryBundle\Factory;

use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\Provider\SplitDeliveryConfigProvider;
use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Oro\Bundle\CheckoutBundle\Factory\MultiShipping\CheckoutFactoryInterface;

/**
 * Sets the shipping address of a split checkout to the address all its line items are shipped to.
 * As split checkouts are used to calculate shipping methods and costs of line item groups
 * and to create sub-orders, this makes each line items group to be shipped to its own address.
 */
class SplitDeliveryCheckoutFactory implements CheckoutFactoryInterface
{
    public function __construct(
        private readonly CheckoutFactoryInterface $innerFactory,
        private readonly SplitDeliveryConfigProvider $configProvider,
        private readonly ShipAddressProvider $shipAddressProvider
    ) {
    }

    #[\Override]
    public function createCheckout(Checkout $source, iterable $lineItems): Checkout
    {
        $checkout = $this->innerFactory->createCheckout($source, $lineItems);
        if (!$this->configProvider->isEnabled()) {
            return $checkout;
        }

        $addressKey = $this->getCommonAddressKey($checkout);
        if (!$addressKey) {
            return $checkout;
        }

        $shippingAddress = $this->shipAddressProvider->createOrderAddress($source, $addressKey);
        if ($shippingAddress) {
            $checkout->setShippingAddress($shippingAddress);
        }

        return $checkout;
    }

    private function getCommonAddressKey(Checkout $checkout): ?string
    {
        $addressKeys = [];
        /** @var CheckoutLineItem $lineItem */
        foreach ($checkout->getLineItems() as $lineItem) {
            $addressKeys[(string)SplitDeliveryFields::getAddressKey($lineItem)] = true;
        }

        return \count($addressKeys) === 1 ? (string)array_key_first($addressKeys) : null;
    }
}
