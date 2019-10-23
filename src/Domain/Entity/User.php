<?php

declare(strict_types = 1);

namespace App\Domain\Entity;

use App\Domain\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class User.
 *
 * Define User entity schema in database, its initial state and behaviors.
 *
 * Avatar image reference is shared with Image - Media - MediaType entities.
 *
 * @ORM\Entity(repositoryClass=UserRepository::class)
 * @ORM\Table(name="users")
 */
class User implements UserInterface, \Serializable
{
    /**
     * Define default algorithm for password hash.
     */
    const DEFAULT_ALGORITHM = 'BCrypt';

    /**
     * Define a default role for authorization process.
     */
    const DEFAULT_ROLE = 'ROLE_USER';

    /**
     * Define algorithms for password hash.
     */
    const HASH_ALGORITHMS = ['BCrypt', 'Argon2i'];

    const ROLE_LABELS = [
        'ROLE_USER'  => 'Member',
        'ROLE_ADMIN' => 'Admin',
        'ROLE_SUPER_ADMIN' => 'Super admin'
    ];

    /**
     * The internal primary identity key.
     *
     * @var UuidInterface
     *
     * @ORM\Id()
     * @ORM\Column(type="uuid_binary", unique=true)
     */
    private $uuid;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $familyName;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $firstName;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string", unique=true)
     */
    private $nickName;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string", unique=true)
     */
    private $email;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string", length=60, unique=true)
     */
    private $password;

    /**
     * @var string|null a custom salt for password hash (e.g. BCrypt but Symfony does not use custom value for that kind of algorithm!)
     */
    private $salt;

    /**
     * @var array
     *
     * @ORM\Column(type="array")
     */
    private $roles;

    /**
     *
     * @var boolean
     *
     * @ORM\Column(type="boolean")
     */
    private $isActivated;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string", length=15, unique=true, nullable=true)
     */
    private $renewalToken;

    /**
     *
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime", name="creation_date")
     */
    private $creationDate;

    /**
     *
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime", name="update_date")
     */
    private $updateDate;

     /**
     *
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime", name="renewal_request_date", nullable=true)
     */
    private $renewalRequestDate;

    /**
     * @var Collection (inverse side of entity relation)
     *
     * @ORM\OneToMany(targetEntity=Media::class, mappedBy="user")
     */
    private $medias;

    /**
     * @var Collection (inverse side of entity relation)
     *
     * @ORM\OneToMany(targetEntity=Trick::class, mappedBy="user")
     */
    private $tricks;

    /**
     * User constructor.
     *
     * @param string                  $familyName
     * @param string                  $firstName
     * @param string                  $nickName
     * @param string                  $email
     * @param string                  $password an encoded password
     * @param string                  $algorithm a hash algorithm type for password
     * @param array                   $roles
     * @param string|null             $salt
     * @param \DateTimeInterface|null $creationDate
     *
     * @return void
     *
     * @throws \Exception
     */
    public function __construct(
        string $familyName,
        string $firstName,
        string $nickName,
        string $email,
        string $password,
        string $algorithm = self::DEFAULT_ALGORITHM,
        array $roles = [self::DEFAULT_ROLE],
        string $salt = null,
        \DateTimeInterface $creationDate = null
    ) {
        $this->uuid = Uuid::uuid4();
        \assert(!empty($familyName),'User family name can not be empty!');
        $this->familyName = $familyName;
        \assert(!empty($firstName),'User first name can not be empty!');
        $this->firstName = $firstName;
        \assert(!empty($nickName),'User nickname can not be empty!');
        $this->nickName = $nickName;
        \assert($this->isEmailValidated($email),'User email format must be valid!');
        $this->email = $email;
        \assert($this->isPasswordValidated($password, $algorithm),'User password must be valid!');
        $this->password = $password;
        $this->roles = $roles;
        $this->salt = $salt;
        $this->isActivated = false;
        $createdAt = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->creationDate = $createdAt;
        $this->updateDate = $createdAt;
        $this->medias = new ArrayCollection();
    }

    /**
     * Change family name after creation.
     *
     * @param string $familyName
     *
     * @return User
     *
     * @throws \InvalidArgumentException
     */
    public function modifyFamilyName(string $familyName) : self
    {
        if (empty($familyName)) {
            throw new \InvalidArgumentException('User family name can not be empty!');
        }
        $this->familyName = $familyName;
        return $this;
    }

