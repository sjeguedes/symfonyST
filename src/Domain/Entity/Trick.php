<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Utils\Traits\StringHelperTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Trick entity.
 *
 * Define Trick entity schema in database, its initial state and behaviors.
 *
 * @ORM\Entity(repositoryClass=TrickRepository::class)
 * @ORM\Table(name="tricks")
 * @ORM\SqlResultSetMappings(
 *      @ORM\SqlResultSetMapping(
 *          name    = "mappingTrickListSortOrder",
 *          columns = {
 *              @ORM\ColumnResult("rank")
 *          }
 *     )
 * )
 */
class Trick
{
    use StringHelperTrait;

    /**
     * Sort direction to show trick list.
     */
    const TRICK_LOADING_MODE = 'DESC';

    /**
     * Number of tricks to load for homepage "load more" functionality.
     */
    const TRICK_NUMBER_PER_LOADING = 10;

    /**
     * Number of tricks to load for complete trick list pagination functionality.
     */
    const TRICK_NUMBER_PER_PAGE = 10;

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
     *
     * @var string
     *
     * @ORM\Column(type="string", unique=true)
     */
    private $name;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="text")
     */
    private $description;

    /**
     *
     * @var string
     *
     * @ORM\Column(type="string", unique=true)
     */
    private $slug;

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
     * @var TrickGroup (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=TrickGroup::class, inversedBy="tricks")
     * @ORM\JoinColumn(name="trick_group_uuid", referencedColumnName="uuid", nullable=false)
     */
    private $trickGroup;

    /**
     * @var Collection (inverse side of entity relation)
     *
     * @ORM\OneToMany(targetEntity=Media::class, cascade={"persist", "remove"}, orphanRemoval=true, mappedBy="trick")
     */
    private $medias;

    /**
     * @var integer|null a rank value used in lists
     *
     */
    private $rank;

    /**
     * Trick constructor.
     *
     * @param string                  $name
     * @param string                  $description
     * @param TrickGroup              $trickGroup
     * @param string|null             $slug
     * @param \DateTimeInterface|null $creationDate
     * @param \DateTimeInterface|null $updateDate
     *
     * @return void
     *
     * @throws \Exception
     */
    public function __construct(
        string $name,
        string $description,
        TrickGroup $trickGroup,
        string $slug = null,
        \DateTimeInterface $creationDate = null,
        \DateTimeInterface $updateDate = null
    ) {
        $this->uuid = Uuid::uuid4();
        assert(!empty($name),'Trick name can not be empty!');
        $this->name = $name;
        assert(!empty($description),'Trick description can not be empty!');
        $this->description = $description;
        $this->trickGroup = $trickGroup;
        !\is_null($slug) ? $this->customizeSlug($slug) : $this->customizeSlug($name);
        $createdAt = !\is_null($creationDate) ? $creationDate :  new \DateTime('now');
        $this->creationDate = $createdAt;
        assert($updateDate > $this->creationDate,'Trick can not be created after update date!');
        $this->updateDate = !\is_null($updateDate) ? $updateDate : $this->creationDate;
        $this->rank = null;
        $this->medias = new ArrayCollection();
    }

    /**
     * Change name after creation.
     *
     * @param string $name
     *
     * @return Trick
     */
    public function modifyName(string $name) : self
    {
        if (empty($name)) {
            throw new \InvalidArgumentException('Trick name can not be empty!');
        }
        $this->name = $name;
        return $this;
    }

    /**
     * Change description after creation.
     *
     * @param string $description
     *
     * @return Trick
     */
    public function modifyDescription(string $description) : self
    {
        if (empty($description)) {
            throw new \InvalidArgumentException('Trick description can not be empty!');
        }
        $this->description = $description;
        return $this;
    }

    /**
     * Customize slug after creation.
     *
     * @param string $slug
     *
     * @return Trick
     *
     * @throws \Exception
     */
    public function customizeSlug(string $slug) : self
    {
        if (empty($slug)) {
            throw new \InvalidArgumentException('Trick slug can not be empty!');
        }
        $this->slug = $this->stringToSlug($slug);
        return $this;
    }

    /**
     * Change update date after creation.
     *
     * @param \DateTimeInterface $updateDate
     *
     * @return Trick
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate) : self
    {
        if ($this->updateDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: Trick can not be created after modified update date!');
        }
        $this->updateDate = $updateDate;
        return $this;
    }

    /**
     * Change assigned trickGroup after creation.
     *
     * @param TrickGroup $trickGroup
     *
     * @return Trick
     */
    public function modifyTrickGroup(TrickGroup $trickGroup) : self
    {
        $this->trickGroup = $trickGroup;
        return $this;
    }

    /**
     * Add Media entity to collection.
     *
     * @param Media $media
     *
     * @return Trick
     */
    public function addMedia(Media $media) : self
    {
        if (!$this->medias->contains($media)) {
            $this->medias->add($media);
            $media->modifyTrick($this);
        }
        return $this;
    }

    /**
     * Remove Media entity from collection.
     *
     * @param Media $media
     *
     * @return Trick
     */
    public function removeMedia(Media $media) : self
    {
        if ($this->medias->contains($media)) {
            $this->medias->removeElement($media);
        }
        return $this;
    }

    /**
     * Assign a rank to sort trick (Used to manage a list to show)
     *
     * This data is not persisted but generated during a database query.
     *
     * @param int $rank
     *
     * @return Trick
     *
     * @throws \InvalidArgumentException
     */
    public function assignRank(int $rank) : self
    {
        if ($rank < 0) {
            throw new \InvalidArgumentException('Trick rank value can not be negative!');

        }
        $this->rank = $rank;
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
     * @return string
     */
    public function getSlug() : string
    {
        return $this->slug;
    }

    /**
     * @return TrickGroup
     */
    public function getTrickGroup() : TrickGroup
    {
        return $this->trickGroup;
    }

    /**
     * @return Collection|Media[]
     */
    public function getMedias() : Collection
    {
        return $this->medias;
    }

    /**
     * @return int|null
     */
    public function getRank() : ?int
    {
        return $this->rank;
    }
}
