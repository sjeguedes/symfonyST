<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class UserRepository.
 *
 * Manage User entity data in database.
 */
class UserRepository extends ServiceEntityRepository
{
    /**
     * UserRepository constructor.
     *
     * @param RegistryInterface $registry
     *
     * @return void
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, User::class);
    }
}