<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\Repository\TrickGroupRepository;
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
     * @var TrickGroupRepository
     */
    private $repository;

    /**
     * TrickGroupManager constructor.
     *
     * @param TrickGroupRepository   $repository
     * @param LoggerInterface        $logger
     *
     * @return void
     */
    public function __construct(TrickGroupRepository $repository, LoggerInterface $logger)
    {
        $this->repository = $repository;
        $this->setLogger($logger);
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
