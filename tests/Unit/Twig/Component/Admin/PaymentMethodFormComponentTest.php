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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Twig\Component\Admin;

use HiPay\SyliusHiPayPlugin\Twig\Component\Admin\PaymentMethodFormComponent;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

final class PaymentMethodFormComponentTest extends TestCase
{
    private PaymentMethodFormComponent $component;

    private RequestStack $requestStack;

    protected function setUp(): void
    {
        $repository = $this->createMock(RepositoryInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory->method('create')->willReturn($form);

        $this->requestStack = new RequestStack();

        $this->component = new PaymentMethodFormComponent(
            $repository,
            $formFactory,
            PaymentMethod::class,
            'Sylius\Bundle\PaymentBundle\Form\Type\PaymentMethodType',
            GatewayConfig::class,
            $this->requestStack,
        );
    }

    /**
     * Invoke a private method on the component via reflection so we can assert
     * the side-effects of internal helpers (`ensureGatewayConfigStub`,
     * `resolveFactoryNameFromRequest`) without enlarging the public API.
     *
     * Note: `setAccessible(true)` is a no-op for private methods in PHP 8.1+,
     * which is why it is not called here.
     */
    private function invokePrivate(string $method, mixed ...$args): mixed
    {
        $reflection = new ReflectionClass($this->component);

        return $reflection->getMethod($method)->invoke($this->component, ...$args);
    }

    public function testDehydrateGatewayFactoryNameReturnsFactoryName(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('hipay_hosted_fields');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->component->resource = $paymentMethod;

        $this->assertSame('hipay_hosted_fields', $this->component->dehydrateGatewayFactoryName(null));
    }

    public function testDehydrateGatewayFactoryNameReturnsNullWhenNotPaymentMethod(): void
    {
        $this->component->resource = $this->createMock(ResourceInterface::class);

        $this->assertNull($this->component->dehydrateGatewayFactoryName(null));
    }

    public function testDehydrateGatewayFactoryNameReturnsNullWhenNoGatewayConfig(): void
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig(null);
        $this->component->resource = $paymentMethod;

        $this->assertNull($this->component->dehydrateGatewayFactoryName(null));
    }

    public function testRestoreGatewayConfigDoesNothingWhenResourceIsNull(): void
    {
        $this->component->resource = null;
        $this->component->gatewayFactoryName = 'hipay_hosted_fields';

        $this->component->restoreGatewayConfig();

        $this->assertNull($this->component->resource);
    }

    public function testRestoreGatewayConfigDoesNothingWhenFactoryNameMatches(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('hipay_hosted_fields');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->component->resource = $paymentMethod;
        $this->component->gatewayFactoryName = 'hipay_hosted_fields';

        $this->component->restoreGatewayConfig();

        $this->assertSame($gatewayConfig, $paymentMethod->getGatewayConfig());
    }

    public function testRestoreGatewayConfigCreatesNewConfigWhenMissing(): void
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig(null);

        $this->component->resource = $paymentMethod;
        $this->component->gatewayFactoryName = 'hipay_hosted_fields';

        $this->component->restoreGatewayConfig();

