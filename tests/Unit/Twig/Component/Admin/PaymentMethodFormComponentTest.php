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
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Payment\Model\GatewayConfig;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Sylius\Resource\Model\ResourceInterface;
use Symfony\Component\Form\FormFactoryInterface;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;

final class PaymentMethodFormComponentTest extends TestCase
{
    private PaymentMethodFormComponent $component;

    protected function setUp(): void
    {
        $repository = $this->createMock(RepositoryInterface::class);
        $formFactory = $this->createMock(FormFactoryInterface::class);
        $form = $this->createMock(FormInterface::class);
        $form->method('createView')->willReturn(new FormView());
        $formFactory->method('create')->willReturn($form);

        $this->component = new PaymentMethodFormComponent(
            $repository,
            $formFactory,
            PaymentMethod::class,
            'Sylius\Bundle\PaymentBundle\Form\Type\PaymentMethodType',
            GatewayConfig::class,
        );
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
}
