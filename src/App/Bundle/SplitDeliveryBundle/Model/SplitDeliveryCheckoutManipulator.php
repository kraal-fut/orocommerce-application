<?php

namespace App\Bundle\SplitDeliveryBundle\Model;

use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Oro\Bundle\CheckoutBundle\Model\CheckoutBySourceCriteriaManipulatorInterface;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Keeps the shipping addresses chosen for checkout line items when the checkout line items
 * are re-created from the checkout source (e.g. when a checkout is re-started from a shopping list).
 */
class SplitDeliveryCheckoutManipulator implements CheckoutBySourceCriteriaManipulatorInterface
{
    public function __construct(
        private readonly CheckoutBySourceCriteriaManipulatorInterface $innerManipulator
    ) {
    }

    #[\Override]
    public function createCheckout(
        Website $website,
        array $sourceCriteria,
        ?UserInterface $customerUser = null,
        ?string $currency = null,
        array $checkoutData = []
    ): Checkout {
        return $this->innerManipulator->createCheckout(
            $website,
            $sourceCriteria,
            $customerUser,
            $currency,
            $checkoutData
        );
    }

    #[\Override]
    public function findCheckout(
        array $sourceCriteria,
        ?UserInterface $customerUser,
        ?string $currency,
        ?string $workflowName = null
    ): ?Checkout {
        return $this->innerManipulator->findCheckout($sourceCriteria, $customerUser, $currency, $workflowName);
    }

    #[\Override]
    public function actualizeCheckout(
        Checkout $checkout,
        ?Website $website,
        array $sourceCriteria,
        ?string $currency,
        array $checkoutData = [],
        bool $updateData = false
    ): Checkout {
        $addressKeys = $this->getAddressKeys($checkout);

        $checkout = $this->innerManipulator->actualizeCheckout(
            $checkout,
            $website,
            $sourceCriteria,
            $currency,
            $checkoutData,
            $updateData
        );

        if ($addressKeys) {
            /** @var CheckoutLineItem $lineItem */
            foreach ($checkout->getLineItems() as $lineItem) {
                $addressKey = $addressKeys[$this->getMatchKey($lineItem)] ?? null;
                if ($addressKey) {
                    SplitDeliveryFields::setAddressKey($lineItem, $addressKey);
                }
            }
        }

        return $checkout;
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
                $addressKeys[$this->getMatchKey($lineItem)] = $addressKey;
            }
        }

        return $addressKeys;
    }

    private function getMatchKey(CheckoutLineItem $lineItem): string
    {
        return SplitDeliveryFields::getLineItemMatchKey(
            $lineItem->getChecksum(),
            $lineItem->getProductSku(),
            $lineItem->getProductUnitCode()
        );
    }
}
