<?php

declare(strict_types=1);

namespace App\Domain\Entity;

use App\Domain\Repository\CommentRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Comment entity.
 *
 * Define Comment entity schema in database, its initial state and behaviors.
 *
 * @ORM\Entity(repositoryClass=CommentRepository::class)
 * @ORM\Table(name="comments")
 *
 * For personal information:
 * @see https://doctrine2.readthedocs.io/en/latest/reference/association-mapping.html#one-to-many-self-referencing
 * @see https://stackoverflow.com/questions/13623285/doctrine-self-referencing-entity-disable-fetching-of-children
 */
class Comment
{
    /**
     * Sort direction to show comment list.
     */
    const COMMENT_LOADING_MODE = 'DESC';

    /**
     * Number of comments to load for single page "load more" functionality.
     */
    const COMMENT_NUMBER_PER_LOADING = 10;

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
     * One Comment can have many children Comment entities (self referencing inverse side of bidirectional relation).
     *
     * @var Comment[]|Collection
     *
     * @ORM\OneToMany(targetEntity="Comment", mappedBy="parentComment")
     */
    private $children;

    /**
     * A parent comment in case of reply (self referencing owning side of bidirectional relation)
     *
     * @var Comment|null
     *
     * @ORM\ManyToOne(targetEntity="Comment", inversedBy="children")
     * @ORM\JoinColumn(name="parent_comment_uuid", referencedColumnName="uuid", onDelete="SET NULL")
     */
    private $parentComment;

    /**
     * @var string
     *
     * @ORM\Column(type="text")
     */
    private $content;

    /**
     * @var Trick (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=Trick::class, inversedBy="comments")
     * @ORM\JoinColumn(name="trick_uuid", referencedColumnName="uuid", nullable=false)
     */
    private $trick;

    /**
     * @var User as comment "author" (owning side of entity relation)
     *
     * @ORM\ManyToOne(targetEntity=User::class, inversedBy="comments")
     * @ORM\JoinColumn(name="user_uuid", referencedColumnName="uuid", nullable=false)
     */
    private $user;

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
     * @var integer|null a rank value used in lists
     *
     */
    private $rank;

    /**
     * Comment constructor.
     *
     * @param Trick                   $trick
     * @param User                    $user          The "author"
     * @param string                  $content
     * @param Comment|null            $parentComment a possible parent comment in case of reply
     * @param \DateTimeInterface|null $creationDate
     *
     * @throws \Exception
     */
    public function __construct(
        Trick $trick,
        User $user,
        string $content,
        ?Comment $parentComment,
        \DateTimeInterface $creationDate = null
    ) {
        \assert(!empty($content), 'Comment content cannot be empty!');
        $this->uuid = Uuid::uuid4();
        $this->parentComment = $parentComment;
        $this->content = $content;
        $this->trick = $trick;
        $this->user = $user;
        $this->creationDate = !\is_null($creationDate) ? $creationDate : new \DateTime('now');
        $this->updateDate = $this->creationDate;
        $this->rank = null;
        $this->children = new ArrayCollection();
    }

    /**
    * Assign a rank to sort comment (Used to manage a list to show).
    *
    * This data is not persisted but used with a database query.
    *
    * @param int $rank
    *
    * @return Comment
    *
    * @throws \Exception
    */
    public function assignRank(int $rank): self
    {
        if ($rank < 0) {
            throw new \InvalidArgumentException('Comment rank value cannot be negative!');
        }
        $this->rank = $rank;
        return $this;
    }

    /**
     * Change parent comment (possibly with reply case) after creation.
     *
     * @param Comment $parentComment
     *
     * @return Comment
     *
     * @throws \Exception
     */
    public function modifyParentComment(Comment $parentComment): self
    {
        $this->parentComment = $parentComment;
        return $this;
    }

    /**
     * Change content after creation.
     *
     * @param string $content
     *
     * @return Comment
     *
     * @throws \Exception
     */
    public function modifyContent(string $content): self
    {
        if (empty($content)) {
            throw new \InvalidArgumentException('Comment content cannot be empty!');
        }
        $this->content = $content;
        return $this;
    }

    /**
     * Change assigned trick after creation.
     *
     * @param Trick $trick
     *
     * @return Comment
     */
    public function modifyTrick(Trick $trick): self
    {
        $this->trick = $trick;
        return $this;
    }

    /**
     * Change assigned user after creation.
     *
     * @param User $user
     *
     * @return Comment
     */
    public function modifyUser(User $user): self
    {
        $this->user = $user;
        return $this;
    }

    /**
     * Change update date after creation.
     *
     * @param \DateTimeInterface $updateDate
     *
     * @return Comment
     *
     * @throws \Exception
     */
    public function modifyUpdateDate(\DateTimeInterface $updateDate): self
    {
        if ($this->creationDate > $updateDate) {
            throw new \RuntimeException('Update date is not logical: Media cannot be created after modified update date!');
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
     * @return Comment[]|Collection
     */
    public function getChildren(): Collection
    {
        return $this->children;
    }

    /**
     * @return Comment|null
     */
    public function getParentComment(): ?Comment
    {
        return $this->parentComment;
    }

    /**
     * @return string
     */
    public function getContent(): string
    {
        return $this->content;
    }

    /**
     * @return Trick
     */
    public function getTrick(): Trick
    {
        return $this->trick;
    }

    /**
     * @return User
     */
    public function getUser(): User
    {
        return $this->user;
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
     * @return int|null
     */
    public function getRank(): ?int
    {
        return $this->rank;
    }
}
