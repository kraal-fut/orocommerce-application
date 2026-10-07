<?php

namespace App\Bundle\SplitDeliveryBundle\Form\Extension;

use App\Bundle\SplitDeliveryBundle\Form\Type\SplitDeliveryType;
use App\Bundle\SplitDeliveryBundle\Provider\ShipAddressProvider;
use App\Bundle\SplitDeliveryBundle\Provider\SplitDeliveryConfigProvider;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Helper\CheckoutWorkflowHelper;
use Oro\Bundle\WorkflowBundle\Entity\WorkflowItem;
use Oro\Bundle\WorkflowBundle\Form\Type\WorkflowTransitionType;
use Symfony\Component\Form\AbstractTypeExtension;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

/**
 * Adds the "Ship to multiple addresses" form to the shipping address step of the multi-step checkout.
 */
class SplitDeliveryTransitionTypeExtension extends AbstractTypeExtension
{
    public const string FIELD_NAME = 'app_split_delivery';
    public const string TRANSITION_NAME = 'continue_to_shipping_method';

    public function __construct(
        private readonly SplitDeliveryConfigProvider $configProvider,
        private readonly ShipAddressProvider $shipAddressProvider
    ) {
    }

    #[\Override]
    public static function getExtendedTypes(): iterable
    {
        return [WorkflowTransitionType::class];
    }

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var WorkflowItem $workflowItem */
        $workflowItem = $options['workflow_item'];
        $checkout = $this->getApplicableCheckout($workflowItem, $options['transition_name']);
        if (null === $checkout) {
            return;
        }

        $addressChoices = $this->shipAddressProvider->getChoices($checkout);
        if (\count($addressChoices) < 2) {
            return;
        }

        $builder->add(self::FIELD_NAME, SplitDeliveryType::class, [
            'checkout' => $checkout,
            'address_choices' => $addressChoices,
        ]);

        // Runs after the form validation listener (priority 0) to store addresses only when the form is valid.
        $builder->addEventListener(
            FormEvents::POST_SUBMIT,
            static function (FormEvent $event) use ($checkout) {
                $form = $event->getForm();
                if ($form->isValid()) {
                    SplitDeliveryType::applyToCheckout($form->get(self::FIELD_NAME), $checkout);
                }
            },
            -10
        );
    }

    private function getApplicableCheckout(WorkflowItem $workflowItem, string $transitionName): ?Checkout
    {
        if (self::TRANSITION_NAME !== $transitionName) {
            return null;
        }

        $checkout = $workflowItem->getEntity();
        if (
            !$checkout instanceof Checkout
            || $checkout->getLineItems()->count() < 2
            || !CheckoutWorkflowHelper::isMultiStepCheckoutWorkflow($workflowItem)
            || $workflowItem->getData()->get('disallow_shipping_address_edit')
            || !$this->configProvider->isEnabled()
        ) {
            return null;
        }

        return $checkout;
    }
}
