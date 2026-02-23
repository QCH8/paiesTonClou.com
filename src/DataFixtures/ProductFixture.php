<?php

namespace App\DataFixtures;

use App\Entity\Product;
use App\Entity\ProductVariant;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class ProductFixture extends Fixture implements DependentFixtureInterface
{
    public const PRODUCT_REFERENCE_PREFIX = 'product_';
    public const VARIANT_REFERENCE_PREFIX = 'variant_';
    public const PRODUCT_COUNT = 40;
    public const VARIANTS_PER_PRODUCT = 3;
    public const TOTAL_VARIANTS = self::PRODUCT_COUNT * self::VARIANTS_PER_PRODUCT;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');
        $variantIndex = 0;

        for ($i = 0; $i < self::PRODUCT_COUNT; $i++) {
            $name = ucfirst($faker->words(2, true));
            $product = (new Product())
                ->setName($name)
                ->setSlug($this->slugify($name . '-' . ($i + 1)))
                ->setDescription($faker->sentence(20))
                ->setActive($faker->boolean(90))
                ->setBrandName(ucfirst($faker->company()))
                ->setCategory($this->getReference(
                    CategoryFixture::CATEGORY_REFERENCE_PREFIX . $faker->numberBetween(0, CategoryFixture::CATEGORY_COUNT),
                    \App\Entity\Category::class
                ));

            for ($j = 0; $j < self::VARIANTS_PER_PRODUCT; $j++) {
                $sku = sprintf('SKU-%04d-%02d', $i + 1, $j + 1);
                $vatRate = $faker->randomElement([0.055, 0.1, 0.2]);

                $variant = (new ProductVariant())
                    ->setStockKeepingUnit($sku)
                    ->setName($name . ' - Variante ' . ($j + 1))
                    ->setPriceHT($faker->numberBetween(500, 25000))
                    ->setVatRate($vatRate)
                    ->setActive($faker->boolean(92))
                    ->setAttributes([
                        'color' => $faker->safeColorName(),
                        'size' => $faker->randomElement(['S', 'M', 'L', 'XL']),
                    ])
                    ->setProduct($product);

                $product->addProductVariant($variant);
                $manager->persist($variant);
                $this->addReference(self::VARIANT_REFERENCE_PREFIX . $variantIndex, $variant);
                $variantIndex++;
            }

            $manager->persist($product);
            $this->addReference(self::PRODUCT_REFERENCE_PREFIX . $i, $product);
        }

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            CategoryFixture::class,
        ];
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}
