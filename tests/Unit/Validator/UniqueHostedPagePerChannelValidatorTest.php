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

namespace Tests\HiPay\SyliusHiPayPlugin\Unit\Validator;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use HiPay\SyliusHiPayPlugin\Constraint\UniqueHostedPagePerChannel;
use HiPay\SyliusHiPayPlugin\Validator\UniqueHostedPagePerChannelValidator;
use PHPUnit\Framework\TestCase;
use Sylius\Component\Core\Model\ChannelInterface;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Sylius\Component\Payment\Model\GatewayConfigInterface;
use Symfony\Component\Validator\Context\ExecutionContextInterface;
use Symfony\Component\Validator\Violation\ConstraintViolationBuilderInterface;

final class UniqueHostedPagePerChannelValidatorTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private QueryBuilder $queryBuilder;

    private Query $query;

    private ExecutionContextInterface $context;

    private UniqueHostedPagePerChannelValidator $validator;

    private UniqueHostedPagePerChannel $constraint;

    protected function setUp(): void
    {
        $this->query = $this->createMock(Query::class);
        $this->queryBuilder = $this->createMock(QueryBuilder::class);
        $this->queryBuilder->method('select')->willReturnSelf();
        $this->queryBuilder->method('from')->willReturnSelf();
        $this->queryBuilder->method('join')->willReturnSelf();
        $this->queryBuilder->method('where')->willReturnSelf();
        $this->queryBuilder->method('andWhere')->willReturnSelf();
        $this->queryBuilder->method('setParameter')->willReturnSelf();
        $this->queryBuilder->method('getQuery')->willReturn($this->query);

        $this->entityManager = $this->createMock(EntityManagerInterface::class);
        $this->entityManager->method('createQueryBuilder')->willReturn($this->queryBuilder);

        $this->context = $this->createMock(ExecutionContextInterface::class);
        $this->validator = new UniqueHostedPagePerChannelValidator($this->entityManager);
        $this->validator->initialize($this->context);
        $this->constraint = new UniqueHostedPagePerChannel();
    }

    public function testNoViolationWhenNotAHostedPageMethod(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('hipay_hosted_fields');

        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getGatewayConfig')->willReturn($gatewayConfig);

        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate($method, $this->constraint);
    }

    public function testNoViolationWhenNoChannels(): void
    {
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('hipay_hosted_page');

        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getGatewayConfig')->willReturn($gatewayConfig);
        $method->method('getChannels')->willReturn(new ArrayCollection());

        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate($method, $this->constraint);
    }

    public function testNoViolationWhenNoDuplicate(): void
    {
        $this->query->method('getSingleScalarResult')->willReturn('0');

        $channel = $this->createMock(ChannelInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('hipay_hosted_page');

        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getGatewayConfig')->willReturn($gatewayConfig);
        $method->method('getChannels')->willReturn(new ArrayCollection([$channel]));
        $method->method('getId')->willReturn(null);

        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate($method, $this->constraint);
    }

    public function testViolationWhenDuplicateExistsOnCreate(): void
    {
        $this->query->method('getSingleScalarResult')->willReturn('1');

        $channel = $this->createMock(ChannelInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('hipay_hosted_page');

        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getGatewayConfig')->willReturn($gatewayConfig);
        $method->method('getChannels')->willReturn(new ArrayCollection([$channel]));
        $method->method('getId')->willReturn(null);

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($this->constraint->message)
            ->willReturn($violationBuilder);

        $this->validator->validate($method, $this->constraint);
    }

    public function testViolationWhenDuplicateExistsOnUpdate(): void
    {
        $this->query->method('getSingleScalarResult')->willReturn('1');

        $channel = $this->createMock(ChannelInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('hipay_hosted_page');

        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getGatewayConfig')->willReturn($gatewayConfig);
        $method->method('getChannels')->willReturn(new ArrayCollection([$channel]));
        $method->method('getId')->willReturn(42);

        // andWhere('pm.id != :id') must be called for update
        $this->queryBuilder->expects($this->atLeastOnce())
            ->method('andWhere')
            ->willReturnSelf();

        $violationBuilder = $this->createMock(ConstraintViolationBuilderInterface::class);
        $violationBuilder->expects($this->once())->method('addViolation');
        $this->context->expects($this->once())
            ->method('buildViolation')
            ->with($this->constraint->message)
            ->willReturn($violationBuilder);

        $this->validator->validate($method, $this->constraint);
    }

    public function testNoViolationWhenSameMethodOnUpdate(): void
    {
        // Count = 0 means no OTHER method uses the same channel
        $this->query->method('getSingleScalarResult')->willReturn('0');

        $channel = $this->createMock(ChannelInterface::class);
        $gatewayConfig = $this->createMock(GatewayConfigInterface::class);
        $gatewayConfig->method('getFactoryName')->willReturn('hipay_hosted_page');

        $method = $this->createMock(PaymentMethodInterface::class);
        $method->method('getGatewayConfig')->willReturn($gatewayConfig);
        $method->method('getChannels')->willReturn(new ArrayCollection([$channel]));
        $method->method('getId')->willReturn(42);

        $this->context->expects($this->never())->method('buildViolation');

        $this->validator->validate($method, $this->constraint);
    }
}
