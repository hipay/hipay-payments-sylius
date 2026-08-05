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

namespace HiPay\SyliusHiPayPlugin\Validator;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Constraint\UniqueHostedPagePerChannel;
use Sylius\Component\Core\Model\PaymentMethod;
use Sylius\Component\Core\Model\PaymentMethodInterface;
use Symfony\Component\Validator\Constraint;
use Symfony\Component\Validator\ConstraintValidator;
use Symfony\Component\Validator\Exception\UnexpectedTypeException;

class UniqueHostedPagePerChannelValidator extends ConstraintValidator
{
    public function __construct(private readonly EntityManagerInterface $entityManager)
    {
    }

    public function validate(mixed $value, Constraint $constraint): void
    {
        if (!$constraint instanceof UniqueHostedPagePerChannel) {
            throw new UnexpectedTypeException($constraint, UniqueHostedPagePerChannel::class);
        }

        if (!$value instanceof PaymentMethodInterface) {
            return;
        }

        $gatewayConfig = $value->getGatewayConfig();
        if (null === $gatewayConfig || 'hipay_hosted_page' !== $gatewayConfig->getFactoryName()) {
            return;
        }

        $channels = $value->getChannels();
        if ($channels->isEmpty()) {
            return;
        }

        $queryBuilder = $this->entityManager->createQueryBuilder()
            ->select('COUNT(pm.id)')
            ->from(PaymentMethod::class, 'pm')
            ->join('pm.channels', 'c')
            ->join('pm.gatewayConfig', 'gc')
            ->where('gc.factoryName = :factory')
            ->andWhere('c IN (:channels)')
            ->setParameter('factory', 'hipay_hosted_page')
            ->setParameter('channels', $channels->toArray());

        if (null !== $value->getId()) {
            $queryBuilder->andWhere('pm.id != :id')->setParameter('id', $value->getId());
        }

        $count = (int) $queryBuilder->getQuery()->getSingleScalarResult();

        if ($count > 0) {
            $this->context->buildViolation($constraint->message)->addViolation();
        }
    }
}
