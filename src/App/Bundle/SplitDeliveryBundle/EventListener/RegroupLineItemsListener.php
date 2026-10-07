<?php

namespace App\Bundle\SplitDeliveryBundle\EventListener;

use App\Bundle\SplitDeliveryBundle\Form\Extension\SplitDeliveryTransitionTypeExtension;
use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\Provider\SplitDeliveryConfigProvider;
use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Oro\Bundle\CheckoutBundle\Helper\CheckoutWorkflowHelper;
use Oro\Bundle\CheckoutBundle\Provider\MultiShipping\GroupedCheckoutLineItemsProvider;
use Oro\Bundle\WorkflowBundle\Event\Transition\TransitionEvent;

/**
 * Before the checkout leaves the shipping address step:
 * - resets line item shipping addresses that equal the main shipping address or are not available anymore;
 * - re-groups checkout line items, as their grouping depends on the shipping addresses.
 */
class RegroupLineItemsListener
{
    public function __construct(
        private readonly SplitDeliveryConfigProvider $configProvider,
        private readonly ShipAddressProvider $shipAddressProvider,
        private readonly GroupedCheckoutLineItemsProvider $groupedLineItemsProvider
    ) {
    }

    public function onTransition(TransitionEvent $event): void
    {
        if (SplitDeliveryTransitionTypeExtension::TRANSITION_NAME !== $event->getTransition()->getName()) {
            return;
        }

        $workflowItem = $event->getWorkflowItem();
        $checkout = $workflowItem->getEntity();
        if (
            !$checkout instanceof Checkout
            || !CheckoutWorkflowHelper::isMultiStepCheckoutWorkflow($workflowItem)
            || !$this->configProvider->isEnabled()
        ) {
            return;
        }

        $this->normalizeAddressKeys($checkout);

        $workflowItem->getData()->set(
            'grouped_line_items',
            $this->groupedLineItemsProvider->getGroupedLineItemsIds($checkout)
        );
    }

    private function normalizeAddressKeys(Checkout $checkout): void
    {
        $mainAddressKey = $this->getMainAddressKey($checkout);
        /** @var CheckoutLineItem $lineItem */
        foreach ($checkout->getLineItems() as $lineItem) {
            $addressKey = SplitDeliveryFields::getAddressKey($lineItem);
            if (
                $addressKey
                && ($addressKey === $mainAddressKey || !$this->shipAddressProvider->resolve($checkout, $addressKey))
            ) {
                SplitDeliveryFields::setAddressKey($lineItem, null);
            }
        }
    }

    private function getMainAddressKey(Checkout $checkout): ?string
    {
        if (!$checkout->isShipToBillingAddress()) {
            return $this->shipAddressProvider->getMainAddressKey($checkout);
        }

        // The shipping address is copied from the billing address later, during the transition execution.
        $billingAddress = $checkout->getBillingAddress();
        $address = $billingAddress?->getCustomerUserAddress() ?? $billingAddress?->getCustomerAddress();
        foreach ($this->shipAddressProvider->getAddresses($checkout) as $key => $shippingAddress) {
            if ($shippingAddress === $address) {
                return $key;
            }
        }

        return null;
    }
}
