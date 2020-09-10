<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class MediaRepository.
 *
 * Manage Media entity data in database.
 */
class MediaRepository extends ServiceEntityRepository
{
    /**
     * MediaRepository constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }
}
