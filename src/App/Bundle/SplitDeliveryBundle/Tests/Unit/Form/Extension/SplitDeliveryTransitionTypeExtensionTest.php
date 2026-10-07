<?php

namespace App\Bundle\SplitDeliveryBundle\Tests\Unit\Form\Extension;

use App\Bundle\SplitDeliveryBundle\Form\Extension\SplitDeliveryTransitionTypeExtension;
use App\Bundle\SplitDeliveryBundle\Form\Type\SplitDeliveryType;
use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\Provider\SplitDeliveryConfigProvider;
use App\Bundle\SplitDeliveryBundle\Tests\Unit\SplitDeliveryFieldsTestTrait;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowDefinition;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Form\Type\WorkflowTransitionType;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Form\FormBuilderInterface;

class SplitDeliveryTransitionTypeExtensionTest extends TestCase
{
    use SplitDeliveryFieldsTestTrait;

    private const array CHOICES = ['Address 1' => 'a_1', 'Address 2' => 'au_2'];

    private SplitDeliveryConfigProvider&MockObject $configProvider;
    private ShipAddressProvider&MockObject $shipAddressProvider;
    private FormBuilderInterface&MockObject $builder;
    private SplitDeliveryTransitionTypeExtension $extension;

    #[\Override]
    protected function setUp(): void
    {
        $this->configProvider = $this->createMock(SplitDeliveryConfigProvider::class);
        $this->shipAddressProvider = $this->createMock(ShipAddressProvider::class);
        $this->builder = $this->createMock(FormBuilderInterface::class);

        $this->extension = new SplitDeliveryTransitionTypeExtension(
            $this->configProvider,
            $this->shipAddressProvider
        );
    }

    private function createWorkflowItem(
        Checkout $checkout,
        bool $isCheckoutWorkflow = true,
        bool $disallowAddressEdit = false
    ): WorkflowItem {
        $definition = new WorkflowDefinition();
        $definition->setMetadata(['is_checkout_workflow' => $isCheckoutWorkflow]);
        $workflowItem = new WorkflowItem();
        $workflowItem->setDefinition($definition);
        $workflowItem->setEntity($checkout);
        $workflowItem->getData()->set('disallow_shipping_address_edit', $disallowAddressEdit);

        return $workflowItem;
    }

    private function createCheckout(int $lineItemsCount = 2): Checkout
    {
        $checkout = new Checkout();
        for ($i = 1; $i <= $lineItemsCount; $i++) {
            $this->createLineItem($i, null, null, $checkout);
        }

        return $checkout;
    }

    public function testGetExtendedTypes(): void
    {
        self::assertSame([WorkflowTransitionType::class], SplitDeliveryTransitionTypeExtension::getExtendedTypes());
    }

    public function testBuildForm(): void
    {
        $checkout = $this->createCheckout();
        $this->configProvider->expects(self::once())->method('isEnabled')->willReturn(true);
        $this->shipAddressProvider->expects(self::once())
            ->method('getChoices')
            ->with(self::identicalTo($checkout))
            ->willReturn(self::CHOICES);

        $this->builder->expects(self::once())
            ->method('add')
            ->with('app_split_delivery', SplitDeliveryType::class, [
                'checkout' => $checkout,
                'address_choices' => self::CHOICES,
            ]);
        $this->builder->expects(self::once())
            ->method('addEventListener');

        $this->extension->buildForm($this->builder, [
            'workflow_item' => $this->createWorkflowItem($checkout),
            'transition_name' => 'continue_to_shipping_method',
        ]);
    }

    /**
     * @dataProvider notApplicableDataProvider
     */
    public function testBuildFormWhenNotApplicable(
        string $transitionName,
        int $lineItemsCount,
        bool $isCheckoutWorkflow,
        bool $disallowAddressEdit,
        bool $enabled,
        array $choices
    ): void {
        $checkout = $this->createCheckout($lineItemsCount);
        $this->configProvider->expects(self::any())->method('isEnabled')->willReturn($enabled);
        $this->shipAddressProvider->expects(self::any())->method('getChoices')->willReturn($choices);

        $this->builder->expects(self::never())->method('add');
        $this->builder->expects(self::never())->method('addEventListener');

        $this->extension->buildForm($this->builder, [
            'workflow_item' => $this->createWorkflowItem($checkout, $isCheckoutWorkflow, $disallowAddressEdit),
            'transition_name' => $transitionName,
        ]);
    }

    public function notApplicableDataProvider(): array
    {
        return [
            'other transition' => ['continue_to_payment', 2, true, false, true, self::CHOICES],
            'one line item' => ['continue_to_shipping_method', 1, true, false, true, self::CHOICES],
            'not a checkout workflow' => ['continue_to_shipping_method', 2, false, false, true, self::CHOICES],
            'address edit disallowed' => ['continue_to_shipping_method', 2, true, true, true, self::CHOICES],
            'feature disabled' => ['continue_to_shipping_method', 2, true, false, false, self::CHOICES],
            'one address' => ['continue_to_shipping_method', 2, true, false, true, ['Address 1' => 'a_1']],
        ];
    }
}
