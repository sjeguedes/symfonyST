<?php

declare(strict_types = 1);

namespace App\Domain\Repository;

use App\Domain\Entity\MediaSource;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

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
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, MediaSource::class);
    }
}
