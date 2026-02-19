<?php

namespace App\Entity;

use App\Repository\OrderItemRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: OrderItemRepository::class)]
class OrderItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column]
    private ?int $quantity = null;

    #[ORM\Column(options: ['comment' => 'Amount in cents'])]
    private ?int $unitPriceHTSnapshot = null;

    #[ORM\Column]
    private ?float $vatRateSnapshot = null;

    #[ORM\Column(length: 255)]
    private ?string $nameSnapshot = null;

    #[ORM\Column(length: 255)]
    private ?string $stockKeepingUnitSnapshot = null;

    #[ORM\ManyToOne(inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Order $order = null;

    #[ORM\ManyToOne(inversedBy: 'orderItems')]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
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

    public function getNameSnapshot(): ?string
    {
        return $this->nameSnapshot;
    }

    public function setNameSnapshot(string $nameSnapshot): static
    {
        $this->nameSnapshot = $nameSnapshot;

        return $this;
    }

    public function getStockKeepingUnitSnapshot(): ?string
    {
        return $this->stockKeepingUnitSnapshot;
    }

    public function setStockKeepingUnitSnapshot(string $stockKeepingUnitSnapshot): static
    {
        $this->stockKeepingUnitSnapshot = $stockKeepingUnitSnapshot;

        return $this;
    }

    public function getOrder(): ?Order
    {
        return $this->order;
    }

    public function setOrder(?Order $order): static
    {
        $this->order = $order;

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
