<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\MediaOwner;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

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
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, MediaOwner::class);
    }
}
