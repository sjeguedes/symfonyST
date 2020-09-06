<?php

declare(strict_types = 1);

namespace App\Domain\Entity;

use App\Domain\Repository\MediaTypeRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * MediaType entity.
 *
 * Define MediaType entity schema in database, its initial state and behaviors.
 *
 * @ORM\Entity(repositoryClass=MediaTypeRepository::class)
 * @ORM\Table(name="media_types")
 */
class MediaType
{
    /**
     * Define allowed images types.
     *
     * Please note this kind of data should be in a configuration file
     * which have to be updated each time media types evolve in database!
     */
    const ALLOWED_IMAGE_TYPES = ['thumbnail', 'normal', 'big', 'avatar'];

    /**
     * Define allowed videos types (providers).
     *
     * Please note this kind of data should be in a configuration file
     * which have to be updated each time media types evolve in database!
     */
    const ALLOWED_VIDEO_TYPES = ['youtube', 'vimeo', 'dailymotion'];

    /**
     * Define media type prefixes.
     *
     * Please note this kind of data should be in a configuration file
     * which have to be updated each time media types evolve in database!
     */
    const TYPE_PREFIXES = ['trick' => 't_', 'user' => 'u_'];

    /**
     * Define immutable types used to filter medias.
     *
     * Please note this kind of data should be in a configuration file
     * which have to be updated each time media types evolve in database!
     */
    const TYPE_CHOICES = [
        'trickThumbnail'   => 't_thumbnail',
        'trickNormal'      => 't_normal',
        'trickBig'         => 't_big',
        'userAvatar'       => 'u_avatar',
        'trickYoutube'     => 't_youtube',
        'trickVimeo'       => 't_vimeo',
        'trickDailymotion' => 't_dailymotion'
    ];

    /**
     * Define media types source types used to filter medias.
     *
     * Please note this kind of data should be in a configuration file
     * which have to be updated each time media types evolve in database!
     */
    const SOURCE_TYPES = ['image', 'video'];

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
     * @var string
     *
     * @ORM\Column(type="string", length=45, unique=true)
     */
    private $type;


    /**
     * @var string
     *
     * @ORM\Column(type="string", length=45)
     */
    private $sourceType;

    /**
     * @var string
     *
     * @ORM\Column(type="string", unique=true)
     */
    private $name;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(type="integer")
     */
    private $width;

    /**
     * @var string
     *
     * @ORM\Column(type="integer")
     */
    private $height;

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
     * @var Collection (inverse side of entity relation)
     *
     * @ORM\OneToMany(targetEntity=Media::class, mappedBy="mediaType")
     */
    private $medias;

    /**
     * MediaType constructor.
     *
     * @param string                  $type
     * @param string                  $sourceType
     * @param string                  $name
     * @param string                  $description
     * @param int                     $width
     * @param int                     $height
     * @param \DateTimeInterface|null $creationDate
     *
     * @throws \Exception
     */
    public function __construct(
        string $type,
        string $sourceType,
        string $name,
        string $description,
        int $width,
        int $height,
        \DateTimeInterface $creationDate = null
    ) {
        \assert(\in_array($type, self::TYPE_CHOICES), 'MediaType type does not exist!');
        \assert(\in_array($sourceType, self::SOURCE_TYPES), 'MediaType source type does not exist!');
        \assert(!empty($name), 'MediaType name can not be empty!');
        \assert(!empty($description), 'MediaType description can not be empty!');
        \assert($width > 0, 'MediaType width must be greater than 0!');
        \assert($height > 0, 'MediaType height must be greater than 0!');
        $this->uuid = Uuid::uuid4();
        $this->type = $type;
        $this->sourceType = $sourceType;
        $this->name = $name;
        $this->description = $description;
        $this->width = $width;
        $this->height = $height;
        $this->creationDate = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->updateDate = $this->creationDate;
        $this->medias = new ArrayCollection();
    }

    /**
     * Change source type after creation.
     *
     * @param string $sourceType
     *
     * @return MediaType
     *
     * @throws \Exception
     */
    public function modifySourceType(string $sourceType) : self
    {
        if (!\in_array($sourceType, self::SOURCE_TYPES)) {
            throw new \InvalidArgumentException('MediaType source type is unknown!');
        }
        $this->sourceType = $sourceType;
        return $this;
    }

    /**
     * Change name after creation.
     *
     * @param string $name
     *
     * @return MediaType
     *
     * @throws \Exception
     */
    public function modifyName(string $name) : self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('MediaType name can not be empty!');
        }
        $this->name = $name;
        return $this;
    }

    /**
    * Change description after creation.
    *
    * @param string $description
    *
    * @return MediaType
    *
    * @throws \Exception
    */
    public function modifyDescription(string $description) : self
    {
        if (empty($description)) {
            throw new \InvalidArgumentException('MediaType description can not be empty!');
        }
        $this->description = $description;
        return $this;
    }

    /**
     * Change update date after creation.
     *
     * @param \DateTimeInterface $updateDate
     *
     * @return MediaType
     *
     * @throws \Exception
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate) : self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: MediaType can not be created after modified update date!');
        }
        $this->updateDate = $updateDate;
        return $this;
    }

    /**
     * Add Media entity to collection.
     *
     * @param Media $media
     *
     * @return MediaType
     */
    public function addMedia(Media $media) : self
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->modifyMediaType($this);
        }
        return $this;
    }

    /**
     * Remove Media entity from collection.
     *
     * @param Media $media
     *
     * @return MediaType
     */
    public function removeMedia(Media $media) : self
    {
        if ($this->medias->contains($media)) {
            $this->medias->removeElement($media);
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
    public function getType() : string
    {
        return $this->type;
    }

    /**
     * @return string
     */
    public function getSourceType() : string
    {
        return $this->sourceType;
    }

    /**
     * @return string
     */
    public function getName() : string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getDescription() : string
    {
        return $this->description;
    }

    /**
     * @return int
     */
    public function getWidth() : int
    {
        return $this->width;
    }

    /**
     * @return int
     */
    public function getHeight() : int
    {
        return $this->height;
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
     * @return Collection|Media[]
     */
    public function getMedias() : Collection
    {
        return $this->medias;
    }
}