    /**
     * Change first name after creation.
     *
     * @param string $firstName
     *
     * @return User
     *
     * @throws \InvalidArgumentException
     */
    public function modifyFirstName(string $firstName) : self
    {
        if (empty($firstName)) {
            throw new \InvalidArgumentException('User first name can not be empty!');
        }
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * Validate email format.
     *
     * @param string $email
     *
     * @return bool
     */
    private function isEmailValidated(string $email) : bool
    {
        if (empty($email) || !filter_var($email,FILTER_VALIDATE_EMAIL)) {
            return false;
        }
        return true;
    }

    /**
     * Validate password with algorithm type.
     *
     * @param string $password
     * @param string $algorithm
     *
     * @return bool
     */
    private function isPasswordValidated(string $password, string $algorithm) : bool
    {
        if (!\in_array($algorithm, self::HASH_ALGORITHMS)) {
            return false;
        }
        if ('BCrypt' === $algorithm && (empty($password) || !preg_match('/^\$2[ayb]\$.{56}$/', $password))) {
            return false;
        }
        // Other possible cases here like Argon2i: do stuff!
        return true;
    }

    /**
     * Change password after creation.
     *
     * @param string $algorithm
     * @param string $password
     *
     * @return User
     *
     * @throws \InvalidArgumentException
     */
    public function modifyPassword(string $password, string $algorithm) : self
    {
        // BCrypt encoded password
        if (!$this->isPasswordValidated($password, $algorithm)) {
            throw new \InvalidArgumentException('User password is not valid!');
        }
        $this->password = $password;
        return $this;
    }

    /**
     * Validate user roles.
     *
     * @param array $roles
     *
     * @return bool
     */
    private function isRolesArrayValidated(array $roles) : bool
    {
        if (!\in_array(self::DEFAULT_ROLE, $roles)) {
            $roles[] = self::DEFAULT_ROLE;
        }
        $roles = array_unique($roles);
        foreach ($roles as $role) {
            if (substr($role, 0, 5) !== 'ROLE_') {
               return false;
            }
        }
        return true;
    }

    /**
     * Change user roles after creation.
     *
     * @param array $roles
     *
     * @return User
     *
     * @throws \InvalidArgumentException
     */
    public function modifyRoles(array $roles) : self
    {
        if (!$this->isRolesArrayValidated($roles)) {
            throw new \InvalidArgumentException('Each role must begin with "ROLE_"!');
        }
        $this->roles = array_unique($roles);
        return $this;
    }

    /**
     * Change account activated state after creation.
     *
     * @param bool $isActivated
     *
     * @return User
     */
    public function modifyIsActivated(bool $isActivated) : self
    {
        $this->isActivated = $isActivated;
        return $this;
    }

    /**
     * Update a password renewal token.
     *
     * User has forgotten his password, so this token is used to renew it.
     * Token format is generated with: substr(hash('sha256', bin2hex(openssl_random_pseudo_bytes(8))), 0, 15);
     *
     * @param string|null $renewalToken
     *
     * @return User
     *
     * @throws \InvalidArgumentException
     */
    public function updateRenewalToken(?string $renewalToken) : self
    {
        if (!\is_null($renewalToken) && !preg_match('/^[a-z0-9]{15}$/', $renewalToken)) {
            throw new \InvalidArgumentException('User password renewal token must be valid!');
        }
        $this->renewalToken = $renewalToken;
        return $this;
    }

    /**
     * Change update date after creation.
     *
     * @param \DateTimeInterface $updateDate
     *
     * @return User
     *
     * @throws \RuntimeException
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate) : self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: User can not be created after modified update date!');
        }
        $this->updateDate = $updateDate;
        return $this;
    }

    /**
     * Update a password renewal request date.
     *
     * User has forgotten his password, so this date is used to limit time validity for token above.
     *
     * @param \DateTimeInterface|null $renewalRequestDate
     *
     * @return User
     *
     * @throws \RuntimeException
     */
    public function updateRenewalRequestDate(?\DateTimeInterface $renewalRequestDate) : self
    {
        if (!\is_null($renewalRequestDate) && $this->creationDate > $renewalRequestDate) {
            throw new \RuntimeException('Renewal request date is not logical: user renewal token can not be created before user creation date!');
        }
        $this->renewalRequestDate = $renewalRequestDate;
        return $this;
    }

    /**
     * Add Media entity to collection.
     *
     * @param Media $media
     *
     * @return User
     */
    public function addMedia(Media $media) : self
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->modifyUser($this);
        }
        return $this;
    }

    /**
     * Remove Media entity from collection.
     *
     * @param Media $media
     *
     * @return User
     */
    public function removeMedia(Media $media) : self
    {
        if ($this->medias->contains($media)) {
            $this->medias->removeElement($media);
        }
        return $this;
    }

    /**
     * Add Trick entity to collection.
     *
     * @param Trick $trick
     *
     * @return User
     */
    public function addTrick(Trick $trick) : self
    {
        if (!$this->tricks->contains($trick)) {
            $this->tricks->add($trick);
            $trick->modifyUser($this);
        }
        return $this;
    }

    /**
     * Remove Trick entity from collection.
     *
     * @param Trick $trick
     *
     * @return User
     */
    public function removeTrick(Trick $trick) : self
    {
        if ($this->tricks->contains($trick)) {
            $this->tricks->removeElement($trick);
        }
        return $this;
    }

    /**
     * @return UuidInterface
     */
    public function getUuid() : UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @return string
     */
    public function getFamilyName() : string
    {
        return $this->familyName;
    }

    /**
     * @return string
     */
    public function getFirstName() : string
    {
        return $this->firstName;
    }

    /**
     * @return string
     */
    public function getNickName() : string
    {
        return $this->nickName;
    }

    /**
     * @return string
     */
    public function getEmail() : string
    {
        return $this->email;
    }

    /**
     * @return string
     *
     * Mandatory method name due to UserInterface
     */
    public function getPassword() : string
    {
        return $this->password;
    }

    /**
     * @return array
     */
    public function getRoles() : array
    {
        return $this->roles;
    }

    /**
     * Get user main role label.
     *
     * @return string
     */
    public function getMainRoleLabel() : string
    {
        $roleLabels = self::ROLE_LABELS;
        $roles = $this->getRoles();
        $mainRoleLabel = '';
        $found = false;
        foreach ($roles as $value) {
            switch ($value) {
                // Don't forget to add more roles here if others are created.
                case 'ROLE_SUPER_ADMIN':
                case 'ROLE_ADMIN':
                    $mainRoleLabel = $roleLabels[$value];
                    $found = true;
                    break;
                default:
                    $mainRoleLabel = $roleLabels['ROLE_USER'];
            }
            if ($found) {
                break;
            }
        };
        return $mainRoleLabel;
    }

    /**
     * {@inheritdoc}
     */
    public function getSalt() : ?string
    {
        return $this->salt;
    }

    /**
     * {@inheritdoc}
     */
    public function getUsername() : string
    {
        // nickname equals username in App.
        return $this->nickName;
    }

    /**
     * @return bool
     */
    public function getIsActivated() : bool
    {
        return $this->isActivated;
    }

    /**
     * @return string|null
     */
    public function getRenewalToken() : ?string
    {
        return $this->renewalToken;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreationDate() : \DateTimeInterface
    {
        return $this->creationDate;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdateDate() : \DateTimeInterface
    {
        return $this->updateDate;
    }

    /**
     * @return \DateTimeInterface|null
     */
    public function getRenewalRequestDate() : ?\DateTimeInterface
    {
        return $this->renewalRequestDate;
    }

    /**
     * @return Collection|Media[]
     */
    public function getMedias() : Collection
    {
        return $this->medias;
    }

    /**
     * @return Collection|Trick[]
     */
    public function getTricks() : Collection
    {
        return $this->tricks;
    }

    /**
     * {@inheritdoc}
     */
    public function eraseCredentials() : void
    {
        // UserInterface method implementation is not used.
    }

    /**
     * {@inheritdoc}
     *
     * @see \Serializable::serialize()
     */
    public function serialize() : string
    {
        return serialize([
            $this->uuid,
            $this->nickName,
            $this->password,
            $this->isActivated,
            $this->salt
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * @see \Serializable::unserialize()
     */
    public function unserialize($serialized) : void
    {
        list(
            $this->uuid,
            $this->nickName,
            $this->password,
            $this->isActivated,
            $this->salt
        ) = unserialize($serialized); // ['allowed_classes' => false]
    }
}