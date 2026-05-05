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

    #[LiveProp(writable: false, hydrateWith: 'hydratePaymentMethod', dehydrateWith: 'dehydratePaymentMethod')]
    public PaymentMethod|null $paymentMethod = null;

    #[LiveProp(writable: false, hydrateWith: 'hydratePayment', dehydrateWith: 'dehydratePayment')]
    public Payment|null $payment = null;

    public function __construct(
        private readonly CheckoutJsSdkConfigFactory $checkoutJsSdkConfigFactory,
        private readonly EntityManagerInterface $entityManager,
        private readonly DecoderInterface $serializer,
        private readonly EventDispatcherInterface $eventDispatcher,
    ) {
    }

    /**
     * Dehydrate a Doctrine-managed {@see PaymentMethod} to its ID.
     *
     * The default LiveComponent dehydration walks every property of the entity,
     * including the Sylius `Collection $channels`. At runtime that collection
     * is a `Doctrine\ORM\PersistentCollection` whose generic `Collection`
     * type-hint is too loose for the LiveComponent property-info layer to
     * round-trip — it throws *"missing its property-type. Add the
     * Doctrine\ORM\PersistentCollection type so the object can be hydrated
     * later."* on first render.
     *
     * Storing only the ID side-steps the whole nested traversal: the
     * client receives a small int, and {@see hydratePaymentMethod()} reloads
     * the entity from the EntityManager on the next request.
     */
    public function dehydratePaymentMethod(?PaymentMethod $paymentMethod): ?int
    {
        return $paymentMethod?->getId();
    }

    public function hydratePaymentMethod(mixed $value): ?PaymentMethod
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }

        /** @var ?PaymentMethod $entity */
        $entity = $this->entityManager->find(PaymentMethod::class, (int) $value);

        return $entity;
    }

    /**
     * Dehydrate a Doctrine-managed {@see Payment} to its ID, for the same
     * reasons as {@see dehydratePaymentMethod()}: Payment carries collections
     * (e.g. `$transitions` on its workflow trait) that LiveComponent cannot
     * default-serialize.
     */
    public function dehydratePayment(?Payment $payment): ?int
    {
        return $payment?->getId();
    }

    public function hydratePayment(mixed $value): ?Payment
    {
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            return null;
        }

        /** @var ?Payment $entity */
        $entity = $this->entityManager->find(Payment::class, (int) $value);

        return $entity;
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
