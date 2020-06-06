<?php

declare(strict_types = 1);

namespace App\Domain\Entity;

use App\Domain\Repository\TrickRepository;
use App\Utils\Traits\StringHelperTrait;
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
     * @ORM\Column(type="datetime")
     */
    private $creationDate;

    /**
     *
     * @var \DateTimeInterface
     *
     * @ORM\Column(type="datetime")
     */
    private $updateDate;

    /**
     * @var MediaOwner (inverse side of entity relation)
     *
     * @ORM\OneToOne(targetEntity=MediaOwner::class, mappedBy="trick")
     */
    private $mediaOwner;

    /**
     * @var TrickGroup (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=TrickGroup::class, inversedBy="tricks")
     * @ORM\JoinColumn(name="trick_group_uuid", referencedColumnName="uuid", nullable=false)
     */
    private $trickGroup;

    /**
     * @var User (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="tricks")
     * @ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid", nullable=false)
     */
    private $user;

    /**
     * @var integer|null a rank value used in lists
     *
     */
    private $rank;

    /**
     * Trick constructor.
     *
     * @param TrickGroup              $trickGroup
     * @param User                    $user
     * @param string                  $name
     * @param string                  $description
     * @param string|null             $slug
     * @param \DateTimeInterface|null $creationDate
     *
     * @throws \Exception
     */
    public function __construct(
        TrickGroup $trickGroup,
        User $user,
        string $name,
        string $description,
        string $slug = null,
        \DateTimeInterface $creationDate = null
    ) {
        \assert(!empty($name), 'Trick name can not be empty!');
        \assert(!empty($description), 'Trick description can not be empty!');
        $this->uuid = Uuid::uuid4();
        $this->trickGroup = $trickGroup;
        $this->user = $user;
        $this->name = $name;
        $this->description = $description;
        $this->slug = !\is_null($slug) && !empty($slug) ? $this->makeSlug($slug) : $this->makeSlug($name);
        $this->creationDate = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->updateDate = $this->creationDate;
        $this->rank = null;
    }

    /**
     * Assign a media owner.
     *
     * @param MediaOwner $mediaOwner
     *
     * @return $this
     */
    public function assignMediaOwner(MediaOwner $mediaOwner) : self
    {
        $this->mediaOwner = $mediaOwner;
        return $this;
    }

    /**
     * Change name after creation.
     *
     * @param string $name
     *
     * @return Trick
     *
     * @throws \Exception
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
     *
     * @throws \Exception
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
        $this->slug = $this->makeSlug($slug);
        return $this;
    }

    /**
     * Change update date after creation.
     *
     * @param \DateTimeInterface $updateDate
     *
     * @return Trick
     *
     * @throws \Exception
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate) : self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: Trick can not be created after modified update date!');
        }
        $this->updateDate = $updateDate;
        return $this;
    }

    /**
     * Change assigned trick group after creation.
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
     * Change assigned user after creation.
     *
     * @param User $user
     *
     * @return Trick
     */
    public function modifyUser(User $user) : self
    {
        $this->user = $user;
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
     * @throws \Exception
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
     * @return MediaOwner
     */
    public function getMediaOwner() : MediaOwner
    {
        return $this->mediaOwner;
    }

    /**
     * @return TrickGroup
     */
    public function getTrickGroup() : TrickGroup
    {
        return $this->trickGroup;
    }

    /**
     * @return int|null
     */
    public function getRank() : ?int
    {
        return $this->rank;
    }
}
