<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\MediaType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * Class MediaTypeRepository.
 *
 * Manage MediaType entity data in database.
 */
class MediaTypeRepository extends ServiceEntityRepository
{
    /**
     * MediaTypeRepository constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MediaType::class);
    }

    /**
     * Find a MediaType entity with query based on its type.
     *
     * @param string $type
     *
     * @return MediaType|null
     */
    public function findOneByType(string $type): ?MediaType
    {
        $queryBuilder = $this->createQueryBuilder('mt');
        $result = $queryBuilder
            ->select(['mt'])
            ->where('mt.type = ?1')
            ->setParameter(1,  $type)
            ->getQuery()
            ->getResult()
        ;
        if(empty($result)) {
            return null;
        }
        return $result[0];
    }
}
