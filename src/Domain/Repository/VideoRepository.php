<?php

declare(strict_types = 1);

namespace App\Domain\Repository;

use App\Domain\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class VideoRepository.
 *
 * Manage Video entity data in database.
 */
class VideoRepository extends ServiceEntityRepository
{
    /**
     * VideoRepository constructor.
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Video::class);
    }
}
