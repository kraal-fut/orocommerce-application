<?php

namespace App\Bundle\SplitDeliveryBundle\Provider;

use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use Oro\Bundle\CheckoutBundle\Provider\MultiShipping\ConfigProvider;

/**
 * Checks whether the split delivery (line items grouped by shipping address) is enabled.
 */
class SplitDeliveryConfigProvider
{
    public function __construct(
        private readonly ConfigProvider $multiShippingConfigProvider
    ) {
    }

    public function isEnabled(): bool
    {
        return
            $this->multiShippingConfigProvider->isLineItemsGroupingEnabled()
            && $this->multiShippingConfigProvider->getGroupLineItemsByField()
                === SplitDeliveryFields::CHECKOUT_LINE_ITEM_ADDRESS_KEY
            && !$this->multiShippingConfigProvider->isShippingSelectionByLineItemEnabled();
    }
}
