<?php

namespace App\Entity;

use App\Repository\OrderRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: OrderRepository::class)]
#[ORM\Table(name: '`order`')]
class Order
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique:true)]
    #[Assert\Unique()]
    private ?string $number = null;

    #[ORM\Column(length: 100)]
    private ?string $status = null;

    #[ORM\Column(length: 180)]
    private ?string $customerEmail = null;

    #[ORM\Column(length: 100)]
    private ?string $currency = null;

    #[ORM\Column(options: ['comment' => 'Amount in cents'])]
    private ?int $totalHT = null;

    #[ORM\Column(options: ['comment' => 'Amount in cents'])]
    private ?int $totalVAT = null;

    #[ORM\Column(options: ['comment' => 'Amount in cents'])]
    private ?int $totalTTC = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $paidAt = null;

    #[ORM\Embedded(class: ShippingAddress::class, columnPrefix: 'shipping_')]
    private ShippingAddress $shippingAddress;

    public function __construct(){
        $this->setCreatedAt(new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris")));
    }

    public function setShippingAddress(ShippingAddress $shippingAddress): static
    {
        $this->shippingAddress = $shippingAddress;

        return $this;
    }

    public function getShippingAddress(): ShippingAddress
    {
        if(!isset($this->shippingAddress)){
            throw new \LogicException("Shipping Address not initialized yet.");
        }
            return $this->shippingAddress;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNumber(): ?string
    {
        return $this->number;
    }

    public function setNumber(string $number): static
    {
        $this->number = $number;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getCustomerEmail(): ?string
    {
        return $this->customerEmail;
    }

    public function setCustomerEmail(string $customerEmail): static
    {
        $this->customerEmail = $customerEmail;

        return $this;
    }

    public function getCurrency(): ?string
    {
        return $this->currency;
    }

    public function setCurrency(string $currency): static
    {
        $this->currency = $currency;

        return $this;
    }

    public function getTotalHT(): ?int
    {
        return $this->totalHT;
    }

    public function setTotalHT(int $totalHT): static
    {
        $this->totalHT = $totalHT;

        return $this;
    }

    public function getTotalVAT(): ?int
    {
        return $this->totalVAT;
    }

    public function setTotalVAT(int $totalVAT): static
    {
        $this->totalVAT = $totalVAT;

        return $this;
    }

    public function getTotalTTC(): ?int
    {
        return $this->totalTTC;
    }

    public function setTotalTTC(int $totalTTC): static
    {
        $this->totalTTC = $totalTTC;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    public function getPaidAt(): ?\DateTimeImmutable
    {
        return $this->paidAt;
    }

    public function setPaidAt(?\DateTimeImmutable $paidAt): static
    {
        $this->paidAt = $paidAt;

        return $this;
    }
}
