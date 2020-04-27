<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\Repository\TrickGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class TrickGroupManager.
 *
 * Manage trick groups to handle, and retrieve as a "service layer".
 */
class TrickGroupManager
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var TrickGroupRepository
     */
    private $repository;

    /**
     * TrickGroupManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param TrickGroupRepository   $repository
     * @param LoggerInterface        $logger
     *
     */
    public function __construct(EntityManagerInterface $entityManager, TrickGroupRepository $repository, LoggerInterface $logger)
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
     * Get TrickGroup entity repository.
     *
     * @return TrickGroupRepository
     */
    public function getRepository() : TrickGroupRepository
    {
        return $this->repository;
    }
}
