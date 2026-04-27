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

namespace Tests\HiPay\SyliusHiPayPlugin\Integration\Form\Type;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethod;
use HiPay\SyliusHiPayPlugin\Form\Type\Resource\OneyShippingMethodType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardShippingMethod;
use Sylius\Bundle\CoreBundle\Fixture\Factory\ShippingMethodExampleFactory;
use Sylius\Component\Addressing\Factory\ZoneFactoryInterface;
use Sylius\Component\Addressing\Model\CountryInterface;
use Sylius\Component\Addressing\Model\Scope as AddressingScope;
use Sylius\Component\Addressing\Model\ZoneInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Resource\Doctrine\Persistence\RepositoryInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Requires a configured test database (see DATABASE_TEST_URL): building the form runs repository queries for excluded shipping methods.
 */
final class OneyShippingMethodTypeTest extends KernelTestCase
{
    private FormFactoryInterface $formFactory;

    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->formFactory = self::getContainer()->get('form.factory');
        /** @var EntityManagerInterface $entityManager */
        $entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager = $entityManager;
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        if ($this->entityManager->getConnection()->isTransactionActive()) {
            $this->entityManager->rollback();
        }

        parent::tearDown();
    }

    public function testFormHasAllExpectedFields(): void
    {
        $form = $this->formFactory->create(OneyShippingMethodType::class, new OneyShippingMethod());

        $this->assertTrue($form->has('shippingMethod'));
        $this->assertTrue($form->has('oneyShippingMethod'));
        $this->assertTrue($form->has('oneyPreparationTime'));
        $this->assertTrue($form->has('oneyDeliveryTime'));
    }

    public function testSubmitValidData(): void
    {
        $shippingMethod = $this->createShippingMethod();

        $form = $this->formFactory->create(OneyShippingMethodType::class, new OneyShippingMethod());

        $form->submit([
            'shippingMethod' => (string) $shippingMethod->getId(),
            'oneyShippingMethod' => OneyStandardShippingMethod::CarrierExpress->value,
            'oneyPreparationTime' => 2,
            'oneyDeliveryTime' => 4,
        ]);

        $this->assertTrue($form->isSynchronized());

        /** @var OneyShippingMethod $data */
        $data = $form->getData();
        $this->assertSame($shippingMethod->getId(), $data->getShippingMethod()?->getId());
        $this->assertSame(OneyStandardShippingMethod::CarrierExpress, $data->getOneyShippingMethod());
        $this->assertSame(2, $data->getOneyPreparationTime());
        $this->assertSame(4, $data->getOneyDeliveryTime());
    }

    public function testOneyStandardShippingMethodChoicesCoverAllEnumCases(): void
    {
        $form = $this->formFactory->create(OneyShippingMethodType::class, new OneyShippingMethod());

        $choices = $form->get('oneyShippingMethod')->getConfig()->getOption('choices');

        $this->assertIsArray($choices);
        $this->assertCount(count(OneyStandardShippingMethod::cases()), $choices);
        $this->assertContains(OneyStandardShippingMethod::CarrierStandard, $choices, '', false, false, false);
    }

    private function createShippingMethod(): ShippingMethodInterface
    {
        /** @var ShippingMethodExampleFactory $factory */
        $factory = self::getContainer()->get('sylius.fixture.example_factory.shipping_method');

        $code = 'hipay_form_sm_' . bin2hex(random_bytes(4));

        /** @var ShippingMethodInterface $shippingMethod */
        $shippingMethod = $factory->create([
            'name' => 'HiPay form shipping ' . $code,
            'code' => $code,
            'enabled' => true,
            'zone' => $this->ensureShippingZoneExists()->getCode(),
        ]);

        $this->entityManager->persist($shippingMethod);
        $this->entityManager->flush();

        return $shippingMethod;
    }

    /**
     * ShippingMethodExampleFactory defaults to a random zone; empty zone tables break integration tests.
     */
    private function ensureShippingZoneExists(): ZoneInterface
    {
        /** @var RepositoryInterface<ZoneInterface> $zoneRepository */
        $zoneRepository = self::getContainer()->get('sylius.repository.zone');
        $zones = $zoneRepository->findAll();
        if ($zones !== []) {
            /** @var ZoneInterface $first */
            $first = $zones[0];

            return $first;
        }

        $this->ensureUnitedStatesCountryExists();

        /** @var ZoneFactoryInterface $zoneFactory */
        $zoneFactory = self::getContainer()->get('sylius.factory.zone');
        $zone = $zoneFactory->createWithMembers(['US']);
        $suffix = bin2hex(random_bytes(4));
        $zone->setCode('hipay_form_zone_' . $suffix);
        $zone->setName('HiPay form test zone ' . $suffix);
        $zone->setType(ZoneInterface::TYPE_COUNTRY);
        $zone->setScope(AddressingScope::ALL);

        $this->entityManager->persist($zone);
        $this->entityManager->flush();

        return $zone;
    }

    private function ensureUnitedStatesCountryExists(): void
    {
        /** @var RepositoryInterface<CountryInterface> $countryRepository */
        $countryRepository = self::getContainer()->get('sylius.repository.country');
        if (null !== $countryRepository->findOneBy(['code' => 'US'])) {
            return;
        }

        /** @var CountryInterface $country */
        $country = self::getContainer()->get('sylius.factory.country')->createNew();
        $country->setCode('US');
        $country->enable();

        $this->entityManager->persist($country);
        $this->entityManager->flush();
    }
}
