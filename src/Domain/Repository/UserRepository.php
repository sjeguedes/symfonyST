<?php

declare(strict_types = 1);

namespace App\Domain\Repository;

use App\Domain\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;
use Symfony\Bridge\Doctrine\Security\User\UserLoaderInterface;

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
        // Avoid unexpected case sensitive SQL collation to compare lowercase stored email
        $email = strtolower($email);
        return $this->createQueryBuilder('u')
        ->where('u.email = :query')
        ->setParameter('query', $email)
        ->getQuery()
        ->getOneOrNullResult();
    }

    /**
     * Find a Trick entity with query based on its uuid.
     *
     * @param UuidInterface $uuid
     *
     * @return User|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByUuid(UuidInterface $uuid) : ?User
    {
        return $this->createQueryBuilder('u')
        ->where('u.uuid = :uuid')
        ->setParameter('uuid', $uuid->getBytes())
        ->getQuery()
        ->getOneOrNullResult();
    }

    /**
     * {@inheritdoc}
     *
     * Load a user finding him with his nickname or email.
     *
     * @param string $username
     *
     * @return User|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function loadUserByUsername($username) : ?User
    {
        // Avoid unexpected case sensitive SQL collation to compare lowercase stored email
        $isEmail = preg_match('/@/', $username);
        $username = $isEmail ? strtolower($username) : $username;
        return $this->createQueryBuilder('u')
            ->where('u.nickName = :query OR u.email = :query')
            ->setParameter('query', $username)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
