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

namespace HiPay\SyliusHiPayPlugin\Twig\Extension;

use HiPay\SyliusHiPayPlugin\PaymentProduct\Configuration\PaymentFallbackDefaultsInterface;
use Sylius\Bundle\ResourceBundle\Doctrine\ORM\EntityRepository;
use Sylius\Component\Core\Model\OrderInterface;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class OrderExtension extends AbstractExtension
{
    public function __construct(
        private readonly EntityRepository $orderRepository,
        private readonly RequestStack $requestStack,
        private readonly DecoderInterface $serializer,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('get_reference_to_pay_from_request', [$this, 'getReferenceToPayFromRequest']),
            new TwigFunction('get_order_from_request', [$this, 'getOrderFromRequest']),
        ];
    }

    public function getOrderFromRequest(): ?OrderInterface
    {
        $request = $this->requestStack->getCurrentRequest();
        $token = $request?->query->get('tokenValue');

        // @phpstan-ignore-next-line
        return $token ? $this->orderRepository->findOneByTokenValue($token) : null;
    }

    public function getReferenceToPayFromRequest(): array
    {
        $request = $this->requestStack->getCurrentRequest();
        $referenceToPay = $request?->query->get('referenceToPay');
        if (null === $referenceToPay) {
            return [];
        }
        $currency = $request?->query->get('currency', PaymentFallbackDefaultsInterface::CURRENCY_CODE);

        /** @var array $data */
        $data = $this->serializer->decode($referenceToPay, 'json');
        $data['currency'] = $currency;

        return $data;
    }
}
