<?php

namespace App\Bundle\SplitDeliveryBundle;

use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Bundle\OrderBundle\Entity\OrderLineItem;

/**
 * Names of the split delivery extend fields and accessors for them.
 */
final class SplitDeliveryFields
{
    /**
     * Address book identifier ("a_1" / "au_1") a checkout line item ships to; null means the main shipping address.
     * Also used as the line items grouping field path.
     */
    public const string CHECKOUT_LINE_ITEM_ADDRESS_KEY = 'app_ship_address_key';

    /**
     * Shipping address of an order line item; null means the order shipping address.
     */
    public const string ORDER_LINE_ITEM_SHIPPING_ADDRESS = 'appShippingAddress';

    public static function getAddressKey(CheckoutLineItem $lineItem): ?string
    {
        $key = $lineItem->get(self::CHECKOUT_LINE_ITEM_ADDRESS_KEY);

        return $key ?: null;
    }

    public static function setAddressKey(CheckoutLineItem $lineItem, ?string $key): void
    {
        if (self::getAddressKey($lineItem) !== ($key ?: null)) {
            $lineItem->set(self::CHECKOUT_LINE_ITEM_ADDRESS_KEY, $key ?: null);
        }
    }

    public static function getShippingAddress(OrderLineItem $lineItem): ?OrderAddress
    {
        return $lineItem->get(self::ORDER_LINE_ITEM_SHIPPING_ADDRESS);
    }

    /**
     * Key used to match a checkout line item with its copies (re-created line items, line items data).
     */
    public static function getLineItemMatchKey(?string $checksum, ?string $sku, ?string $unitCode): string
    {
        return $checksum ?: sprintf('%s|%s', $sku, $unitCode);
    }
}
