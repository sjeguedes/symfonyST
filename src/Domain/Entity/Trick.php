<?php

declare(strict_types = 1);

namespace App\Domain\Entity;

use App\Domain\Repository\TrickRepository;
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
     * @var bool
     *
     * @ORM\Column(type="boolean")
     */
    private $isPublished;

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
     * @var Collection (inverse side of entity relation)
     *
     * @ORM\OneToMany(targetEntity=Comment::class, cascade={"remove"}, orphanRemoval=true, mappedBy="trick")
     */
    private $comments;

    /**
     * @var MediaOwner|null (inverse side of entity relation)
     *
     * The media owner can be null if application allows no media at creation/update!
     *
     * @ORM\OneToOne(targetEntity=MediaOwner::class, mappedBy="trick", cascade={"persist", "remove"}, orphanRemoval=true)
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
     * @param bool                    $isPublished  a publication state which an administrator can change
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
        bool $isPublished = false,
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
        $this->isPublished = $isPublished;
        $this->creationDate = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->updateDate = $this->creationDate;
        $this->rank = null;
        $this->comments = new ArrayCollection();
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
     * Moderate a trick.
     *
     * @param bool $isPublished
     *
     * @return Trick
     */
    public function modifyIsPublished(bool $isPublished) : self
    {
        $this->isPublished = $isPublished;
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
     * Assign a rank to sort trick (Used to manage a list to show).
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
     * Add Comment entity to collection.
     *
     * @param Comment $comment
     *
     * @return Trick
     */
    public function addComment(Comment $comment) : self
    {
        if (!$this->comments->contains($comment)) {
            $this->comments->add($comment);
            $comment->modifyTrick($this);
        }
        return $this;
    }

    /**
     * Remove Comment entity from collection.
     *
     * @param Comment $comment
     *
     * @return Trick
     */
    public function removeComment(Comment $comment) : self
    {
        if ($this->comments->contains($comment)) {
            $this->comments->removeElement($comment);
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
     * @return bool
     */
    public function getIsPublished() : bool
    {
        return $this->isPublished;
    }

    /**
     * @return Collection|Comment[]
     */
    public function getComments() : Collection
    {
        return $this->comments;
    }

    /**
     * @return MediaOwner|null
     *
     * * The media owner can be null when no media is set (trick creation/update)!
     */
    public function getMediaOwner() : ?MediaOwner
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
     * @return User
     *
     * This is the trick author.
     */
    public function getUser() : User
    {
        return $this->user;
    }

    /**
     * @return int|null
     */
    public function getRank() : ?int
    {
        return $this->rank;
    }
}
