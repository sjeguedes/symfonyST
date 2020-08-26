<?php

declare(strict_types = 1);

namespace App\Domain\Repository;

use App\Domain\Entity\Comment;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class CommentRepository.
 *
 * Manage Comment entity data in database.
 */
class CommentRepository extends ServiceEntityRepository
{
    /**
     * CommentRepository constructor.
     *
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Find comments expected data with query based on their associated trick uuid.
     *
     * @param UuidInterface $trickUuid
     *
     * @return mixed
     */
    public function findAllByTrick(UuidInterface $trickUuid) : array
    {
        $queryBuilder = $this->createQueryBuilder('c');
        return $queryBuilder
            // IMPORTANT! This retrieves expected data correctly!
            ->select('c.uuid, c.creationDate')
            ->join('c.trick', 't', 'WITH', 'c.trick = t.uuid')
            ->where('t.uuid = ?1')
            ->orderBy('c.creationDate', 'ASC')
            ->setParameter(1, $trickUuid->getBytes())
            ->getQuery()
            ->getResult();
    }
}
