<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit;

use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use App\Bundle\SplitDeliveryBundle\Tests\Unit\Stub\ExtendFieldsStorageTestExtension;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Oro\Bundle\EntityExtendBundle\Test\EntityExtendTestInitializer;
use Oro\Bundle\OrderBundle\Entity\OrderLineItem;
use Oro\Component\Testing\ReflectionUtil;

/**
 * Makes the split delivery extend fields available in unit tests.
 */
trait SplitDeliveryFieldsTestTrait
{
    private ExtendFieldsStorageTestExtension $splitDeliveryFieldsExtension;

    /**
     * @before
     */
    protected function addSplitDeliveryFieldsExtension(): void
    {
        $this->splitDeliveryFieldsExtension = new ExtendFieldsStorageTestExtension([
            CheckoutLineItem::class => [SplitDeliveryFields::CHECKOUT_LINE_ITEM_ADDRESS_KEY],
            OrderLineItem::class => [SplitDeliveryFields::ORDER_LINE_ITEM_SHIPPING_ADDRESS],
        ]);
        EntityExtendTestInitializer::addExtension($this->splitDeliveryFieldsExtension);
    }

    /**
     * @after
     */
    protected function removeSplitDeliveryFieldsExtension(): void
    {
        EntityExtendTestInitializer::removeExtension($this->splitDeliveryFieldsExtension);
    }

    private function createLineItem(
        int $id,
        ?string $addressKey = null,
        ?string $checksum = null,
        ?Checkout $checkout = null
    ): CheckoutLineItem {
        $lineItem = new CheckoutLineItem();
        ReflectionUtil::setId($lineItem, $id);
        $lineItem->setProductSku('SKU' . $id);
        $lineItem->setProductUnitCode('item');
        $lineItem->setChecksum($checksum ?? 'checksum' . $id);
        SplitDeliveryFields::setAddressKey($lineItem, $addressKey);
        $checkout?->addLineItem($lineItem);

        return $lineItem;
    }
}
