<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\TrickGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class TrickGroupRepository.
 *
 * Manage TrickGroup entity data in database.
 */
class TrickGroupRepository extends ServiceEntityRepository
{
    /**
     * TrickGroupRepository constructor.
     *
     * @param RegistryInterface $registry
     *
     * @return void
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, TrickGroup::class);
    }
}
