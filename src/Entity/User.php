<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
#[UniqueEntity(fields: ['email'], message: 'Cet email est déjà pris.')]
class User implements PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank()]
    #[Assert\Length(max: 180, maxMessage: "Can't be more than 180 chars.")]
    #[Assert\Email]
    private ?string $email = null;

    #[ORM\Column]
    #[Assert\NotNull()]
    private array $roles = [];

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank()]
    private ?string $passwordHash = null;

    #[ORM\Column]
    #[Assert\NotBlank()]
    private ?\DateTimeImmutable $createdAt = null;

    /**
     * @var Collection<int, Order>
     */
    #[ORM\OneToMany(targetEntity: Order::class, mappedBy: 'user')]
    private Collection $orders;

    /**
     * @var Collection<int, Cart>
     */
    #[ORM\OneToMany(targetEntity: Cart::class, mappedBy: 'user')]
    private Collection $cart;

    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable("now", new \DateTimeZone("Europe/Paris"));
        $this->orders = new ArrayCollection();
        $this->cart = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getRoles(): array
    {
        return $this->roles;
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    public function getPasswordHash(): ?string
    {
        return $this->passwordHash;
    }

    public function setPasswordHash(string $passwordHash): static
    {
        $this->passwordHash = $passwordHash;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, Order>
     */
    public function getOrders(): Collection
    {
        return $this->orders;
    }

    public function addOrders(Order $order): static
    {
        if (!$this->orders->contains($order)) {
            $this->orders->add($order);
            $order->setUser($this);
        }

        return $this;
    }

    public function removeOrderId(Order $order): static
    {
        if ($this->orders->removeElement($order)) {
            // set the owning side to null (unless already changed)
            if ($order->getUser() === $this) {
                $order->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Cart>
     */
    public function getCart(): Collection
    {
        return $this->cart;
    }

    public function addCart(Cart $cart): static
    {
        if (!$this->cart->contains($cart)) {
            $this->cart->add($cart);
            $cart->setUser($this);
        }

        return $this;
    }

    public function removeCart(Cart $cart): static
    {
        if ($this->cart->removeElement($cart)) {
            // set the owning side to null (unless already changed)
            if ($cart->getUser() === $this) {
                $cart->setUser(null);
            }
        }

        return $this;
    }

    public function getPassword(): ?string
    {
        // TODO: Implement getPassword() method.
    }
}
