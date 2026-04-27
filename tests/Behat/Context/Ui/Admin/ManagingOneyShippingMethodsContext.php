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
use HiPay\SyliusHiPayPlugin\Entity\OneyShippingMethod;
use HiPay\SyliusHiPayPlugin\PaymentProduct\Oney\OneyStandardShippingMethod;
use InvalidArgumentException;
use Sylius\Behat\Page\Admin\Crud\CreatePageInterface;
use Sylius\Behat\Page\Admin\Crud\IndexPageInterface;
use Sylius\Behat\Page\Admin\Crud\UpdatePageInterface;
use Sylius\Component\Core\Model\ShippingMethodInterface;
use Sylius\Component\Core\Repository\ShippingMethodRepositoryInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Tests\HiPay\SyliusHiPayPlugin\Behat\Element\Admin\OneyShippingMethod\FormElementInterface;
use Webmozart\Assert\Assert;

final readonly class ManagingOneyShippingMethodsContext implements Context
{
    /**
     * @param ShippingMethodRepositoryInterface<ShippingMethodInterface> $shippingMethodRepository
     */
    public function __construct(
        private IndexPageInterface $indexPage,
        private CreatePageInterface $createPage,
        private UpdatePageInterface $updatePage,
        private FormElementInterface $formElement,
        private EntityManagerInterface $entityManager,
        private ShippingMethodRepositoryInterface $shippingMethodRepository,
        private TranslatorInterface $translator,
    ) {
    }

    /**
     * @Given there is an Oney shipping method for shipping method with code :code with Oney standard shipping method :shippingMethodCase
     */
    public function thereIsAnOneyShippingMethodForShippingMethodWithCodeWithOneyStandardShippingMethod(
        string $code,
        string $shippingMethodCase,
    ): void {
        $shippingMethod = $this->shippingMethodRepository->findOneBy(['code' => $code]);
        Assert::notNull($shippingMethod, sprintf('Shipping method with code "%s" not found.', $code));

        $mapping = new OneyShippingMethod();
        $mapping->setShippingMethod($shippingMethod);
        $mapping->setOneyShippingMethod($this->parseOneyStandardShippingMethod($shippingMethodCase));
        $mapping->setOneyPreparationTime(0);
        $mapping->setOneyDeliveryTime(0);

        $this->entityManager->persist($mapping);
        $this->entityManager->flush();
    }

    /**
     * @When I browse Oney shipping methods
     */
    public function iBrowseOneyShippingMethods(): void
    {
        $this->indexPage->open();
    }

    /**
     * @When I want to create a new Oney shipping method
     */
    public function iWantToCreateANewOneyShippingMethod(): void
    {
        $this->createPage->open();
    }

    /**
     * @When I want to modify the Oney shipping method for shipping method with code :code
     */
    public function iWantToModifyTheOneyShippingMethodForShippingMethodWithCode(string $code): void
    {
        $shippingMethod = $this->shippingMethodRepository->findOneBy(['code' => $code]);
        Assert::notNull($shippingMethod, sprintf('Shipping method with code "%s" not found.', $code));

        /** @var OneyShippingMethod|null $mapping */
        $mapping = $this->entityManager->getRepository(OneyShippingMethod::class)->findOneBy(['shippingMethod' => $shippingMethod]);
        Assert::notNull($mapping);

        $this->updatePage->open(['id' => $mapping->getId()]);
    }

    /**
     * @When I select shipping method :name for the Oney shipping method
     */
    public function iSelectShippingMethodForTheOneyShippingMethod(string $name): void
    {
        $this->formElement->selectShippingMethodByName($name);
    }

    /**
     * @When I select Oney standard shipping method :shippingMethodCase
     */
    public function iSelectOneyStandardShippingMethod(string $shippingMethodCase): void
    {
        $enum = $this->parseOneyStandardShippingMethod($shippingMethodCase);
        $label = $this->translator->trans(
            'sylius_hipay_plugin.oney_standard_shipping_method.' . $enum->value,
            [],
            'messages',
        );
        $this->formElement->selectOneyStandardShippingMethodByLabel($label);
    }

    /**
     * @When I set Oney preparation time to :value
     */
    public function iSetOneyPreparationTimeTo(int $value): void
    {
        $this->formElement->setOneyPreparationTime($value);
    }

    /**
     * @When I set Oney delivery time to :value
     */
    public function iSetOneyDeliveryTimeTo(int $value): void
    {
        $this->formElement->setOneyDeliveryTime($value);
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
     * @When I delete the Oney shipping method for shipping method with code :code
     */
    public function iDeleteTheOneyShippingMethodForShippingMethodWithCode(string $code): void
    {
        $this->indexPage->deleteResourceOnPage(['shippingMethod.code' => $code]);
    }

    /**
     * @Then I should see :count Oney shipping method(s) in the list
     */
    public function iShouldSeeOneyShippingMethodsInTheList(int $count): void
    {
        Assert::same($this->indexPage->countItems(), $count);
    }

    /**
     * @Then I should see an Oney shipping method for shipping method with code :code in the list
     * @Then the Oney shipping method for shipping method with code :code should appear in the list
     */
    public function iShouldSeeAnOneyShippingMethodForShippingMethodWithCodeInTheList(string $code): void
    {
        $this->indexPage->open();
        Assert::true($this->indexPage->isSingleResourceOnPage(['shippingMethod.code' => $code]));
    }

    /**
     * @Then the Oney shipping method for shipping method with code :code should have Oney standard shipping method :shippingMethodCase
     */
    public function theOneyShippingMethodForShippingMethodWithCodeShouldHaveOneyStandardShippingMethod(
        string $code,
        string $shippingMethodCase,
    ): void {
        $this->entityManager->clear();
        $shippingMethod = $this->shippingMethodRepository->findOneBy(['code' => $code]);
        Assert::notNull($shippingMethod);

        /** @var OneyShippingMethod|null $mapping */
        $mapping = $this->entityManager->getRepository(OneyShippingMethod::class)->findOneBy(['shippingMethod' => $shippingMethod]);
        Assert::notNull($mapping);
        Assert::same($mapping->getOneyShippingMethod(), $this->parseOneyStandardShippingMethod($shippingMethodCase));
    }

    /**
     * @Then there should be no Oney shipping method for shipping method with code :code
     */
    public function thereShouldBeNoOneyShippingMethodForShippingMethodWithCode(string $code): void
    {
        $this->entityManager->clear();
        $shippingMethod = $this->shippingMethodRepository->findOneBy(['code' => $code]);
        Assert::notNull($shippingMethod);

        /** @var OneyShippingMethod|null $mapping */
        $mapping = $this->entityManager->getRepository(OneyShippingMethod::class)->findOneBy(['shippingMethod' => $shippingMethod]);
        Assert::null($mapping, sprintf('Oney shipping method for shipping method "%s" still exists.', $code));
    }

    /**
     * @Then I should be notified that the form contains errors
     */
    public function iShouldBeNotifiedThatTheFormContainsErrors(): void
    {
        Assert::true($this->formElement->hasFormErrorAlert());
    }

    /**
     * @Then the Sylius shipping method field should only list shipping method :name
     */
    public function theSyliusShippingMethodFieldShouldOnlyListShippingMethod(string $name): void
    {
        Assert::same(
            $this->formElement->getSelectableShippingMethodNames(),
            [$name],
            sprintf('Expected the Sylius shipping method select to offer only "%s".', $name),
        );
    }

    private function parseOneyStandardShippingMethod(string $caseName): OneyStandardShippingMethod
    {
        foreach (OneyStandardShippingMethod::cases() as $case) {
            if ($case->name === $caseName) {
                return $case;
            }
        }

        throw new InvalidArgumentException(sprintf('Unknown Oney standard shipping method case "%s".', $caseName));
    }
}
