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

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Event\CheckoutPaymentDetailsDecodedEvent;
use HiPay\SyliusHiPayPlugin\Event\CheckoutPaymentDetailsPersistedEvent;
use HiPay\SyliusHiPayPlugin\Provider\CheckoutJsSdkConfigFactory;
use Sylius\Component\Core\Model\Payment;
use Sylius\Component\Core\Model\PaymentMethod;
use Symfony\Component\Serializer\Encoder\DecoderInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Symfony\UX\LiveComponent\Attribute\AsLiveComponent;
use Symfony\UX\LiveComponent\Attribute\LiveAction;
use Symfony\UX\LiveComponent\Attribute\LiveArg;
use Symfony\UX\LiveComponent\Attribute\LiveProp;
use Symfony\UX\LiveComponent\ComponentToolsTrait;
use Symfony\UX\LiveComponent\DefaultActionTrait;

#[AsLiveComponent(name: 'hipay_hosted_fields')]
final class HostedFieldsComponent
{
    use DefaultActionTrait;
    use ComponentToolsTrait;

    #[LiveProp(writable: false)]
    public PaymentMethod|null $paymentMethod = null;

    #[LiveProp(writable: false)]
    public Payment|null $payment = null;

    public function __construct(
        private readonly CheckoutJsSdkConfigFactory $checkoutJsSdkConfigFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly DecoderInterface $serializer,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    public function getJsSdkConfig(): array
    {
        return $this->checkoutJsSdkConfigFactory->create($this->paymentMethod, $this->payment);
    }

    /**
     * Exposed for Twig: same rules as {@see getJsSdkConfig()} `eligibility` (Oney postal check).
     *
     * @return array{blocked: bool, messages?: list<string>}
     */
    public function getEligibility(): array
    {
        return $this->checkoutJsSdkConfigFactory->buildEligibility($this->paymentMethod, $this->payment);
    }

    #[LiveAction]
    public function processPayment(#[LiveArg] string $response): void
    {
        if (null === $this->payment) {
            return;
        }

        /** @var array<string, mixed> $details */
        $details = $this->serializer->decode($response, 'json');
        $decodedEvent = new CheckoutPaymentDetailsDecodedEvent($details, $this->payment);
        $this->eventDispatcher->dispatch($decodedEvent);
        $this->payment->setDetails($decodedEvent->getDetails());
        $this->entityManager->persist($this->payment);
        $this->entityManager->flush();

        $this->eventDispatcher->dispatch(new CheckoutPaymentDetailsPersistedEvent($this->payment));

        $this->dispatchBrowserEvent('hipay:payment:processed', []);
    }
}
