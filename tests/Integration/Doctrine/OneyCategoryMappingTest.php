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

use DateTimeInterface;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\OneyCategory;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardCategory;
use Sylius\Component\Taxonomy\Factory\TaxonFactoryInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

final class OneyCategoryMappingTest extends KernelTestCase
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

    public function testOneyCategoryMetadataIsLoaded(): void
    {
        $metadata = $this->entityManager->getClassMetadata(OneyCategory::class);

        $this->assertSame('hipay_oney_category', $metadata->getTableName());
        $this->assertTrue($metadata->hasAssociation('taxon'));
        $this->assertTrue($metadata->hasField('oneyCategory'));
        $this->assertTrue($metadata->hasField('createdAt'));
        $this->assertTrue($metadata->hasField('updatedAt'));

        $joinColumn = $metadata->getAssociationMapping('taxon')['joinColumns'][0];
        $this->assertSame('CASCADE', strtoupper((string) $joinColumn['onDelete']));
    }

    public function testOneyCategoryCanBePersistedAndRetrieved(): void
    {
        $taxon = $this->createRootTaxon();
        $mapping = $this->createMapping($taxon, OneyStandardCategory::ItEquipment);

        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        $this->assertNotNull($mapping->getId());

        $this->entityManager->clear();

        $found = $this->entityManager->find(OneyCategory::class, $mapping->getId());

        $this->assertInstanceOf(OneyCategory::class, $found);
        $this->assertSame($taxon->getId(), $found->getTaxon()?->getId());
        $this->assertSame(OneyStandardCategory::ItEquipment, $found->getOneyCategory());
    }

    public function testCreatedAtIsSetAutomatically(): void
    {
        $taxon = $this->createRootTaxon();
        $mapping = $this->createMapping($taxon, OneyStandardCategory::Traveling);

        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        $this->assertInstanceOf(DateTimeInterface::class, $mapping->getCreatedAt());
    }

    public function testTaxonUniqueConstraintIsEnforced(): void
    {
        $taxon = $this->createRootTaxon();
        $mapping1 = $this->createMapping($taxon, OneyStandardCategory::HomeAndGardening);
        $mapping2 = $this->createMapping($taxon, OneyStandardCategory::ClothingAndAccessories);

        $this->entityManager->persist($mapping1);
        $this->entityManager->flush();

        $this->entityManager->persist($mapping2);

        $this->expectException(UniqueConstraintViolationException::class);
        $this->entityManager->flush();
    }

    public function testDeletingTaxonCascadesToOneyCategory(): void
    {
        $taxon = $this->createRootTaxon();
        $mapping = $this->createMapping($taxon, OneyStandardCategory::Ticketing);

        $this->entityManager->persist($mapping);
        $this->entityManager->flush();

        $id = $mapping->getId();
        $this->assertNotNull($id);

        $this->entityManager->remove($taxon);
        $this->entityManager->flush();
        $this->entityManager->clear();

        $this->assertNull($this->entityManager->find(OneyCategory::class, $id));
    }

    private function createRootTaxon(): TaxonInterface
    {
        /** @var TaxonFactoryInterface $taxonFactory */
        $taxonFactory = self::getContainer()->get('sylius.factory.taxon');
        /** @var TaxonInterface $taxon */
        $taxon = $taxonFactory->createNew();
        $suffix = bin2hex(random_bytes(4));
        $taxon->setCode('hipay_oney_test_' . $suffix);
        $taxon->setCurrentLocale('en_US');
        $taxon->setFallbackLocale('en_US');
        $taxon->setName('HiPay Oney test ' . $suffix);
        $taxon->setSlug('hipay-oney-test-' . $suffix);
        $taxon->setEnabled(true);

        $this->entityManager->persist($taxon);
        $this->entityManager->flush();

        return $taxon;
    }

    private function createMapping(TaxonInterface $taxon, OneyStandardCategory $oneyCategory): OneyCategory
    {
        $mapping = new OneyCategory();
        $mapping->setTaxon($taxon);
        $mapping->setOneyCategory($oneyCategory);

        return $mapping;
    }
}
