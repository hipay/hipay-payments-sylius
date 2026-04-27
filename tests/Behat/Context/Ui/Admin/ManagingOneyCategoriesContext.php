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

namespace Tests\HiPay\SyliusHiPayPlugin\Behat\Context\Ui\Admin;

use Behat\Behat\Context\Context;
use Doctrine\ORM\EntityManagerInterface;
use HiPay\SyliusHiPayPlugin\Entity\OneyCategory;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardCategory;
use InvalidArgumentException;
use Sylius\Behat\Page\Admin\Crud\CreatePageInterface;
use Sylius\Behat\Page\Admin\Crud\IndexPageInterface;
use Sylius\Behat\Page\Admin\Crud\UpdatePageInterface;
use Sylius\Component\Taxonomy\Model\TaxonInterface;
use Sylius\Component\Taxonomy\Repository\TaxonRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\HiPay\SyliusHiPayPlugin\Behat\Element\Admin\OneyCategory\FormElementInterface;
use Webmozart\Assert\Assert;

final readonly class ManagingOneyCategoriesContext implements Context
{
    private const DEFAULT_LOCALE = 'en_US';

    /**
     * @param TaxonRepositoryInterface<TaxonInterface> $taxonRepository
     */
    public function __construct(
        private IndexPageInterface $indexPage,
        private CreatePageInterface $createPage,
        private UpdatePageInterface $updatePage,
        private FormElementInterface $formElement,
        private EntityManagerInterface $entityManager,
        private TaxonRepositoryInterface $taxonRepository,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @Given there is an Oney category for taxon :taxonName with Oney standard category :categoryCase
     */
    public function thereIsAnOneyCategoryForTaxonWithCategory(string $taxonName, string $categoryCase): void
    {
        $taxons = $this->taxonRepository->findByName($taxonName, self::DEFAULT_LOCALE);
        Assert::count($taxons, 1, sprintf('Expected exactly one taxon named "%s".', $taxonName));

        $oneyCategory = new OneyCategory();
        $oneyCategory->setTaxon($taxons[0]);
        $oneyCategory->setOneyCategory($this->parseOneyStandardCategory($categoryCase));

        $this->entityManager->persist($oneyCategory);
        $this->entityManager->flush();
    }

    /**
     * @When I browse Oney categories
     */
    public function iBrowseOneyCategories(): void
    {
        $this->indexPage->open();
    }

    /**
     * @When I want to create a new Oney category
     */
    public function iWantToCreateANewOneyCategory(): void
    {
        $this->createPage->open();
    }

    /**
     * @When I want to modify the Oney category for taxon with code :code
     */
    public function iWantToModifyTheOneyCategoryForTaxonWithCode(string $code): void
    {
        $taxon = $this->taxonRepository->findOneBy(['code' => $code]);
        Assert::notNull($taxon, sprintf('Taxon with code "%s" not found.', $code));

        /** @var OneyCategory|null $oneyCategory */
        $oneyCategory = $this->entityManager->getRepository(OneyCategory::class)->findOneBy(['taxon' => $taxon]);
        Assert::notNull($oneyCategory);

        $this->updatePage->open(['id' => $oneyCategory->getId()]);
    }

    /**
     * @When I select taxon :taxonName for the Oney category
     */
    public function iSelectTaxonForTheOneyCategory(string $taxonName): void
    {
        $this->formElement->selectTaxonByName($taxonName);
    }

    /**
     * @When I select Oney standard category :categoryCase
     */
    public function iSelectOneyStandardCategory(string $categoryCase): void
    {
        $enum = $this->parseOneyStandardCategory($categoryCase);
        $label = $this->translator->trans(
            'sylius_hipay_plugin.oney_standard_category.' . $enum->getTranslationKey(),
            [],
            'messages',
        );
        $this->formElement->selectOneyStandardCategoryByLabel($label);
    }

    /**
     * @When I add it
     * @When I try to add it
     */
    public function iAddIt(): void
    {
        $this->createPage->create();
    }

    /**
     * @When I delete the Oney category for taxon with code :code
     */
    public function iDeleteTheOneyCategoryForTaxonWithCode(string $code): void
    {
        $this->indexPage->deleteResourceOnPage(['taxon.code' => $code]);
    }

    /**
     * @Then I should see :count Oney category(ies) in the list
     */
    public function iShouldSeeOneyCategoriesInTheList(int $count): void
    {
        Assert::same($this->indexPage->countItems(), $count);
    }

    /**
     * @Then I should see an Oney category for taxon with code :code in the list
     * @Then the Oney category for taxon with code :code should appear in the list
     */
    public function iShouldSeeAnOneyCategoryForTaxonWithCodeInTheList(string $code): void
    {
        $this->indexPage->open();
        Assert::true($this->indexPage->isSingleResourceOnPage(['taxon.code' => $code]));
    }

    /**
     * @Then the Oney category for taxon with code :code should have Oney standard category :categoryCase
     */
    public function theOneyCategoryForTaxonWithCodeShouldHaveCategory(string $code, string $categoryCase): void
    {
        $this->entityManager->clear();
        $taxon = $this->taxonRepository->findOneBy(['code' => $code]);
        Assert::notNull($taxon);

        /** @var OneyCategory|null $oneyCategory */
        $oneyCategory = $this->entityManager->getRepository(OneyCategory::class)->findOneBy(['taxon' => $taxon]);
        Assert::notNull($oneyCategory);
        Assert::same($oneyCategory->getOneyCategory(), $this->parseOneyStandardCategory($categoryCase));
    }

    /**
     * @Then there should be no Oney category for taxon with code :code
     */
    public function thereShouldBeNoOneyCategoryForTaxonWithCode(string $code): void
    {
        $this->entityManager->clear();
        $taxon = $this->taxonRepository->findOneBy(['code' => $code]);
        Assert::notNull($taxon);

        /** @var OneyCategory|null $oneyCategory */
        $oneyCategory = $this->entityManager->getRepository(OneyCategory::class)->findOneBy(['taxon' => $taxon]);
        Assert::null($oneyCategory, sprintf('Oney category for taxon "%s" still exists.', $code));
    }

    /**
     * @Then I should be notified that the form contains errors
     */
    public function iShouldBeNotifiedThatTheFormContainsErrors(): void
    {
        Assert::true($this->formElement->hasFormErrorAlert());
    }

    /**
     * @Then the Sylius category field should only list taxon :taxonName
     */
    public function theSyliusCategoryFieldShouldOnlyListTaxon(string $taxonName): void
    {
        Assert::same(
            $this->formElement->getSelectableTaxonNames(),
            [$taxonName],
            sprintf('Expected the Sylius category select to offer only taxon "%s".', $taxonName),
        );
    }

    private function parseOneyStandardCategory(string $caseName): OneyStandardCategory
    {
        foreach (OneyStandardCategory::cases() as $case) {
            if ($case->name === $caseName) {
                return $case;
            }
        }

        throw new InvalidArgumentException(sprintf('Unknown Oney standard category case "%s".', $caseName));
    }
}
