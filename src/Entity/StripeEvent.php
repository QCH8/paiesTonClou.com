<?php

namespace App\Entity;

use App\Repository\StripeEventRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: StripeEventRepository::class)]
class StripeEvent
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    private ?string $stripeEventId = null;

    #[ORM\Column(length: 100)]
    private ?string $type = null;

    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $payload = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $receivedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $processedAt = null;

    #[ORM\Column(length: 100)]
    private ?string $processingStatus = null;

    public function __construct(){
        $this->setReceivedAt(new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris")));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getStripeEventId(): ?string
    {
        return $this->stripeEventId;
    }

    public function setStripeEventId(string $stripeEventId): static
    {
        $this->stripeEventId = $stripeEventId;

        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getPayload(): array
    {
        return $this->payload;
    }

    public function setPayload(array $payload): static
    {
        $this->payload = $payload;

        return $this;
    }

    public function getReceivedAt(): ?\DateTimeImmutable
    {
        return $this->receivedAt;
    }

    public function setReceivedAt(\DateTimeImmutable $receivedAt): static
    {
        $this->receivedAt = $receivedAt;

        return $this;
    }

    public function getProcessedAt(): ?\DateTimeImmutable
    {
        return $this->processedAt;
    }

    public function setProcessedAt(?\DateTimeImmutable $processedAt): static
    {
        $this->processedAt = $processedAt;

        return $this;
    }

    public function getProcessingStatus(): ?string
    {
        return $this->processingStatus;
    }

    public function setProcessingStatus(string $processingStatus): static
    {
        $this->processingStatus = $processingStatus;

        return $this;
    }
}
