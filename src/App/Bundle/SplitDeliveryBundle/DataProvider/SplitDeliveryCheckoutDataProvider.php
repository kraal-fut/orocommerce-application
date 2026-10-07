<?php

namespace App\Bundle\SplitDeliveryBundle\DataProvider;

use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\Provider\SplitDeliveryConfigProvider;
use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Component\Checkout\DataProvider\CheckoutDataProviderInterface;
use Symfony\Contracts\Service\ResetInterface;

/**
 * Adds the shipping address of each checkout line item to the line items data,
 * so it is copied to the order line items created from the checkout.
 */
class SplitDeliveryCheckoutDataProvider implements CheckoutDataProviderInterface, ResetInterface
{
    /** @var array<string,OrderAddress> */
    private array $orderAddresses = [];

    public function __construct(
        private readonly CheckoutDataProviderInterface $innerDataProvider,
        private readonly SplitDeliveryConfigProvider $configProvider,
        private readonly ShipAddressProvider $shipAddressProvider
    ) {
    }

    #[\Override]
    public function reset(): void
    {
        $this->orderAddresses = [];
    }

    #[\Override]
    public function isEntitySupported(object $entity): bool
    {
        return $this->innerDataProvider->isEntitySupported($entity);
    }

    #[\Override]
    public function getData(object $entity): array
    {
        $data = $this->innerDataProvider->getData($entity);
        if (!$entity instanceof Checkout || !$this->configProvider->isEnabled()) {
            return $data;
        }

        $addressKeys = $this->getAddressKeys($entity);
        if (!$addressKeys) {
            return $data;
        }

        foreach ($data as &$lineItemData) {
            $matchKey = SplitDeliveryFields::getLineItemMatchKey(
                $lineItemData['checksum'] ?? null,
                $lineItemData['productSku'] ?? null,
                $lineItemData['productUnitCode'] ?? null
            );
            $orderAddress = $this->getOrderAddress($entity, $addressKeys[$matchKey] ?? null);
            if ($orderAddress) {
                $lineItemData[SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS] = $orderAddress;
            }
        }

        return $data;
    }

    /**
     * @return array<string,string> [line item match key => address key, ...]
     */
    private function getAddressKeys(Checkout $checkout): array
    {
        $addressKeys = [];
        /** @var CheckoutLineItem $lineItem */
        foreach ($checkout->getLineItems() as $lineItem) {
            $addressKey = SplitDeliveryFields::getAddressKey($lineItem);
            if ($addressKey) {
                $matchKey = SplitDeliveryFields::getLineItemMatchKey(
                    $lineItem->getChecksum(),
                    $lineItem->getProductSku(),
                    $lineItem->getProductUnitCode()
                );
                $addressKeys[$matchKey] = $addressKey;
            }
        }

        return $addressKeys;
    }

    /**
     * Line items shipped to the same address share one order address.
     */
    private function getOrderAddress(Checkout $checkout, ?string $addressKey): ?OrderAddress
    {
        if (!$addressKey) {
            return null;
        }

        $cacheKey = sprintf('%d|%s', spl_object_id($checkout), $addressKey);
        if (!\array_key_exists($cacheKey, $this->orderAddresses)) {
            $this->orderAddresses[$cacheKey] = $this->shipAddressProvider->createOrderAddress($checkout, $addressKey);
        }

        return $this->orderAddresses[$cacheKey];
    }
}
