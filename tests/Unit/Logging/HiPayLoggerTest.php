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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Logging;

use HiPay\SyliusHiPayPlugin\Entity\AccountInterface;
use HiPay\SyliusHiPayPlugin\Logging\HiPayLogger;
use Monolog\Logger;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class HiPayLoggerTest extends TestCase
{
    private Logger&MockObject $inner;

    private Logger&MockObject $baseLogger;

    private AccountInterface&MockObject $account;

    private HiPayLogger $logger;

    protected function setUp(): void
    {
        $this->inner = $this->createMock(Logger::class);
        $this->baseLogger = $this->createMock(Logger::class);
        $this->account = $this->createMock(AccountInterface::class);
        $this->logger = new HiPayLogger($this->inner, $this->baseLogger);
    }

    public function testDoesNotDelegateWhenNoAccountIsSet(): void
    {
        $this->baseLogger->expects(self::once())->method('log');

        $this->logger->info('msg');
    }

    public function testDoesNotDelegateWhenDebugModeIsOff(): void
    {
        $this->account->method('isDebugMode')->willReturn(false);
        $this->baseLogger->expects(self::once())->method('log');

        $this->logger->setAccount($this->account);
        $this->logger->info('msg');
    }

    public function testDelegatesWhenDebugModeIsOn(): void
    {
        $this->account->method('isDebugMode')->willReturn(true);
        $this->inner->expects(self::once())->method('log')->with('info', 'msg', ['k' => 1]);

        $this->logger->setAccount($this->account);
        $this->logger->info('msg', ['k' => 1]);
    }

    public function testClearingAccountStopsDelegation(): void
    {
        $this->account->method('isDebugMode')->willReturn(true);
        $this->inner->expects(self::once())->method('log');
        $this->baseLogger->expects(self::once())->method('log');

        $this->logger->setAccount($this->account);
        $this->logger->info('first');
        $this->logger->setAccount(null);
        $this->logger->info('second');
    }
}
