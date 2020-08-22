<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\Repository\CommentRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class CommentManager.
 *
 * Manage comments to handle, and retrieve as a "service layer".
 */
class CommentManager
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

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
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->setLogger($logger);
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
}
