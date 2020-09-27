<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Repository\ImageRepository;
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
    private $description;

    /**
     * @var string
     *
     * @ORM\Column(type="string", length=4)
     */
    private $format;

    /**
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
     * @var MediaSource (inverse side of relation)
     *
     * @ORM\OneToOne(targetEntity=MediaSource::class, mappedBy="image", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $mediaSource;

    /**
     * Image constructor.
     *
     * @param string                  $name
     * @param string                  $description
     * @param string                  $format
     * @param int                     $size
     * @param \DateTimeInterface|null $creationDate
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
        \assert(!empty($name), 'Image name cannot be empty!'); // This can be improved with regex check!
        \assert(!empty($description), 'Image description cannot be empty!');
        \assert(!empty($format), 'Image format cannot be empty!'); // This can be improved with regex check!
        \assert($size > 0, 'Image size must be greater than 0!');
        $this->uuid = Uuid::uuid4();
        $this->name = $name;
        $this->description = $description;
        $this->format = $format;
        $this->size = $size;
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
     * @return Image
     *
     * @throws \Exception
     */
    public function modifyName(string $name): self
    {
        // This can be improved with regex check!
        if (empty($name)) {
            throw new \InvalidArgumentException('Image name cannot be empty!');
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
    *
    * @throws \Exception
    */
    public function modifyDescription(string $description): self
    {
        if (empty($description)) {
            throw new \InvalidArgumentException('Image description cannot be empty!');
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
     *
     * @throws \Exception
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate): self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: Image cannot be created after modified update date!');
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
    public function getDescription(): string
    {
        return $this->description;
    }

    /**
     * @return string
     */
    public function getFormat(): string
    {
        return $this->format;
    }

    /**
     * @return int
     */
    public function getSize(): int
    {
        return $this->size;
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
