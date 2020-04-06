<?php

declare(strict_types = 1);

namespace App\Domain\Entity;

use App\Domain\Repository\VideoRepository;
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
     * @var Media (inverse side of entity relation)
     *
     * @ORM\OneToOne(targetEntity=Media::class, orphanRemoval=true, mappedBy="video")
     */
    private $media;

    /**
     * Image constructor.
     *
     * @param string                  $url
     * @param string                  $description
     * @param \DateTimeInterface|null $creationDate
     *
     * @return void
     *
     * @throws \Exception
     */
    public function __construct(
        string $url,
        string $description,
        \DateTimeInterface $creationDate = null
    ) {
        \assert(!empty($url), 'Video URL can not be empty!');
        \assert(!empty($description), 'Video description can not be empty!');
        $this->uuid = Uuid::uuid4();
        $this->url = $url;
        $this->description = $description;
        $this->creationDate = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->updateDate = $this->creationDate;
    }

    /**
    * Change URL after creation.
    *
    * @param string $url
    *
    * @return Video
    *
    * @throws \Exception
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
     *
     * @throws \Exception
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
     *
     * @throws \Exception
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate) : self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: Video can not be created after modified update date!');
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
     * @return Media
     */
    public function getMedia() : Media
    {
        return $this->media;
    }
}
