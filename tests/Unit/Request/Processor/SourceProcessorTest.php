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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Request\Processor;

use HiPay\Fullservice\Gateway\Request\Maintenance\MaintenanceRequest;
use HiPay\Fullservice\Gateway\Request\Order\OrderRequest;
use HiPay\SyliusHiPayPlugin\Request\Processor\SourceProcessor;
use HiPay\SyliusHiPayPlugin\Request\RequestContextInterface;
use PHPUnit\Framework\TestCase;

final class SourceProcessorTest extends TestCase
{
    public function testItSetsSourceOnAnOrderRequest(): void
    {
        $request = new OrderRequest();
        (new SourceProcessor())->process($request, $this->createMock(RequestContextInterface::class));

        $this->assertIsArray($request->source);
        $this->assertSame('CMS', $request->source['source']);
        $this->assertSame('Sylius', $request->source['brand']);
        $this->assertArrayHasKey('brand_version', $request->source);
        $this->assertArrayHasKey('integration_version', $request->source);
    }

    public function testItSetsSourceOnACaptureMaintenanceRequest(): void
    {
        $request = new MaintenanceRequest();
        (new SourceProcessor())->process($request, $this->createMock(RequestContextInterface::class));

        $this->assertIsArray($request->source);
        $this->assertSame('CMS', $request->source['source']);
        $this->assertSame('Sylius', $request->source['brand']);
    }
}
