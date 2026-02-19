<?php

namespace App\Entity;

use App\Repository\ProductVariantRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ProductVariantRepository::class)]
class ProductVariant
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 64, unique: true)]
    private ?string $stockKeepingUnit = null;

    #[ORM\Column(length: 255)]
    private ?string $name = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $attributes = null;

    #[ORM\Column(options: ['comment' => 'Amount in cents'])]
    private ?int $priceHT = null;

    #[ORM\Column]
    private ?float $vatRate = null;

    #[ORM\Column]
    private ?bool $active = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStockKeepingUnit(): ?string
    {
        return $this->stockKeepingUnit;
    }

    public function setStockKeepingUnit(string $stockKeepingUnit): static
    {
        $this->stockKeepingUnit = $stockKeepingUnit;

        return $this;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(string $name): static
    {
        $this->name = $name;

        return $this;
    }

    public function getAttributes(): ?array
    {
        return $this->attributes;
    }

    public function setAttributes(?array $attributes): static
    {
        $this->attributes = $attributes;

        return $this;
    }

    public function getPriceHT(): ?int
    {
        return $this->priceHT;
    }

    public function setPriceHT(int $priceHT): static
    {
        $this->priceHT = $priceHT;

        return $this;
    }

    public function getVatRate(): ?float
    {
        return $this->vatRate;
    }

    public function setVatRate(float $vatRate): static
    {
        $this->vatRate = $vatRate;

        return $this;
    }

    public function isActive(): ?bool
    {
        return $this->active;
    }

    public function setActive(bool $active): static
    {
        $this->active = $active;

        return $this;
    }
}
