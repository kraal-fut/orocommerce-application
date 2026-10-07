<?php

namespace App\Bundle\SplitDeliveryBundle\Form\Type;

use Oro\Bundle\CheckoutBundle\Entity\CheckoutLineItem;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * Shipping address choice for one checkout line item.
 * Choices are address keys; the empty value means the main checkout shipping address.
 */
class LineItemShipAddressType extends AbstractType
{
    #[\Override]
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver
            ->setRequired('line_item')
            ->setAllowedTypes('line_item', CheckoutLineItem::class)
            ->setDefaults([
                'required' => false,
                'placeholder' => 'app.split_delivery.form.main_address.label',
                'choice_translation_domain' => false,
            ]);
    }

    #[\Override]
    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['line_item'] = $options['line_item'];
    }

    #[\Override]
    public function getParent(): string
    {
        return ChoiceType::class;
    }

    #[\Override]
    public function getBlockPrefix(): string
    {
        return 'app_split_delivery_line_item_ship_address';
    }
}
