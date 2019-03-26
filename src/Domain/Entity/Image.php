<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Repository\ImageRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Image entity.
 *
 * Define Image entity schema in database, its initial state and behaviors.
 *
 * @ORM\Entity(repositoryClass=ImageRepository::class)
 * @ORM\Table(name="images")
 */
class Image
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
     * @ORM\Column(type="string", length=4)
     */
    private $format;

    /**
     *
     * @var integer
     *
     * @ORM\Column(type="integer")
     */
    private $size;

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
     * @ORM\OneToMany(targetEntity=Media::class, mappedBy="image")
     */
    private $medias;

    /**
     * Image constructor.
     *
     * @param string                  $name
     * @param string                  $description
     * @param string                  $format
     * @param int                     $size
     * @param \DateTimeInterface|null $creationDate
     *
     * @return void
     *
     * @throws \Exception
     */
    public function __construct(
        string $name,
        string $description,
        string $format,
        int $size,
        \DateTimeInterface $creationDate = null
    ) {
        $this->uuid = Uuid::uuid4();
        \assert(!empty($name),'Image name can not be empty!');
        $this->name = $name;
        \assert(!empty($description),'Image description can not be empty!');
        $this->description = $description;
        \assert(!empty($format),'Image format can not be empty!');
        $this->format = $format;
        \assert($size > 0, 'Image size must be greater than 0!');
        $this->size = $size;
        $createdAt = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->creationDate = $createdAt;
        $this->updateDate = $createdAt;
        $this->medias = new ArrayCollection();
    }

    /**
     * Change name after creation.
     *
     * @param string $name
     *
     * @return Image
     */
    public function modifyName(string $name) : self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Image name can not be empty!');
        }
        $this->name = $name;
        return $this;
    }

    /**
    * Change description after creation.
    *
    * @param string $description
    *
    * @return Image
    */
    public function modifyDescription(string $description) : self
    {
        if (empty($description)) {
            throw new \InvalidArgumentException('Image description can not be empty!');
        }
        $this->description = $description;
        return $this;
    }

    /**
     * Change update date after creation.
     *
     * @param \DateTimeInterface $updateDate
     *
     * @return Image
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate) : self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: Image can not be created after modified update date!');
        }
        $this->updateDate = $updateDate;
        return $this;
    }

    /**
     * Add Media entity to collection.
     *
     * @param Media $media
     *
     * @return Image
     */
    public function addMedia(Media $media) : self
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->modifyImage($this);
        }
        return $this;
    }

    /**
     * Remove Media entity from collection.
     *
     * @param Media $media
     *
     * @return Image
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
     * @return string
     */
    public function getFormat() : string
    {
        return $this->format;
    }

    /**
     * @return int
     */
    public function getSize() : int
    {
        return $this->size;
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
