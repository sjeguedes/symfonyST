<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Video entity.
 *
 * Define Video entity schema in database, its initial state and behaviors.
 *
 * @ORM\Entity(repositoryClass=VideoRepository::class)
 * @ORM\Table(name="videos")
 */
class Video
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
    private $url;

    /**
     * @var string
     *
     * @ORM\Column(type="string")
     */
    private $description;

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
     * @ORM\OneToMany(targetEntity=Media::class, cascade={"persist", "remove"}, orphanRemoval=true, mappedBy="video")
     */
    private $medias;

    /**
     * Image constructor.
     *
     * @param string                  $url
     * @param string                  $description
     * @param \DateTimeInterface|null $creationDate
     * @param \DateTimeInterface|null $updateDate
     *
     * @return void
     *
     * @throws \Exception
     */
    public function __construct(
        string $url,
        string $description,
        \DateTimeInterface $creationDate = null,
        \DateTimeInterface $updateDate = null
    ) {
        $this->uuid = Uuid::uuid4();
        assert(!empty($url),'Video URL can not be empty!');
        $this->url = $url;
        assert(!empty($description),'Video description can not be empty!');
        $this->description = $description;
        $createdAt = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->creationDate = $createdAt;
        assert($updateDate > $this->creationDate,'Video can not be created after update date!');
        $this->updateDate = !\is_null($updateDate) ? $updateDate : $this->creationDate;
        $this->medias = new ArrayCollection();
    }

    /**
    * Change URL after creation.
    *
    * @param string $url
    *
    * @return Video
    */
    public function modifyUrl(string $url) : self
    {
        if (empty($url)) {
            throw new \InvalidArgumentException('Video URL can not be empty!');
        }
        $this->url = $url;
        return $this;
    }

    /**
     * Change description after creation.
     *
     * @param string $description
     *
     * @return Video
     */
    public function modifyDescription(string $description) : self
    {
        if (empty($description)) {
            throw new \InvalidArgumentException('Video description can not be empty!');
        }
        $this->description = $description;
        return $this;
    }

    /**
     * Change update date after creation.
     *
     * @param \DateTimeInterface $updateDate
     *
     * @return Video
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate) : self
    {
        if ($this->updateDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: Video can not be created after modified update date!');
        }
        $this->updateDate = $updateDate;
        return $this;
    }

    /**
     * Add Media entity to collection.
     *
     * @param Media $media
     *
     * @return Video
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
     * @return Video
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
    public function getUrl() : string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getDescription() : string
    {
        return $this->description;
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
