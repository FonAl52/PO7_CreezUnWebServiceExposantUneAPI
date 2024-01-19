<?php

namespace App\Entity;

use App\Entity\Customer;
use Doctrine\ORM\Mapping as ORM;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\Collection;
use Doctrine\Common\Collections\ArrayCollection;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Validator\Constraints as Assert;


#[ORM\Entity(repositoryClass: UserRepository::class)]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(['getUsers'])]
    private ?int $id = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\NotBlank(message: "Le username de client est obligatoire.")]
    #[Assert\Length(min: 1, max: 255, minMessage: "Le username doit faire au moins {{ limit }} caractères")]
    #[Assert\Unique(message: "Ce username existe déjà")]
    #[Groups(['getUsers'])]
    private ?string $username = null;

    #[ORM\Column(length: 180, unique: true)]
    #[Assert\Email(message: "The email {{ value }} is not a valid email.")]
    #[Assert\Unique(message: "Cet email existe déjà")]
    #[Groups(['getUsers'])]
    private ?string $email = null;

    #[ORM\Column]
    #[Groups(['getUsers'])]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: false, options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: 'datetime_immutable', nullable: false, options: ["default" => "CURRENT_TIMESTAMP"])]
    private \DateTimeImmutable $updatedAt;

    #[ORM\OneToMany(mappedBy: 'user', targetEntity: Customer::class)]
    private Collection $customers;

    /**
     * Construct
     */
    public function __construct()
    {
        $this->createdAt = new \DateTimeImmutable();
        $this->updatedAt = new \DateTimeImmutable();
        $this->customers = new ArrayCollection();

        //end __construct()
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
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

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username;
    }

    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;
        // guarantee every user at least has ROLE_USER
        $roles[] = 'ROLE_USER';

        return array_unique($roles);
    }

    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * Get user password
     *
     * @return string
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    /**
     * Set user password
     *
     * @param string $password
     * @return static
     */
    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Get user createdAt
     *
     * @return \DateTimeImmutable|null
     */
    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * Set user createdAt
     *
     * @param \DateTimeImmutable $createdAt
     * @return static
     */
    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    /**
     * Get user updatedAt
     *
     * @return \DateTimeImmutable|null
     */
    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * Set user updatedAt
     *
     * @param \DateTimeImmutable $updatedAt
     * @return self
     */
    public function setUpdatedAt(\DateTimeImmutable $updatedAt): self
    {
        $this->updatedAt = $updatedAt;

        return $this;
    }

    /**
     * @see UserInterface
     */
    public function eraseCredentials(): void
    {
        // If you store any temporary, sensitive data on the user, clear it here
        // $this->plainPassword = null;
    }

    /**
     * @return Collection<int, Customer>
     */
    public function getCustomers(): Collection
    {
        return $this->customers;
    }

    public function addCustomer(Customer $customers): static
    {
        if (!$this->customers->contains($customers)) {
            $this->customers->add($customers);
            $customers->setUser($this);
        }

        return $this;
    }

    public function removeCustomer(Customer $customers): static
    {
        if ($this->customers->removeElement($customers)) {
            // set the owning side to null (unless already changed)
            if ($customers->getUser() === $this) {
                $customers->setUser(null);
            }
        }

        return $this;
    }
}
