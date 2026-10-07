<?php

namespace App\Bundle\SplitDeliveryBundle\Provider;

use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Oro\Bundle\CheckoutBundle\Provider\MultiShipping\LineItemGroupTitleProvider;
use Oro\Bundle\ShippingBundle\Provider\GroupLineItemHelper;
use Symfony\Contracts\Translation\TranslatorInterface;

/**
 * Provides "Ship to: <address>" titles for line items grouped by shipping address
 * and delegates titles of other groups to the decorated provider.
 * Extends the decorated class because its consumers depend on the class, not on an interface.
 */
class SplitDeliveryLineItemGroupTitleProvider extends LineItemGroupTitleProvider
{
    private const string GROUP_KEY_PREFIX =
        SplitDeliveryFields::CHECKOUT_LINE_ITEM_ADDRESS_KEY . GroupLineItemHelper::GROUPING_DELIMITER;

    /**
     * The parent constructor is not called on purpose: all calls not related to the split delivery
     * are delegated to the decorated provider.
     */
    public function __construct(
        private readonly LineItemGroupTitleProvider $innerProvider,
        private readonly ShipAddressProvider $shipAddressProvider,
        private readonly TranslatorInterface $translator
    ) {
    }

    #[\Override]
    public function getTitle(string $lineItemGroupKey, object $lineItem): string
    {
        if (!str_starts_with($lineItemGroupKey, self::GROUP_KEY_PREFIX)) {
            return $this->innerProvider->getTitle($lineItemGroupKey, $lineItem);
        }

        $checkout = $lineItem instanceof CheckoutLineItem ? $lineItem->getCheckout() : null;
        if (null === $checkout) {
            return $this->translator->trans('app.split_delivery.line_item_group.main_address.title');
        }

        $addressKey = substr($lineItemGroupKey, \strlen(self::GROUP_KEY_PREFIX));
        $address = $this->shipAddressProvider->resolve($checkout, $addressKey) ?? $checkout->getShippingAddress();
        if (null === $address) {
            return $this->translator->trans('app.split_delivery.line_item_group.main_address.title');
        }

        return $this->translator->trans(
            'app.split_delivery.line_item_group.ship_to.title',
            ['%address%' => $this->shipAddressProvider->formatAddress($address)]
        );
    }
}
