<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit\Model;

use App\Bundle\SplitDeliveryBundle\Model\SplitDeliveryCheckoutManipulator;
use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use App\Bundle\SplitDeliveryBundle\Tests\Unit\SplitDeliveryFieldsTestTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Model\CheckoutBySourceCriteriaManipulatorInterface;
use Oro\Bundle\WebsiteBundle\Entity\Website;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class SplitDeliveryCheckoutManipulatorTest extends TestCase
{
    use SplitDeliveryFieldsTestTrait;

    private CheckoutBySourceCriteriaManipulatorInterface&MockObject $innerManipulator;
    private SplitDeliveryCheckoutManipulator $manipulator;

    #[\Override]
    protected function setUp(): void
    {
        $this->innerManipulator = $this->createMock(CheckoutBySourceCriteriaManipulatorInterface::class);
        $this->manipulator = new SplitDeliveryCheckoutManipulator($this->innerManipulator);
    }

    public function testActualizeCheckoutRestoresAddressKeys(): void
    {
        $website = new Website();
        $checkout = new Checkout();
        $this->createLineItem(1, 'au_2', null, $checkout);
        $this->createLineItem(2, null, null, $checkout);
        $this->createLineItem(3, 'a_4', null, $checkout);

        $newLineItem1 = $this->createLineItem(11, null, 'checksum1');
        $newLineItem2 = $this->createLineItem(12, null, 'checksum2');
        $newLineItem5 = $this->createLineItem(15, null, 'checksum5');

        $this->innerManipulator->expects(self::once())
            ->method('actualizeCheckout')
            ->with(self::identicalTo($checkout), self::identicalTo($website), ['shoppingList' => 1], 'USD', [], true)
            ->willReturnCallback(
                static function (Checkout $checkout) use ($newLineItem1, $newLineItem2, $newLineItem5) {
                    $checkout->setLineItems(new ArrayCollection([$newLineItem1, $newLineItem2, $newLineItem5]));

                    return $checkout;
                }
            );

        $result = $this->manipulator->actualizeCheckout($checkout, $website, ['shoppingList' => 1], 'USD', [], true);

        self::assertSame($checkout, $result);
        self::assertSame('au_2', SplitDeliveryFields::getAddressKey($newLineItem1));
        self::assertNull(SplitDeliveryFields::getAddressKey($newLineItem2));
        self::assertNull(SplitDeliveryFields::getAddressKey($newLineItem5));
    }

    public function testFindCheckout(): void
    {
        $checkout = new Checkout();
        $this->innerManipulator->expects(self::once())
            ->method('findCheckout')
            ->with(['shoppingList' => 1], null, 'USD', 'b2b_flow_checkout')
            ->willReturn($checkout);

        self::assertSame(
            $checkout,
            $this->manipulator->findCheckout(['shoppingList' => 1], null, 'USD', 'b2b_flow_checkout')
        );
    }
}
