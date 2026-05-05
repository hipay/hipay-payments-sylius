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

namespace HiPay\SyliusHiPayPlugin\Twig\Component\Admin;

use Sylius\Bundle\UiBundle\Twig\Component\ResourceFormComponent;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Component\Payment\Model\PaymentMethodInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\Attribute\PostHydrate;
use Symfony\UX\TwigComponent\Attribute\ExposeInTemplate;

/**
 * Payment method form as a LiveComponent that preserves gateway factory name
 * across re-renders so GatewayConfig (and Payum's usePayum, etc.) is built correctly.
 */
class PaymentMethodFormComponent extends ResourceFormComponent
{
    #[LiveProp(dehydrateWith: 'dehydrateGatewayFactoryName')]
    public ?string $gatewayFactoryName = null;

    /** @var class-string<GatewayConfigInterface> */
    private string $gatewayConfigClass;

    private RequestStack $requestStack;

    /**
     * @param class-string<GatewayConfigInterface> $gatewayConfigClass
     */
    public function __construct(
        RepositoryInterface $repository,
        FormFactoryInterface $formFactory,
        string $resourceClass,
        string $formClass,
        string $gatewayConfigClass,
        RequestStack $requestStack,
    ) {
        parent::__construct($repository, $formFactory, $resourceClass, $formClass);
        $this->gatewayConfigClass = $gatewayConfigClass;
        $this->requestStack = $requestStack;
    }

    /** @SuppressWarnings(PHPMD.UnusedFormalParameter) — $value required by LiveComponent dehydrateWith signature */
    public function dehydrateGatewayFactoryName(mixed $value): ?string
    {
        if ($this->resource instanceof PaymentMethodInterface) {
            return $this->resource->getGatewayConfig()?->getFactoryName();
        }

        return null;
    }

    /**
     * Restore GatewayConfig with factoryName after hydration so the form
     * (and Payum's usePayum field, gateway-specific config) is built correctly on re-render.
     */
    #[PostHydrate]
    public function restoreGatewayConfig(): void
    {
        if ($this->resource === null || $this->gatewayFactoryName === null || $this->gatewayFactoryName === '') {
            return;
        }

        if (!$this->resource instanceof PaymentMethodInterface) {
            return;
        }

        $config = $this->resource->getGatewayConfig();
        if ($config !== null && $config->getFactoryName() === $this->gatewayFactoryName) {
            return;
        }

        /** @var GatewayConfigInterface $newConfig */
        $newConfig = new $this->gatewayConfigClass();
        $newConfig->setFactoryName($this->gatewayFactoryName);
        $this->resource->setGatewayConfig($newConfig);
    }

    /**
     * Build form view after hydration so the template always has the "form" variable
     * on re-render (when the "form" prop from the hook is not passed).
     */
    #[PostHydrate]
    public function ensureFormViewReady(): void
    {
        if ($this->resource !== null) {
            $this->getFormView();
        }
    }

    /**
     * Make sure the resource carries a {@see GatewayConfigInterface} with a
     * non-null `factoryName` before the Symfony form is built — otherwise:
     *
     *   - Sylius core's {@see \Sylius\Bundle\PaymentBundle\Form\Type\GatewayConfigType}
     *     PRE_SET_DATA listener throws *"A factory name is required"* when the
     *     gateway config is missing or has no factoryName.
     *   - Third-party form extensions registered on `PaymentMethodType` (e.g.
     *     sylius/paypal-plugin's `PaymentMethodTypeExtension`) call
     *     `$data->getGatewayConfig()->getFactoryName()` without null-checking,
     *     and crash with *"Call to a member function getFactoryName() on null"*.
     *
     * On the "create new payment method" screen the factoryName is in the URL
     * (`/admin/payment-methods/new/{factory}`); we read it from the current
     * request as the source of truth. On the edit screen the resource already
     * has its persisted GatewayConfig, so this method is a no-op.
     *
     * The post-hydrate {@see restoreGatewayConfig()} continues to handle the
     * later live-component re-renders, where the factoryName comes from the
     * dehydrated `gatewayFactoryName` LiveProp.
     */
    private function ensureGatewayConfigStub(): void
    {
        if (!$this->resource instanceof PaymentMethodInterface) {
            return;
        }

        $config = $this->resource->getGatewayConfig();
        $factoryName = $config?->getFactoryName()
            ?? $this->gatewayFactoryName
            ?? $this->resolveFactoryNameFromRequest();

        if ($config !== null && $config->getFactoryName() !== null) {
            return;
        }

        if ($config === null) {
            /** @var GatewayConfigInterface $config */
            $config = new $this->gatewayConfigClass();
            $this->resource->setGatewayConfig($config);
        }

        if ($factoryName !== null && $factoryName !== '') {
            $config->setFactoryName($factoryName);
        }
    }

    /**
     * Read the gateway factory name from the current request's `factory`
     * route attribute (as set by `sylius_admin_payment_method_create`:
     * `/admin/payment-methods/new/{factory}`). Returns null when called
     * outside the create flow (e.g. edit page, CLI, sub-request).
     */
    private function resolveFactoryNameFromRequest(): ?string
    {
        $request = $this->requestStack->getCurrentRequest();
        if ($request === null) {
            return null;
        }

        $factory = $request->attributes->get('factory');

        return is_string($factory) && $factory !== '' ? $factory : null;
    }

    /**
     * Expose form view to the template. Ensures "form" variable exists on re-render
     * when the component is hydrated without the initial "form" prop from the hook.
     *
     * In edit mode (resource already persisted), data-model is removed so the form
     * behaves as a plain HTML form: no live re-renders on field changes, no risk of
     * the DynamicForms chain breaking. The dependent fields are already built from
     * the persisted entity, and the final save goes through a regular HTTP POST.
     */
    #[ExposeInTemplate(name: 'form', getter: 'getFormView')]
    public function getFormView(): FormView
    {
        $this->ensureGatewayConfigStub();

        return parent::getFormView();
    }
}