        $config = $paymentMethod->getGatewayConfig();
        $this->assertInstanceOf(GatewayConfigInterface::class, $config);
        $this->assertSame('hipay_hosted_fields', $config->getFactoryName());
    }

    public function testRestoreGatewayConfigCreatesNewConfigWhenFactoryNameDiffers(): void
    {
        $gatewayConfig = new GatewayConfig();
        $gatewayConfig->setFactoryName('other_factory');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($gatewayConfig);

        $this->component->resource = $paymentMethod;
        $this->component->gatewayFactoryName = 'hipay_hosted_fields';

        $this->component->restoreGatewayConfig();

        $config = $paymentMethod->getGatewayConfig();
        $this->assertInstanceOf(GatewayConfigInterface::class, $config);
        $this->assertSame('hipay_hosted_fields', $config->getFactoryName());
    }

    public function testRestoreGatewayConfigDoesNothingWhenGatewayFactoryNameEmpty(): void
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig(null);

        $this->component->resource = $paymentMethod;
        $this->component->gatewayFactoryName = '';

        $this->component->restoreGatewayConfig();

        $this->assertNull($paymentMethod->getGatewayConfig());
    }

    // -----------------------------------------------------------------------
    // resolveFactoryNameFromRequest
    // -----------------------------------------------------------------------

    public function testResolveFactoryNameFromRequestReturnsNullWhenNoCurrentRequest(): void
    {
        // RequestStack is empty in setUp().
        $this->assertNull($this->invokePrivate('resolveFactoryNameFromRequest'));
    }

    public function testResolveFactoryNameFromRequestReturnsFactoryAttribute(): void
    {
        $request = new Request();
        $request->attributes->set('factory', 'hipay_hosted_fields');
        $this->requestStack->push($request);

        $this->assertSame('hipay_hosted_fields', $this->invokePrivate('resolveFactoryNameFromRequest'));
    }

    public function testResolveFactoryNameFromRequestReturnsNullWhenAttributeIsEmptyString(): void
    {
        $request = new Request();
        $request->attributes->set('factory', '');
        $this->requestStack->push($request);

        $this->assertNull($this->invokePrivate('resolveFactoryNameFromRequest'));
    }

    public function testResolveFactoryNameFromRequestReturnsNullWhenAttributeIsNotString(): void
    {
        $request = new Request();
        $request->attributes->set('factory', 42);
        $this->requestStack->push($request);

        $this->assertNull($this->invokePrivate('resolveFactoryNameFromRequest'));
    }

    // -----------------------------------------------------------------------
    // ensureGatewayConfigStub
    // -----------------------------------------------------------------------

    public function testEnsureGatewayConfigStubDoesNothingWhenResourceIsNotPaymentMethod(): void
    {
        $this->component->resource = $this->createMock(ResourceInterface::class);

        $this->invokePrivate('ensureGatewayConfigStub');

        // No exception, no setGatewayConfig call — nothing to assert beyond reaching here.
        $this->addToAssertionCount(1);
    }

    public function testEnsureGatewayConfigStubLeavesAlreadyConfiguredResourceUntouched(): void
    {
        $existing = new GatewayConfig();
        $existing->setFactoryName('already_set');

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($existing);

        $this->component->resource = $paymentMethod;

        // Even with a different factory in the request, the already-set factoryName must win.
        $request = new Request();
        $request->attributes->set('factory', 'something_else');
        $this->requestStack->push($request);

        $this->invokePrivate('ensureGatewayConfigStub');

        $this->assertSame($existing, $paymentMethod->getGatewayConfig());
        $this->assertSame('already_set', $existing->getFactoryName());
    }

    public function testEnsureGatewayConfigStubAttachesConfigWithFactoryNameFromRequest(): void
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig(null);

        $this->component->resource = $paymentMethod;

        $request = new Request();
        $request->attributes->set('factory', 'hipay_hosted_fields');
        $this->requestStack->push($request);

        $this->invokePrivate('ensureGatewayConfigStub');

        $config = $paymentMethod->getGatewayConfig();
        $this->assertInstanceOf(GatewayConfigInterface::class, $config);
        $this->assertSame('hipay_hosted_fields', $config->getFactoryName());
    }

    public function testEnsureGatewayConfigStubAttachesConfigWithFactoryNameFromLiveProp(): void
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig(null);

        $this->component->resource = $paymentMethod;
        $this->component->gatewayFactoryName = 'hipay_hosted_fields';
        // No request pushed: the LiveProp is the only source.

        $this->invokePrivate('ensureGatewayConfigStub');

        $config = $paymentMethod->getGatewayConfig();
        $this->assertInstanceOf(GatewayConfigInterface::class, $config);
        $this->assertSame('hipay_hosted_fields', $config->getFactoryName());
    }

    public function testEnsureGatewayConfigStubFillsExistingConfigMissingFactoryName(): void
    {
        $config = new GatewayConfig();
        // Factory name intentionally not set.

        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig($config);

        $this->component->resource = $paymentMethod;

        $request = new Request();
        $request->attributes->set('factory', 'hipay_hosted_fields');
        $this->requestStack->push($request);

        $this->invokePrivate('ensureGatewayConfigStub');

        $this->assertSame($config, $paymentMethod->getGatewayConfig());
        $this->assertSame('hipay_hosted_fields', $config->getFactoryName());
    }

    public function testEnsureGatewayConfigStubAttachesEmptyConfigWhenNoFactoryNameAvailable(): void
    {
        $paymentMethod = new PaymentMethod();
        $paymentMethod->setGatewayConfig(null);

        $this->component->resource = $paymentMethod;
        // No LiveProp value, no request: the stub still gets created (so PayPal-like
        // PRE_SET_DATA listeners that call $config->getFactoryName() do not crash on null).

        $this->invokePrivate('ensureGatewayConfigStub');

        $config = $paymentMethod->getGatewayConfig();
        $this->assertInstanceOf(GatewayConfigInterface::class, $config);
        $this->assertNull($config->getFactoryName());
    }
}
