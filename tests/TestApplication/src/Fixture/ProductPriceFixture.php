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

namespace Tests\HiPay\SyliusHiPayPlugin\Fixture;

use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Sylius\Bundle\FixturesBundle\Fixture\AbstractFixture;
use Sylius\Component\Core\Model\ProductInterface;
use Sylius\Component\Core\Model\ProductVariantInterface;
use Sylius\Component\Core\Repository\ProductRepositoryInterface;
use Symfony\Component\Config\Definition\Builder\ArrayNodeDefinition;

/**
 * Sets a fixed price on sample products. The Sylius product fixture draws a random price,
 * whereas the basket test cases (tax excluded prices, rounding remainders) need exact amounts.
 */
final class ProductPriceFixture extends AbstractFixture
{
    /**
     * @param ProductRepositoryInterface<ProductInterface> $productRepository
     */
    public function __construct(
        private readonly ProductRepositoryInterface $productRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function getName(): string
    {
        return 'hipay_product_price';
    }

    /**
     * @param array{custom: list<array{product: string, price: int}>} $options
     */
    public function load(array $options): void
    {
        foreach ($options['custom'] as $productPrice) {
            $product = $this->productRepository->findOneByCode($productPrice['product']);
            if (!$product instanceof ProductInterface) {
                throw new InvalidArgumentException(sprintf('Product "%s" not found.', $productPrice['product']));
            }

            /** @var ProductVariantInterface $variant */
            foreach ($product->getVariants() as $variant) {
                foreach ($variant->getChannelPricings() as $channelPricing) {
                    $channelPricing->setPrice($productPrice['price']);
                }
            }
        }

        $this->entityManager->flush();
    }

    protected function configureOptionsNode(ArrayNodeDefinition $optionsNode): void
    {
        $optionsNode
            ->children()
                ->arrayNode('custom')
                    ->arrayPrototype()
                        ->children()
                            ->scalarNode('product')->isRequired()->cannotBeEmpty()->end()
                            ->integerNode('price')->isRequired()->min(0)->info('In the smallest currency unit')->end()
        ;
    }
}
