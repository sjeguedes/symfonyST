<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

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
class User
{
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
     * @ORM\Column(type="string", name="family_name")
     */
    private $familyName;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string", name="first_name")
     */
    private $firstName;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string", name="nick_name", unique=true)
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
     * @param string                  $password a default BCrypt encoded password
     * @param \DateTimeInterface|null $creationDate
     * @param \DateTimeInterface|null $updateDate
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
        \DateTimeInterface $creationDate = null,
        \DateTimeInterface $updateDate = null
    ) {
        $this->uuid = Uuid::uuid4();
        assert(!empty($familyName),'User family name can not be empty!');
        $this->familyName = $familyName;
        assert(!empty($firstName),'User first name can not be empty!');
        $this->firstName = $firstName;
        assert(!empty($nickName),'User nickname can not be empty!');
        $this->nickName = $nickName;
        assert(!empty($email) && filter_var($email,FILTER_VALIDATE_EMAIL),'User email format must be valid!');
        $this->email = $email;
        // BCrypt encoded password
        assert(!\is_null($password) && preg_match('/^\$2[ayb]\$.{56}$/', $password),'User BCrypt password must be valid!');
        $this->password = $password;
        $createdAt = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->creationDate = $createdAt;
        $updatedAt = !\is_null($updateDate) ? $updateDate : $this->creationDate;
        assert($updatedAt >= $this->creationDate,'User can not be created after update date!');
        $this->updateDate = $updatedAt;
        $this->medias = new ArrayCollection();
    }

    /**
     * Change family name after creation.
     *
     * @param string $familyName
     *
     * @return User
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
     * Change password after creation.
     *
     * @param string $password
     *
     * @return User
     */
    public function modifyPassword(string $password) : self
    {
        // BCrypt encoded password
        if (empty($password) || !preg_match('/^\$2[ayb]\$.{56}$/', $password)) {
            throw new \InvalidArgumentException('User BCrypt password is not valid!');
        }
        $this->password = $password;
        return $this;
    }

    /**
     * Generate a password renewal token.
     *
     * User has forgotten his password, so this token is used to renew it.
     * Token format is generated with: substr(hash('sha256', bin2hex(openssl_random_pseudo_bytes(8))), 0, 15);
     *
     * @param string $renewalToken
     *
     * @return User
     */
    public function generateRenewalToken(string $renewalToken) : self
    {
        if (empty($renewalToken) || !preg_match('/^[a-z0-9]{15}$/', $renewalToken)) {
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
     * Generate a password renewal request date.
     *
     * User has forgotten his password, so this date is used to limit time validity for token above.
     *
     * @param \DateTimeInterface $renewalRequestDate
     *
     * @return User
     */
    public function generateRenewalRequestDate(\DateTimeInterface $renewalRequestDate) : self
    {
        if ($this->creationDate > $renewalRequestDate) {
            throw new \RuntimeException('Renewal request date is not logical: User renewal token can not be created before User creation date!');
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
     */
    public function getPassword() : string
    {
        return $this->password;
    }

    /**
     * @return string
     */
    public function getRenewalToken() : string
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
     * @return \DateTimeInterface
     */
    public function getRenewalRequestDate() : \DateTimeInterface
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
}