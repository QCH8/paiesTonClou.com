<?php

namespace App\DataFixtures;

use App\Entity\Category;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;

class CategoryFixture extends Fixture
{
    public const CATEGORY_REFERENCE_PREFIX = 'category_';
    public const CATEGORY_COUNT = 8;

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        for ($i = 0; $i < self::CATEGORY_COUNT; $i++) {
            $name = ucfirst($faker->unique()->word());
            $category = (new Category())
                ->setName($name)
                ->setSlug($this->slugify($name . '-' . ($i + 1)));

            $manager->persist($category);
            $this->addReference(self::CATEGORY_REFERENCE_PREFIX . $i, $category);
        }

        // Add a stable "featured" category used by tests/manual checks.
        $featured = (new Category())
            ->setName('Nouveautes')
            ->setSlug('nouveautes');
        $manager->persist($featured);
        $this->addReference(self::CATEGORY_REFERENCE_PREFIX . self::CATEGORY_COUNT, $featured);

        $manager->flush();
    }

    private function slugify(string $value): string
    {
        $value = strtolower(trim($value));
        $value = preg_replace('/[^a-z0-9]+/', '-', $value) ?? '';

        return trim($value, '-');
    }
}
