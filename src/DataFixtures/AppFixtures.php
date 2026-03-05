<?php

namespace App\DataFixtures;

use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\Order;
use App\Entity\OrderItem;
use App\Entity\Payment;
use App\Entity\ProductVariant;
use App\Entity\ShippingAddress;
use App\Entity\StripeEvent;
use App\Entity\User;
use Doctrine\Common\DataFixtures\DependentFixtureInterface;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Faker\Factory;
use Faker\Generator;

class AppFixtures extends Fixture implements DependentFixtureInterface
{
    private const USER_COUNT = 12;
    private const CART_COUNT = 14;
    private const ORDER_COUNT = 24;
    private const USER_REFERENCE_PREFIX = 'user_';

    public function load(ObjectManager $manager): void
    {
        $faker = Factory::create('fr_FR');

        $users = $this->seedUsers($manager, $faker);
        $carts = $this->seedCarts($manager, $faker, $users);
        $this->seedOrders($manager, $faker, $users, $carts);

        $manager->flush();
    }

    public function getDependencies(): array
    {
        return [
            ProductFixture::class,
        ];
    }

    /**
     * @return User[]
     */
    private function seedUsers(ObjectManager $manager, Generator $faker): array
    {
        $users = [];
        for ($i = 0; $i < self::USER_COUNT; $i++) {
            $user = (new User())
                ->setEmail($faker->unique()->safeEmail())
                ->setRoles($i === 0 ? ['ROLE_ADMIN'] : ['ROLE_USER'])
                ->setPasswordHash(password_hash('password123', PASSWORD_BCRYPT));

            $manager->persist($user);
            $users[] = $user;
            $this->addReference(self::USER_REFERENCE_PREFIX . $i, $user);
        }

        return $users;
    }

    /**
     * @param User[] $users
     * @return Cart[]
     */
    private function seedCarts(ObjectManager $manager, Generator $faker, array $users): array
    {
        $carts = [];

        for ($i = 0; $i < self::CART_COUNT; $i++) {
            $cart = (new Cart())
                ->setStatus($faker->randomElement(['active', 'abandoned', 'converted']))
                ->setCurrency('EUR')
                ->setUpdatedAt(new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris')));

            if ($faker->boolean(80)) {
                $cart->setUser($users[array_rand($users)]);
            }

            $itemCount = $faker->numberBetween(1, 4);
            for ($j = 0; $j < $itemCount; $j++) {
                $variant = $this->randomVariant($faker);
                $quantity = $faker->numberBetween(1, 4);

                $item = (new CartItem())
                    ->setCart($cart)
                    ->setProductVariant($variant)
                    ->setQuantity($quantity)
                    ->setUnitPriceHTSnapshot($variant->getPriceHT() ?? 0)
                    ->setVatRateSnapshot($variant->getVatRate() ?? 0.2);

                $cart->addCartItem($item);
                $manager->persist($item);
            }

            $manager->persist($cart);
            $carts[] = $cart;
        }

        return $carts;
    }

    /**
     * @param User[] $users
     * @param Cart[] $carts
     */
    private function seedOrders(ObjectManager $manager, Generator $faker, array $users, array $carts): void
    {
        $availableCarts = $carts;
        shuffle($availableCarts);

        for ($i = 0; $i < self::ORDER_COUNT; $i++) {
            $status = $faker->randomElement(['pending', 'paid', 'shipped', 'cancelled']);
            $address = (new ShippingAddress())
                ->setFullname($faker->name())
                ->setLine1($faker->streetAddress())
                ->setLine2($faker->boolean(30) ? $faker->secondaryAddress() : null)
                ->setCity($faker->city())
                ->setPostalCode($faker->postcode())
                ->setCountry('France')
                ->setPhone($faker->boolean(70) ? $faker->phoneNumber() : null);

            $order = (new Order())
                ->setNumber(sprintf('ORD-%s-%04d', date('Ymd'), $i + 1))
                ->setStatus($status)
                ->setCustomerEmail($faker->safeEmail())
                ->setCurrency('EUR')
                ->setShippingAddress($address)
                ->setUser($faker->boolean(75) ? $users[array_rand($users)] : null);

            if (!empty($availableCarts) && $faker->boolean(35)) {
                $order->setCart(array_pop($availableCarts));
            }

            $itemCount = $faker->numberBetween(1, 5);
            $totalHT = 0;
            $totalVAT = 0;

            for ($j = 0; $j < $itemCount; $j++) {
                $variant = $this->randomVariant($faker);
                $quantity = $faker->numberBetween(1, 3);
                $unitPrice = $variant->getPriceHT() ?? 0;
                $vatRate = $variant->getVatRate() ?? 0.2;
                $lineHT = $unitPrice * $quantity;
                $lineVAT = (int) round($lineHT * $vatRate);

                $orderItem = (new OrderItem())
                    ->setOrder($order)
                    ->setProductVariant($variant)
                    ->setQuantity($quantity)
                    ->setUnitPriceHTSnapshot($unitPrice)
                    ->setVatRateSnapshot($vatRate)
                    ->setNameSnapshot($variant->getName() ?? 'Produit')
                    ->setStockKeepingUnitSnapshot($variant->getStockKeepingUnit() ?? 'SKU-UNKNOWN');

                $order->addOrderItem($orderItem);
                $manager->persist($orderItem);

                $totalHT += $lineHT;
                $totalVAT += $lineVAT;
            }

            $totalTTC = $totalHT + $totalVAT;
            $order
                ->setTotalHT($totalHT)
                ->setTotalVAT($totalVAT)
                ->setTotalTTC($totalTTC)
                ->setPaidAt(in_array($status, ['paid', 'shipped'], true)
                    ? new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'))
                    : null);

            $manager->persist($order);

            if (in_array($status, ['paid', 'shipped'], true)) {
                $payment = (new Payment())
                    ->setOrder($order)
                    ->setProvider('stripe')
                    ->setStatus('succeeded')
                    ->setStripeCheckoutSessionId('cs_test_' . bin2hex(random_bytes(10)))
                    ->setStripePaymentIntentId('pi_' . bin2hex(random_bytes(10)))
                    ->setAmountTTC($totalTTC)
                    ->setCurrency('EUR')
                    ->setIdempotencyKey('idem_' . bin2hex(random_bytes(8)));

                $order->addPayment($payment);
                $manager->persist($payment);
            }

            $event = (new StripeEvent())
                ->setOrder($order)
                ->setStripeEventId('evt_' . bin2hex(random_bytes(10)))
                ->setType($status === 'paid' || $status === 'shipped'
                    ? 'checkout.session.completed'
                    : 'checkout.session.async_payment_failed')
                ->setPayload([
                    'order_number' => $order->getNumber(),
                    'status' => $status,
                ])
                ->setProcessingStatus($faker->randomElement(['processed', 'pending']))
                ->setProcessedAt($faker->boolean(80)
                    ? new \DateTimeImmutable('now', new \DateTimeZone('Europe/Paris'))
                    : null);
            $order->addStripeEvent($event);
            $manager->persist($event);
        }
    }

    private function randomVariant(Generator $faker): ProductVariant
    {
        return $this->getReference(
            ProductFixture::VARIANT_REFERENCE_PREFIX . $faker->numberBetween(0, ProductFixture::TOTAL_VARIANTS - 1),
            ProductVariant::class
        );
    }
}
