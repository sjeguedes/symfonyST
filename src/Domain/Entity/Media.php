<?php

declare(strict_types = 1);

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
     * @var MediaType (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=MediaType::class, inversedBy="medias")
     * @ORM\JoinColumn(name="media_type_uuid", referencedColumnName="uuid", nullable=false)
     */
    private $mediaType;

    /**
     * @var Image|null (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=Image::class, inversedBy="medias")
     * @ORM\JoinColumn(name="image_uuid", referencedColumnName="uuid", nullable=true)
     */
    private $image;

    /**
     * @var Video|null (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=Video::class, inversedBy="medias")
     * @ORM\JoinColumn(name="video_uuid", referencedColumnName="uuid", nullable=true)
     */
    private $video;

    /**
     * @var Trick|null (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=Trick::class, inversedBy="medias")
     * @ORM\JoinColumn(name="trick_uuid", referencedColumnName="uuid", nullable=true)
     */
    private $trick;

    /**
     * @var User (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="medias")
     * @ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid", nullable=false)
     */
    private $user;

    /**
     *
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    private $isMain;

    /**
     *
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    private $isPublished;

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
     * Media constructor.
     *
     * @param MediaType               $mediaType
     * @param Trick|null              $trick
     * @param User|null               $user
     * @param bool                    $isMain
     * @param bool                    $isPublished
     * @param \DateTimeInterface|null $creationDate
     *
     * @return void
     *
     * @throws \Exception
     */
    private function __construct(
        MediaType $mediaType,
        Trick $trick = null,
        User $user = null,
        bool $isMain = false,
        bool $isPublished = false,
        \DateTimeInterface $creationDate = null
    ) {
        $this->uuid = Uuid::uuid4();
        $this->mediaType = $mediaType;
        $this->trick = $trick;
        $this->user = $user;
        $this->isMain = $isMain;
        $this->isPublished = $isPublished;
        $createdAt = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->creationDate = $createdAt;
        $this->updateDate = $createdAt;
    }

    /**
     * Named constructor used to create instance based on Image instance.
     *
     * @param Image                   $image
     * @param MediaType               $mediaType
     * @param Trick|null              $trick
     * @param User|null               $user
     * @param bool                    $isMain
     * @param bool                    $isPublished
     * @param \DateTimeInterface|null $creationDate
     *
     * @return Media
     *
     * @throws \Exception
     */
    public static function createNewInstanceWithImage(
        Image $image,
        MediaType $mediaType,
        Trick $trick = null,
        User $user = null,
        bool $isMain = false,
        bool $isPublished = false,
        \DateTimeInterface $creationDate = null
    ) : Media
    {
        $self = new self($mediaType, $trick, $user, $isMain, $isPublished, $creationDate);
        $self->image = $image;
        $self->video = null;
        return $self;
    }

    /**
     * Named constructor used to create instance based on Video instance.
     *
     * @param Video                   $video
     * @param MediaType               $mediaType
     * @param Trick|null              $trick
     * @param User|null               $user
     * @param bool                    $isMain
     * @param bool                    $isPublished
     * @param \DateTimeInterface|null $creationDate
     *
     * @return Media
     *
     * @throws \Exception
     */
    public static function createNewInstanceWithVideo(
        Video $video,
        MediaType $mediaType,
        Trick $trick = null,
        User $user = null,
        bool $isMain = false,
        bool $isPublished = false,
        \DateTimeInterface $creationDate = null
    ) : Media
    {
        $self = new self($mediaType, $trick, $user, $isMain, $isPublished, $creationDate);
        $self->video = $video;
        $self->image = null;
        return $self;
    }

    /**
     * Change assigned image resource after creation.
     *
     * @param Image $image
     *
     * @return Media
     */
    public function modifyImage(Image $image) : self
    {
        $this->image = $image;
        return $this;
    }

    /**
     * Change assigned video resource after creation.
     *
     * @param Video $video
     *
     * @return Media
     */
    public function modifyVideo(Video $video) : self
    {
        $this->video = $video;
        return $this;
    }


    /**
     * Change assigned media type after creation.
     *
     * @param MediaType $mediaType
     *
     * @return Media
     */
    public function modifyMediaType(MediaType $mediaType) : self
    {
        $this->mediaType = $mediaType;
        return $this;
    }

    /**
     * Change assigned trick after creation.
     *
     * @param Trick $trick
     *
     * @return Media
     */
    public function modifyTrick(Trick $trick) : self
    {
        $this->trick = $trick;
        return $this;
    }

    /**
     * Change assigned user after creation.
     *
     * @param User $user
     *
     * @return Media
     */
    public function modifyUser(User $user) : self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Change main state after creation.
     *
     * This media is the media to show in trick lists, if it is set to true.
     *
     * @param bool $isMain
     *
     * @return Media
     */
    public function modifyIsMain(bool $isMain) : self
    {
        if (true === $isMain && false === $this->isPublished) {
            throw new \RuntimeException('You can not use this media as main, if it is not published as the same time!');
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
     */
    public function modifyIsPublished(bool $isPublished) : self
    {
        if (false === $this->isPublished && true === $this->isMain) {
            throw new \RuntimeException('You can not un-publish this media, if it is used as main at the same time!');
        }
        $this->isPublished = $isPublished;
        return $this;
    }

    /**
     * Change update date after creation.
     *
     * @param \DateTimeInterface $updateDate
     *
     * @return Media
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate) : self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: Media can not be created after modified update date!');
        }
        $this->updateDate = $updateDate;
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
     * @return Image|null
     */
    public function getImage() : ?Image
    {
        return $this->image;
    }

    /**
     * @return Video|null
     */
    public function getVideo() : ?Video
    {
        return $this->video;
    }

    /**
     * @return MediaType
     */
    public function getMediaType() : MediaType
    {
        return $this->mediaType;
    }

    /**
     * @return Trick
     */
    public function getTrick() : Trick
    {
        return $this->trick;
    }

    /**
     * @return bool
     */
    public function getIsMain() : bool
    {
        return $this->isMain;
    }

    /**
     * @return bool
     */
    public function getIsPublished() : bool
    {
        return $this->isPublished;
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
}
