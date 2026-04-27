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
use HiPay\SyliusHiPayPlugin\Entity\OneyCategory;
use HiPay\SyliusHiPayPlugin\Form\Type\Resource\OneyCategoryType;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardCategory;
use Sylius\Component\Taxonomy\Factory\TaxonFactoryInterface;
use Sylius\Component\Taxonomy\Generator\TaxonSlugGeneratorInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Form\FormFactoryInterface;

/**
 * Requires a configured test database (see DATABASE_TEST_URL): building the form runs repository queries for excluded taxons.
 */
final class OneyCategoryTypeTest extends KernelTestCase
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
        $form = $this->formFactory->create(OneyCategoryType::class, new OneyCategory());

        $this->assertTrue($form->has('taxon'));
        $this->assertTrue($form->has('oneyCategory'));
    }

    public function testSubmitValidData(): void
    {
        $taxon = $this->createLevelOneTaxon();

        $form = $this->formFactory->create(OneyCategoryType::class, new OneyCategory());

        $form->submit([
            'taxon' => (string) $taxon->getId(),
            'oneyCategory' => OneyStandardCategory::ItEquipment->value,
        ]);

        $this->assertTrue($form->isSynchronized());

        /** @var OneyCategory $data */
        $data = $form->getData();
        $this->assertSame($taxon->getId(), $data->getTaxon()?->getId());
        $this->assertSame(OneyStandardCategory::ItEquipment, $data->getOneyCategory());
    }

    public function testOneyStandardCategoryChoicesCoverAllEnumCases(): void
    {
        $form = $this->formFactory->create(OneyCategoryType::class, new OneyCategory());

        $choices = $form->get('oneyCategory')->getConfig()->getOption('choices');

        $this->assertIsArray($choices);
        $this->assertCount(count(OneyStandardCategory::cases()), $choices);
        $this->assertContains(OneyStandardCategory::ClothingAndAccessories, $choices);
    }

    private function createLevelOneTaxon(): TaxonInterface
    {
        $entityManager = $this->entityManager;
        /** @var TaxonFactoryInterface $taxonFactory */
        $taxonFactory = self::getContainer()->get('sylius.factory.taxon');
        /** @var TaxonSlugGeneratorInterface $slugGenerator */
        $slugGenerator = self::getContainer()->get('sylius.generator.taxon_slug');

        $suffix = bin2hex(random_bytes(4));

        /** @var TaxonInterface $root */
        $root = $taxonFactory->createNew();
        $root->setCode('hipay_form_root_' . $suffix);
        $root->setCurrentLocale('en_US');
        $root->setFallbackLocale('en_US');
        $root->setName('HiPay form root ' . $suffix);
        $root->setSlug($slugGenerator->generate($root));
        $root->setEnabled(true);

        $entityManager->persist($root);
        $entityManager->flush();

        /** @var TaxonInterface $child */
        $child = $taxonFactory->createNew();
        $child->setCode('hipay_form_child_' . $suffix);
        $child->setCurrentLocale('en_US');
        $child->setFallbackLocale('en_US');
        $child->setName('HiPay form child ' . $suffix);
        $child->setParent($root);
        $child->setSlug($slugGenerator->generate($child));
        $child->setEnabled(true);

        $entityManager->persist($child);
        $entityManager->flush();

        $entityManager->refresh($child);
        $this->assertSame(1, $child->getLevel());

        return $child;
    }
}
