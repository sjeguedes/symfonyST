<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Repository\VideoRepository;
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
    protected $uuid;

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
     * @var MediaSource (inverse side of relation)
     *
     * @ORM\OneToOne(targetEntity=MediaSource::class, mappedBy="video", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $mediaSource;

    /**
     * Video constructor.
     *
     * @param string                  $name
     * @param string                  $url
     * @param string                  $description
     * @param \DateTimeInterface|null $creationDate
     *
     * @throws \Exception
     */
    public function __construct(
        string $name,
        string $url,
        string $description,
        \DateTimeInterface $creationDate = null
    ) {
        \assert(!empty($name), 'Video name cannot be empty!'); // This can be improved with regex check!
        \assert(!empty($url), 'Video URL cannot be empty!'); // This can be improved with regex check!
        \assert(!empty($description), 'Video description cannot be empty!');
        $this->uuid = Uuid::uuid4();
        $this->name = $name;
        $this->url = $url;
        $this->description = $description;
        $this->creationDate = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->updateDate = $this->creationDate;
    }

    /**
     * Assign a media source.
     *
     * @param MediaSource $mediaSource
     *
     * @return $this
     */
    public function assignMediaSource(MediaSource $mediaSource): self
    {
        $this->mediaSource = $mediaSource;
        return $this;
    }

    /**
     * Change name after creation.
     *
     * @param string $name
     *
     * @return Video
     *
     * @throws \Exception
     */
    public function modifyName(string $name): self
    {
        // This can be improved with regex check!
        if (empty($name)) {
            throw new \InvalidArgumentException('Video name cannot be empty!');
        }
        $this->name = $name;
        return $this;
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
    public function modifyUrl(string $url): self
    {
        // This can be improved with regex check!
        if (empty($url)) {
            throw new \InvalidArgumentException('Video URL cannot be empty!');
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
    public function modifyDescription(string $description): self
    {
        if (empty($description)) {
            throw new \InvalidArgumentException('Video description cannot be empty!');
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
    public function modifyUpdateDate(\DateTimeInterface $updateDate): self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: Video cannot be created after modified update date!');
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
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function getUrl(): string
    {
        return $this->url;
    }

    /**
     * @return string
     */
    public function getDescription(): string
    {
        return $this->description;
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

    /**
     * @return MediaSource|null
     */
    public function getMediaSource(): ?MediaSource
    {
        return $this->mediaSource;
    }
}
