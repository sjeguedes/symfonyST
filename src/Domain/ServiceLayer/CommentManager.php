<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\DTO\CreateCommentDTO;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use App\Domain\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class CommentManager.
 *
 * Manage comments to handle, and retrieve as a "service layer".
 */
class CommentManager extends AbstractServiceLayer
{
    use LoggerAwareTrait;

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
     */
    public function __construct(EntityManagerInterface $entityManager, CommentRepository $repository, LoggerInterface $logger)
    {
        parent::__construct($entityManager, $logger);
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->setLogger($logger);
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
    ) : ?Comment
    {
        // Add comment to trick and user corresponding comment collections
        $newComment->getTrick()->addComment($newComment);
        $newComment->getUser()->addComment($newComment);
        // Save data if necessary
        $object = $this->addAndSaveNewEntity($newComment, $isPersisted, $isFlushed);
        return \is_null($object) ? null : $newComment;
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
    ) : ?Comment
    {
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
     * Get entity manager.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager() : EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Get Comment entity repository.
     *
     * @return CommentRepository
     */
    public function getRepository() : CommentRepository
    {
        return $this->repository;
    }

    /**
     * Remove an Comment entity and all associated entities depending on cascade operations.
     *
     * @param Comment $comment
     * @param bool    $isFlushed
     *
     * @return bool
     */
    public function removeComment(Comment $comment, bool $isFlushed = true) : bool
    {
        // Update associated user and trick comment collections, but it is not really necessary!
        $comment->getUser()->removeComment($comment);
        $comment->getTrick()->removeComment($comment);
        // Proceed to removal in database
        return $this->removeAndSaveNoMoreEntity($comment, $isFlushed);
    }
}
