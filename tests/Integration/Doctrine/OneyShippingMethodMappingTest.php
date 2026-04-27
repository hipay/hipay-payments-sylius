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

namespace Tests\HiPay\SyliusHiPayPlugin\Integration\Doctrine;

use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethod;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OneyShippingMethodMappingTest extends KernelTestCase
{
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->entityManager = self::getContainer()->get('doctrine.orm.entity_manager');
        $this->entityManager->beginTransaction();
    }

    protected function tearDown(): void
    {
        $this->entityManager->rollback();
        parent::tearDown();
    }

    public function testOneyShippingMethodMetadataIsLoaded(): void
    {
        $metadata = $this->entityManager->getClassMetadata(OneyShippingMethod::class);

        $this->assertSame('hipay_oney_shipping_method', $metadata->getTableName());
        $this->assertTrue($metadata->hasAssociation('shippingMethod'));
        $this->assertTrue($metadata->hasField('oneyShippingMethod'));
        $this->assertTrue($metadata->hasField('oneyPreparationTime'));
        $this->assertTrue($metadata->hasField('oneyDeliveryTime'));
        $this->assertTrue($metadata->hasField('createdAt'));
        $this->assertTrue($metadata->hasField('updatedAt'));

        $joinColumn = $metadata->getAssociationMapping('shippingMethod')['joinColumns'][0];
        $this->assertSame('CASCADE', strtoupper((string) $joinColumn['onDelete']));
    }
}
