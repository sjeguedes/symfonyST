<?php

declare(strict_types=1);

namespace App\Domain\Entity;

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
     * Immutable types used to filter medias.
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
     * @ORM\Column(type="string", unique=true)
     */
    private $type;

    /**
     * @var string
     *
     * @ORM\Column(type="string", unique=true)
     */
    private $name;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $description;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="integer")
     */
    private $width;

    /**
     *
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
     * @ORM\OneToMany(targetEntity=Media::class, cascade={"persist", "remove"}, orphanRemoval=true, mappedBy="mediaType")
     */
    private $medias;

    /**
     * MediaType constructor.
     *
     * @param string                  $type
     * @param string                  $name
     * @param string                  $description
     * @param int                     $width
     * @param int                     $height
     * @param \DateTimeInterface|null $creationDate
     * @param \DateTimeInterface|null $updateDate
     *
     * @return void
     *
     * @throws \Exception
     */
    public function __construct(
        string $type,
        string $name,
        string $description,
        int $width,
        int $height,
        \DateTimeInterface $creationDate = null,
        \DateTimeInterface $updateDate = null
    ) {
        $this->uuid = Uuid::uuid4();
        assert(in_array($type,self::TYPE_CHOICES),'MediaType type does not exist!');
        $this->type = $type;
        assert(!empty($name),'MediaType name can not be empty!');
        $this->name = $name;
        assert(!empty($description),'MediaType description can not be empty!');
        $this->description = $description;
        assert($width > 0,'MediaType width must be greater than 0!');
        $this->width = $width;
        assert($height > 0,'MediaType height must be greater than 0!');
        $this->height = $height;
        $createdAt = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->creationDate = $createdAt;
        assert($updateDate > $this->creationDate,'MediaType can not be created after update date!');
        $this->updateDate = !\is_null($updateDate) ? $updateDate : $this->creationDate;
        $this->medias = new ArrayCollection();
    }

    /**
     * Change name after creation.
     *
     * @param string $name
     *
     * @return MediaType
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
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate) : self
    {
        if ($this->updateDate > $updateDate) {
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
