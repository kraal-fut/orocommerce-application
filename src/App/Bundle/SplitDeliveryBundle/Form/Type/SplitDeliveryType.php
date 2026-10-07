<?php

namespace App\Bundle\SplitDeliveryBundle\Form\Type;

use App\Bundle\SplitDeliveryBundle\SplitDeliveryFields;
use Oro\Bundle\CheckoutBundle\Entity\Checkout;
use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\FormType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * "Ship to multiple addresses" form: a toggle and a shipping address choice for each checkout line item.
 * Use {@see SplitDeliveryType::applyToCheckout} to store the submitted addresses in the checkout line items.
 */
class SplitDeliveryType extends AbstractType
{
    public const string ENABLED_FIELD = 'enabled';
    public const string RENDERED_FIELD = 'rendered';
    public const string ITEMS_FIELD = 'items';

    #[\Override]
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        /** @var Checkout $checkout */
        $checkout = $options['checkout'];

        $enabled = false;
        $itemsBuilder = $builder->create(self::ITEMS_FIELD, FormType::class, ['label' => false]);
        /** @var CheckoutLineItem $lineItem */
        foreach ($checkout->getLineItems() as $lineItem) {
            $addressKey = SplitDeliveryFields::getAddressKey($lineItem);
            if ($addressKey && !\in_array($addressKey, $options['address_choices'], true)) {
                $addressKey = null;
            }
            $enabled = $enabled || null !== $addressKey;

            $itemsBuilder->add(self::getLineItemFieldName($lineItem), LineItemShipAddressType::class, [
                'line_item' => $lineItem,
                'choices' => $options['address_choices'],
                'data' => $addressKey,
                'label' => false,
            ]);
        }

        $builder
            ->add(self::ENABLED_FIELD, CheckboxType::class, [
                'label' => 'app.split_delivery.form.enabled.label',
                'required' => false,
                'data' => $enabled,
            ])
            // Allows to distinguish a submitted form with the unchecked toggle from a form that was not rendered.
            ->add(self::RENDERED_FIELD, HiddenType::class, ['data' => '1'])
            ->add($itemsBuilder);
    }

    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired(['checkout', 'address_choices'])
            ->setAllowedTypes('checkout', Checkout::class)
            ->setAllowedTypes('address_choices', 'array')
            ->setDefaults([
                'mapped' => false,
                'label' => false,
                'required' => false,
            ]);
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'app_split_delivery';
    }

    /**
     * Stores the submitted shipping addresses in the checkout line items.
     */
    public static function applyToCheckout(FormInterface $form, Checkout $checkout): void
    {
        if ('1' !== $form->get(self::RENDERED_FIELD)->getData()) {
            return;
        }

        $enabled = (bool)$form->get(self::ENABLED_FIELD)->getData();
        $itemsForm = $form->get(self::ITEMS_FIELD);
        /** @var CheckoutLineItem $lineItem */
        foreach ($checkout->getLineItems() as $lineItem) {
            $fieldName = self::getLineItemFieldName($lineItem);
            $addressKey = $enabled && $itemsForm->has($fieldName) ? $itemsForm->get($fieldName)->getData() : null;
            SplitDeliveryFields::setAddressKey($lineItem, $addressKey);
        }
    }

    private static function getLineItemFieldName(CheckoutLineItem $lineItem): string
    {
        return 'li_' . $lineItem->getId();
    }
}
