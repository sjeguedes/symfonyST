<?php

declare(strict_types = 1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class UserRepository.
 *
 * Manage User entity data in database.
 */
class UserRepository extends ServiceEntityRepository implements UserLoaderInterface
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

    /**
     * Find a user only with email.
     *
     * @param string $email
     *
     * @return User|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByEmail(string $email) : ?User
    {
       return $this->createQueryBuilder('u')
        ->where('u.email = :query')
        ->setParameter('query', $email)
        ->getQuery()
        ->getOneOrNullResult();
    }

    /**
     * {@inheritdoc}
     *
     * Load a user finding him with his nickname or email.
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function loadUserByUsername($username) : ?UserInterface
    {
        return $this->createQueryBuilder('u')
            ->where('u.nickName = :query OR u.email = :query')
            ->setParameter('query', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }
}