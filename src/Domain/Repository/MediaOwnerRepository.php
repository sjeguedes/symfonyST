<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\MediaOwner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class MediaOwnerRepository.
 *
 * Manage MediaOwner entity data in database.
 */
class MediaOwnerRepository extends ServiceEntityRepository
{
    /**
     * MediaOwnerRepository constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaOwner::class);
    }
}
