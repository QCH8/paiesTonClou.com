<?php

namespace App\Entity;

use App\Repository\CartItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CartItemRepository::class)]
class CartItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    #[Assert\NotBlank()]
    private ?int $quantity = null;

    #[ORM\Column(options: ['comment' => 'Amount in cents'])]
    private ?int $unitPriceHTSnapshot = null;

    #[ORM\Column]
    private ?float $vatRateSnapshot = null;

    #[ORM\ManyToOne(inversedBy: 'cartItem')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Cart $cart = null;

    #[ORM\ManyToOne(inversedBy: 'cartItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?ProductVariant $productVariant = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getQuantity(): ?int
    {
        return $this->quantity;
    }

    public function setQuantity(int $quantity): static
    {
        $this->quantity = $quantity;

        return $this;
    }

    public function getUnitPriceHTSnapshot(): ?int
    {
        return $this->unitPriceHTSnapshot;
    }

    public function setUnitPriceHTSnapshot(int $unitPriceHTSnapshot): static
    {
        $this->unitPriceHTSnapshot = $unitPriceHTSnapshot;

        return $this;
    }

    public function getVatRateSnapshot(): ?float
    {
        return $this->vatRateSnapshot;
    }

    public function setVatRateSnapshot(float $vatRateSnapshot): static
    {
        $this->vatRateSnapshot = $vatRateSnapshot;

        return $this;
    }

    public function getCart(): ?Cart
    {
        return $this->cart;
    }

    public function setCart(?Cart $cart): static
    {
        $this->cart = $cart;

        return $this;
    }

    public function getProductVariant(): ?ProductVariant
    {
        return $this->productVariant;
    }

    public function setProductVariant(?ProductVariant $productVariant): static
    {
        $this->productVariant = $productVariant;

        return $this;
    }
}
