<?php

/*
 * HiPay payment integration for Sylius
 *
 * (c) Hipay
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace HiPay\SyliusHiPayPlugin\Twig\Component\Shop;

use Sylius\Bundle\UiBundle\Twig\Component\ResourceFormComponent;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * LiveComponent wrapper for the order show (repayment) payment form.
 *
 * Extends the Sylius ResourceFormComponent so the payment method
 * selection is rendered as a Live Component, enabling the HiPay
 * hipay_hosted_fields LiveComponent (Hosted Fields) to work on the
 * order repayment page the same way it does during checkout.
 */
class OrderShowPaymentFormComponent extends ResourceFormComponent
{
    /**
     * Match sylius_shop_order_show routing (validation_groups: sylius_order_pay).
     * Without this, the Live form uses default groups and behaviour diverges from the controller form.
     */
    protected function instantiateForm(): FormInterface
    {
        return $this->formFactory->create($this->formClass, $this->resource, [
            'validation_groups' => ['sylius_order_pay'],
        ]);
    }

    /**
     * Rebuild the form view after hydration so the template always
     * has the "form" variable on subsequent re-renders.
     */
    #[PostHydrate]
    public function ensureFormViewReady(): void
    {
        if ($this->resource !== null) {
            $this->getFormView();
        }
    }

    #[ExposeInTemplate(name: 'form', getter: 'getFormView')]
    public function getFormView(): FormView
    {
        return parent::getFormView();
    }
}
