<?php

declare(strict_types=1);

namespace App\Domain\ServiceLayer;

use App\Domain\DTO\CreateCommentDTO;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use App\Domain\Repository\CommentRepository;
use App\Utils\Traits\SessionHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Class CommentManager.
 *
 * Manage comments to handle, and retrieve as a "service layer".
 */
class CommentManager extends AbstractServiceLayer
{
    use LoggerAwareTrait;
    use SessionHelperTrait;

    /**
     * Define a session key name prefix to store current comment total count.
     */
    const COMMENT_COUNT_SESSION_KEY_PREFIX = 'commentCount-';

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var CommentRepository
     */
    private $repository;

    /**
     * CommentManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param CommentRepository      $repository
     * @param LoggerInterface        $logger
     * @param SessionInterface       $session
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        CommentRepository $repository,
        LoggerInterface $logger,
        SessionInterface $session
    )
    {
        parent::__construct($entityManager, $logger);
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->setLogger($logger);
        $this->setSession($session);
    }

    /**
     * Add (persist) and save Comment entity in database.
     *
     * Please note combinations:
     * - $isPersisted = false, $isFlushed = false means Comment entity must be instantiated only.
     * - $isPersisted = true, $isFlushed = true means Comment entity is added to unit of work and saved in database.
     * - $isPersisted = true, $isFlushed = false means Comment entity is added to unit of work only.
     * - $isPersisted = false, $isFlushed = true means Comment entity is saved in database only with possible change(s) in unit of work.
     *
     * @param Comment $newComment
     * @param bool    $isPersisted
     * @param bool    $isFlushed
     *
     * @return Comment|null
     */
    public function addAndSaveComment(
        Comment $newComment,
        bool $isPersisted = false,
        bool $isFlushed = false
    ): ?Comment {
        // Add comment to trick and user corresponding comment collections
        $newComment->getTrick()->addComment($newComment);
        $newComment->getUser()->addComment($newComment);
        // Save data if necessary
        $object = $this->addAndSaveNewEntity($newComment, $isPersisted, $isFlushed);
        return \is_null($object) ? null : $newComment;
    }

    /**
     * Retrieve and assign trick comments ranks thanks to a simple query in order to compare uuid,
     * depending on comment list results.
     *
     * @param array              $trickCommentsUuidData
     * @param \IteratorAggregate $commentEntries
     * @param string             $order
     *
     * @return \IteratorAggregate|Paginator
     *
     * For information:
     * @see https://stackoverflow.com/questions/2480179/anonymous-recursive-php-functions
     */
    public function addRanksToTrickComments(
        array $trickCommentsUuidData,
        \IteratorAggregate $commentEntries,
        string $order = 'ASC'
    ): \IteratorAggregate {
        return $this->repository->findCommentsRanks(
            $trickCommentsUuidData,
            $commentEntries,
            $order
        );
    }

    /**
     * Create trick comment Comment entity.
     *
     * @param CreateCommentDTO $dataModel
     * @param Trick            $trickToUpdate
     * @param User             $userToUpdate
     * @param bool             $isPersisted
     * @param bool             $isFlushed
     *
     * @return Comment|null
     *
     * @see addAndSaveComment() method to save data (comment instance)
     *
     * @throws \Exception
     */
    public function createTrickComment(
        CreateCommentDTO $dataModel,
        Trick $trickToUpdate,
        User $userToUpdate,
        bool $isPersisted = false,
        bool $isFlushed = false
    ): ?Comment {
        // Create/get trick comment entity
        $commentContent = $dataModel->getContent();
        $commentParentComment = $dataModel->getParentComment();
        $newTrickComment = new Comment(
            $trickToUpdate,
            $userToUpdate,
            $commentContent,
            $commentParentComment
        );
        // Return Comment entity
        // Maybe persist and possibly save data in database
        return $this->addAndSaveComment($newTrickComment, $isPersisted, $isFlushed); // null or the entity
    }

    /**
     * Find comments expected data with query based on their associated trick uuid,
     * depending on creation date sort order.
     *
     * @param UuidInterface $trickUuid
     * @param string        $order       a sort order to use with comment creation date
     * @param bool          $hasUuidOnly retrieve comments uuid and parent comment uuid only
     *
     * @return array
     */
    public function findOnesByTrick(
        UuidInterface $trickUuid,
        string $order = 'ASC',
        bool $hasUuidOnly = false
    ): array {
        return $this->repository->findAllByTrick($trickUuid, $order, $hasUuidOnly);
    }

    /**
     * Find comments (parents and their children) expected data with query based on their associated trick uuid,
     * depending on offset, limit, creation date sort order and first level condition.
     *
     * @param UuidInterface $trickUuid
     * @param int           $offset
     * @param int           $limit
     * @param string        $order          a sort order to use with comment creation date
     * @param bool          $isAtFirstLevel must retrieve only first level comments (parent comment is null)
     *
     * @return \IteratorAggregate|Paginator
     *
     * @throws \Exception
     */
    public function findOnesByTrickWithOffsetLimit(
        UuidInterface $trickUuid,
        int $offset,
        int $limit,
        string $order = 'ASC',
        bool $isAtFirstLevel = false
    ): \IteratorAggregate {
        return $this->repository->findAllByTrickWithOffsetLimit(
            $trickUuid,
            $offset,
            $limit,
            $order,
            $isAtFirstLevel
        );
    }

    /**
     * Get entity manager.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Get Comment entity repository.
     *
     * @return CommentRepository
     */
    public function getRepository(): CommentRepository
    {
        return $this->repository;
    }

    /**
     * Check if comment total count is outdated.
     *
     * For instance, this can happen when entities are added or removed,
     * when a sequential ajax request is performed.
     *
     * @param UuidInterface $trickUuid
     * @param int           $count
     *
     * @return bool
     */
    public function isCountAllOutdated(UuidInterface $trickUuid, int $count): bool
    {
        $keyName = self::COMMENT_COUNT_SESSION_KEY_PREFIX . $trickUuid->toString();
        if ($this->session->has($keyName) && $this->session->get($keyName) !== $count) {
            // Store trick comments total count in session by updating value for use of ajax request.
            $this->session->set($keyName, $count);
            return true;
        }
        return false;
    }

    /**
     * Remove an Comment entity and all associated entities depending on cascade operations.
     *
     * @param Comment $comment
     * @param bool    $isFlushed
     *
     * @return bool
     */
    public function removeComment(Comment $comment, bool $isFlushed = true): bool
    {
        // Update associated user and trick comment collections, but it is not really necessary!
        $comment->getUser()->removeComment($comment);
        $comment->getTrick()->removeComment($comment);
        // Proceed to removal in database
        return $this->removeAndSaveNoMoreEntity($comment, $isFlushed);
    }
}
