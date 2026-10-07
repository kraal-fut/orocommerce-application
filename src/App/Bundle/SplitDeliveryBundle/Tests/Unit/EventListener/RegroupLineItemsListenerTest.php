<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit\EventListener;

use App\Bundle\SplitDeliveryBundle\EventListener\RegroupLineItemsListener;
use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\Provider\SplitDeliveryConfigProvider;
use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use App\Bundle\SplitDeliveryBundle\Tests\Unit\SplitDeliveryFieldsTestTrait;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Provider\MultiShipping\GroupedCheckoutLineItemsProvider;
use Oro\Bundle\CustomerBundle\Entity\CustomerUserAddress;
use Oro\Bundle\OrderBundle\Entity\OrderAddress;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Event\Transition\TransitionEvent;
use Oro\Bundle\WorkflowBundle\Model\Transition;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class RegroupLineItemsListenerTest extends TestCase
{
    use SplitDeliveryFieldsTestTrait;

    private SplitDeliveryConfigProvider&MockObject $configProvider;
    private ShipAddressProvider&MockObject $shipAddressProvider;
    private GroupedCheckoutLineItemsProvider&MockObject $groupedLineItemsProvider;
    private RegroupLineItemsListener $listener;

    #[\Override]
    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(SplitDeliveryConfigProvider::class);
        $this->shipAddressProvider = $this->createMock(ShipAddressProvider::class);
        $this->groupedLineItemsProvider = $this->createMock(GroupedCheckoutLineItemsProvider::class);

        $this->listener = new RegroupLineItemsListener(
            $this->configProvider,
            $this->shipAddressProvider,
            $this->groupedLineItemsProvider
        );
    }

    private function createEvent(Checkout $checkout, string $transitionName): TransitionEvent
    {
        $definition = new WorkflowDefinition();
        $definition->setMetadata(['is_checkout_workflow' => true]);
        $workflowItem = new WorkflowItem();
        $workflowItem->setDefinition($definition);
        $workflowItem->setEntity($checkout);
        $transition = $this->createMock(Transition::class);
        $transition->expects(self::any())->method('getName')->willReturn($transitionName);

        return new TransitionEvent($workflowItem, $transition);
    }

    public function testOnTransitionForOtherTransition(): void
    {
        $this->configProvider->expects(self::never())->method('isEnabled');

        $this->listener->onTransition($this->createEvent(new Checkout(), 'continue_to_payment'));
    }

    public function testOnTransitionWhenFeatureDisabled(): void
    {
        $checkout = new Checkout();
        $lineItem = $this->createLineItem(1, 'au_2', null, $checkout);
        $this->configProvider->expects(self::once())->method('isEnabled')->willReturn(false);
        $this->groupedLineItemsProvider->expects(self::never())->method('getGroupedLineItemsIds');

        $event = $this->createEvent($checkout, 'continue_to_shipping_method');
        $this->listener->onTransition($event);

        self::assertSame('au_2', SplitDeliveryFields::getAddressKey($lineItem));
        self::assertFalse($event->getWorkflowItem()->getData()->has('grouped_line_items'));
    }

    public function testOnTransition(): void
    {
        $checkout = new Checkout();
        $mainAddressLineItem = $this->createLineItem(1, 'au_1', null, $checkout);
        $otherAddressLineItem = $this->createLineItem(2, 'au_2', null, $checkout);
        $unavailableAddressLineItem = $this->createLineItem(3, 'au_3', null, $checkout);
        $groupedIds = ['app_ship_address_key:0' => [1, 3], 'app_ship_address_key:au_2' => [2]];

        $this->configProvider->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->shipAddressProvider->expects(self::once())->method('getMainAddressKey')->willReturn('au_1');
        $this->shipAddressProvider->expects(self::exactly(2))
            ->method('resolve')
            ->willReturnMap([
                [$checkout, 'au_2', new CustomerUserAddress()],
                [$checkout, 'au_3', null],
            ]);
        $this->groupedLineItemsProvider->expects(self::once())
            ->method('getGroupedLineItemsIds')
            ->with(self::identicalTo($checkout))
            ->willReturn($groupedIds);

        $event = $this->createEvent($checkout, 'continue_to_shipping_method');
        $this->listener->onTransition($event);

        self::assertNull(SplitDeliveryFields::getAddressKey($mainAddressLineItem));
        self::assertSame('au_2', SplitDeliveryFields::getAddressKey($otherAddressLineItem));
        self::assertNull(SplitDeliveryFields::getAddressKey($unavailableAddressLineItem));
        self::assertSame($groupedIds, $event->getWorkflowItem()->getData()->get('grouped_line_items'));
    }

    public function testOnTransitionWhenShipToBillingAddress(): void
    {
        $billingCustomerUserAddress = new CustomerUserAddress();
        $billingAddress = new OrderAddress();
        $billingAddress->setCustomerUserAddress($billingCustomerUserAddress);
        $checkout = new Checkout();
        $checkout->setShipToBillingAddress(true);
        $checkout->setBillingAddress($billingAddress);
        $lineItem = $this->createLineItem(1, 'au_1', null, $checkout);

        $this->configProvider->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->shipAddressProvider->expects(self::never())->method('getMainAddressKey');
        $this->shipAddressProvider->expects(self::once())
            ->method('getAddresses')
            ->willReturn(['au_1' => $billingCustomerUserAddress, 'au_2' => new CustomerUserAddress()]);
        $this->groupedLineItemsProvider->expects(self::once())->method('getGroupedLineItemsIds')->willReturn([]);

        $this->listener->onTransition($this->createEvent($checkout, 'continue_to_shipping_method'));

        self::assertNull(SplitDeliveryFields::getAddressKey($lineItem));
    }
}
