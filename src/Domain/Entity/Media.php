<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Repository\MediaRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Media entity.
 *
 * Define Media entity schema in database, its initial state and behaviors.
 *
 * @ORM\Entity(repositoryClass=MediaRepository::class)
 * @ORM\Table(name="medias")
 */
class Media
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
     * @var MediaSource (owning side of entity relation)
     *
     * @ORM\OneToOne(targetEntity=MediaSource::class, inversedBy="media", cascade={"persist", "remove"})
     * @ORM\JoinColumn(name="media_source_uuid", referencedColumnName="uuid", nullable=false)
     */
    private $mediaSource;

    /**
     * @var MediaOwner|null (owning side of entity relation)
     *
     * It can be null in case of direct upload!
     *
     * @ORM\ManyToOne(targetEntity=MediaOwner::class, inversedBy="medias")
     * @ORM\JoinColumn(name="media_owner_uuid", referencedColumnName="uuid", nullable=true)
     */
    private $mediaOwner;

    /**
     * @var MediaType (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=MediaType::class, inversedBy="medias")
     * @ORM\JoinColumn(name="media_type_uuid", referencedColumnName="uuid", nullable=false)
     */
    private $mediaType;

    /**
     * @var User (owning side of entity relation)
     *
     * It can be changed in case user creator account removal!
     *
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="medias")
     * @ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid", nullable=false)
     */
    private $user;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    private $isMain;

    /**
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    private $isPublished;

    /**
     * @var int
     *
     * @ORM\Column(type="smallint", nullable=true, options={"unsigned":true})
     */
    private $showListRank;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime")
     */
    private $updateDate;

    /**
     * Media constructor.
     *
     * @param MediaOwner|null         $mediaOwner  a media attachment which can be in case of direct upload
     * @param MediaSource             $mediaSource
     * @param MediaType               $mediaType
     * @param User                    $user        a media creator ("author")
     * @param bool                    $isMain
     * @param bool                    $isPublished
     * @param int                     $showListRank
     * @param \DateTimeInterface|null $creationDate
     *
     * @throws \Exception
     */
    public function __construct(
        ?MediaOwner $mediaOwner,
        MediaSource $mediaSource,
        MediaType $mediaType,
        User $user,
        bool $isMain = false,
        bool $isPublished = false,
        int $showListRank = null,
        \DateTimeInterface $creationDate = null
    ) {
        // Show list rank can be null, when the media entity to create is based on an Image entity
        // generated during image direct upload, or if rank makes no sense!
        if (!\is_null($showListRank)) {
            \assert($showListRank > 0, 'Show list rank must be greater than 0!');
        }
        $this->uuid = Uuid::uuid4();
        $this->mediaOwner = $mediaOwner;
        $this->mediaSource = $mediaSource;
        $this->mediaType = $mediaType;
        $this->user = $user;
        $this->isMain = $isMain;
        $this->isPublished = $isPublished;
        $this->showListRank = $showListRank;
        $this->creationDate = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->updateDate = $this->creationDate;
    }

    /**
     * Change assigned media owner after creation.
     *
     * @param MediaOwner $mediaOwner
     *
     * After creation a media owner cannot be null!
     *
     * @return Media
     */
    public function modifyMediaOwner(MediaOwner $mediaOwner): self
    {
        $this->mediaOwner = $mediaOwner;
        return $this;
    }

    /**
     * Change assigned media source after creation.
     *
     * @param MediaSource $mediaSource
     *
     * @return Media
     */
    public function modifyMediaSource(MediaSource $mediaSource): self
    {
        $this->mediaSource = $mediaSource;
        return $this;
    }

    /**
     * Change assigned media type after creation.
     *
     * @param MediaType $mediaType
     *
     * @return Media
     */
    public function modifyMediaType(MediaType $mediaType): self
    {
        $this->mediaType = $mediaType;
        return $this;
    }

    /**
     * Change assigned user after creation.
     *
     * @param User $user
     *
     * After creation a user "creator" can be changed due to user account removal!
     * For instance an anonymous user or administrator can be affected to replace the creator.
     *
     * @return Media
     */
    public function modifyUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Change main state after creation.
     *
     * This media is the media to promote in single trick page header, if it is set to true.
     *
     * @param bool $isMain
     *
     * @return Media
     *
     * @throws \Exception
     */
    public function modifyIsMain(bool $isMain): self
    {
        if (true === $isMain && false === $this->isPublished) {
            throw new \RuntimeException('You cannot use this media as main, if it is not published as the same time!');
        }
        $this->isMain = $isMain;
        return $this;
    }

    /**
     * Change publication state after creation.
     *
     * @param bool $isPublished
     *
     * @return Media
     *
     * @throws \Exception
     */
    public function modifyIsPublished(bool $isPublished): self
    {
        if (false === $this->isPublished && true === $this->isMain) {
            throw new \RuntimeException('You cannot un-publish this media, if it is used as main at the same time!');
        }
        $this->isPublished = $isPublished;
        return $this;
    }

    /**
     * Change show list rank after creation.
     *
     * This rank is the position used by this media to be shown in images or videos list on single trick page.
     *
     * @param int $showListRank
     *
     * @return Media
     *
     * @throws \Exception
     */
    public function modifyShowListRank(int $showListRank): self
    {
        if (0 >= $showListRank) {
            throw new \RuntimeException('Show list rank must be greater than 0!');
        }
        $this->showListRank = $showListRank;
        return $this;
    }

    /**
     * Change update date after creation.
     *
     * @param \DateTimeInterface $updateDate
     *
     * @return Media
     *
     * @throws \Exception
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate): self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: Media cannot be created after modified update date!');
        }
        $this->updateDate = $updateDate;
        return $this;
    }

    /**
     * @return UuidInterface
     */
    public function getUuid(): UuidInterface
    {
        return $this->uuid;
    }

    /**
     * @return MediaOwner
     */
    public function getMediaOwner(): MediaOwner
    {
        return $this->mediaOwner;
    }

    /**
     * @return MediaSource
     */
    public function getMediaSource(): MediaSource
    {
        return $this->mediaSource;
    }

    /**
     * @return MediaType
     */
    public function getMediaType(): MediaType
    {
        return $this->mediaType;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
    }

    /**
     * @return bool
     */
    public function getIsMain(): bool
    {
        return $this->isMain;
    }

    /**
     * @return bool
     */
    public function getIsPublished(): bool
    {
        return $this->isPublished;
    }

    /**
     * @return int
     */
    public function getShowListRank(): int
    {
        return $this->showListRank;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getCreationDate(): \DateTimeInterface
    {
        return $this->creationDate;
    }

    /**
     * @return \DateTimeInterface
     */
    public function getUpdateDate(): \DateTimeInterface
    {
        return $this->updateDate;
    }
}
