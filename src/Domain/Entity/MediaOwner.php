<?php

declare(strict_types = 1);

namespace App\Domain\Entity;

use App\Domain\Repository\MediaOwnerRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * MediaOwner entity.
 *
 * Define MediaOwner entity schema in database, its initial state and behaviors.
 *
 * Please note this entity is necessary to avoid direct dependency
 * between medias owners like Trick, TrickGroup, User, etc... and Media entities.
 *
 * @ORM\Entity(repositoryClass=MediaOwnerRepository::class)
 * @ORM\Table(name="media_owners")
 */
class MediaOwner
{
    /**
     * Define types to identify media owner source.
     */
    const OWNER_TYPES = [
        Trick::class      => 'trick',
        TrickGroup::class => 'trick_group',
        User::class       => 'user'
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
     * @var Collection|Media[] (inverse side of entity relation)
     *
     * @ORM\OneToMany(targetEntity=Media::class, cascade={"persist", "remove"}, orphanRemoval=true, mappedBy="mediaOwner")
     */
    private $medias;

    /**
     * @var Trick|null (owning side of entity relation)
     *
     * @ORM\OneToOne(targetEntity=Trick::class, cascade={"persist"}, inversedBy="mediaOwner")
     * @ORM\JoinColumn(name="trick_uuid", referencedColumnName="uuid", nullable=true)
     */
    private $trick;

    /**
     * @var User|null (owning side of entity relation)
     *
     * @ORM\OneToOne(targetEntity=User::class, inversedBy="mediaOwner")
     * @ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid", nullable=true)
     */
    private $user;

    /**
     *
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     *
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime")
     */
    private $updateDate;

    /**
     * MediaOwner constructor.
     *
     * @param object                  $owner
     * @param \DateTimeInterface|null $creationDate
     *
     * @throws \Exception
     */
    public function __construct(object $owner, \DateTimeInterface $creationDate = null)
    {
        // Check parent class to make also fixture proxy work!
        \assert(
            \array_key_exists(
                !\get_parent_class($owner) ? \get_class($owner) : \get_parent_class($owner),
                self::OWNER_TYPES
            ),
            'MediaOwner owner type is not allowed!'
        );
        $this->uuid = Uuid::uuid4();
        $this->medias = new ArrayCollection();
        $this->trick = $owner instanceof Trick ? $owner : null;
        $this->user = $owner instanceof User ? $owner : null;
        $this->creationDate = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->updateDate = $this->creationDate;
    }

    /**
     * @return UuidInterface
     */
    public function getUuid() : UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @return Collection|Media[]
     */
    public function getMedias() : Collection
    {
        return $this->medias;
    }

    /**
     * @return Trick|null
     */
    public function getTrick() : ?Trick
    {
        return $this->trick;
    }

    /**
     * @return User|null
     */
    public function getUser() : ?User
    {
        return $this->user;
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
     * Add Media entity to collection.
     *
     * @param Media $media
     *
     * @return MediaOwner
     */
    public function addMedia(Media $media) : self
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->modifyMediaOwner($this);
        }
        return $this;
    }

    /**
     * Remove Media entity from collection.
     *
     * @param Media $media
     *
     * @return MediaOwner
     */
    public function removeMedia(Media $media) : self
    {
        if ($this->medias->contains($media)) {
            $this->medias->removeElement($media);
        }
        return $this;
    }
}
