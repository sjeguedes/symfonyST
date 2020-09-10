<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\MediaSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class MediaSourceRepository.
 *
 * Manage MediaSource entity data in database.
 */
class MediaSourceRepository extends ServiceEntityRepository
{
    /**
     * MediaSourceRepository constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaSource::class);
    }
}
