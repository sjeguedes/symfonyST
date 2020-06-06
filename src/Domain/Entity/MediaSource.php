<?php

declare(strict_types = 1);

namespace App\Domain\Entity;

use App\Domain\Repository\MediaSourceRepository;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * MediaSource entity.
 *
 * Define TrickMedia entity schema in database, its initial state and behaviors.
 *
 * Please note this entity is necessary to avoid direct dependency between Image, Video, etc... and Media entities.
 *
 * @ORM\Entity(repositoryClass=MediaSourceRepository::class)
 * @ORM\Table(name="media_sources")
 */
class MediaSource
{
    /**
     * Define types to identify media source.
     */
    const SOURCE_TYPES = [
        Image::class => 'image',
        Video::class => 'video'
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
     * @var Image|null (owning side of entity relation)
     *
     * @ORM\OneToOne(targetEntity=Image::class, inversedBy="mediaSource")
     * @ORM\JoinColumn(name="image_uuid", referencedColumnName="uuid", nullable=true)
     */
    private $image;

    /**
     * @var Video|null (owning side of entity relation)
     *
     * @ORM\OneToOne(targetEntity=Video::class, inversedBy="mediaSource")
     * @ORM\JoinColumn(name="video_uuid", referencedColumnName="uuid", nullable=true)
     */
    private $video;

    /**
     * @var Media (inverse side of entity relation)
     *
     * @ORM\OneToOne(targetEntity=Media::class, mappedBy="mediaSource", cascade={"persist", "remove"}, orphanRemoval=true)
     */
    private $media;

    /**
     * MediaSource constructor.
     *
     * @param object $source
     *
     * @throws \Exception
     */
    public function __construct(object $source)
    {
        // Check parent class to make also fixture proxy work!
        \assert(
            \array_key_exists(
                !\get_parent_class($source) ? \get_class($source) : \get_parent_class($source),
                self::SOURCE_TYPES
            ),
            'MediaSource source type is not allowed!'
        );
        $this->uuid = Uuid::uuid4();
        $this->image = $source instanceof Image ? $source : null;
        $this->video = $source instanceof Video ? $source : null;
    }

    /**
     * Assign a media.
     *
     * @param Media $media
     *
     * @return $this
     */
    public function assignMedia(Media $media) : self
    {
        $this->media = $media;
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
     * @return Media
     */
    public function getMedia() : Media
    {
        return $this->media;
    }

    /**
     * @return Video|null
     */
    public function getVideo() : ?Video
    {
        return $this->video;
    }
}
