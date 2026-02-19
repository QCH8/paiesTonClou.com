<?php

namespace App\Model;

use App\Entity\Category;
use App\Entity\Product;
use App\Entity\ProductVariant;

class ProductSearch
{

    private ?string $q = null;
    private ?Category $category = null;
    private ?string $sku = null;

    private ?int $minPriceHT = null;
    private ?int $maxPriceHT = null;

    public function getQ(): ?string
    {
        return $this->q;
    }

    public function setQ(?string $q): void
    {
        $this->q = $q;
    }

    public function getCategory(): ?Category
    {
        return $this->category;
    }

    public function setCategory(?Category $category): void
    {
        $this->category = $category;
    }

    public function getSku(): ?string
    {
        return $this->sku;
    }

    public function setSku(?string $sku): void
    {
        $this->sku = $sku;
    }

    public function getMinPriceHT(): ?int
    {
        return $this->minPriceHT;
    }

    public function setMinPriceHT(?int $minPriceHT): void
    {
        $this->minPriceHT = $minPriceHT;
    }

    public function getMaxPriceHT(): ?int
    {
        return $this->maxPriceHT;
    }

    public function setMaxPriceHT(?int $maxPriceHT): void
    {
        $this->maxPriceHT = $maxPriceHT;
    }

    public function isActiveOnly(): bool
    {
        return $this->activeOnly;
    }

    public function setActiveOnly(bool $activeOnly): void
    {
        $this->activeOnly = $activeOnly;
    }

    private bool $activeOnly = true;
}
