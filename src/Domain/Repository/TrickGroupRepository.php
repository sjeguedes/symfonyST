<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\TrickGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Ramsey\Uuid\UuidInterface;
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
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, TrickGroup::class);
    }

    /**
     * Find a TrickGroup entity with query based on its uuid.
     *
     * @param UuidInterface $uuid
     *
     * @return TrickGroup|null
     *
     * @see: binary string to store uuid
     * https://mysqlserverteam.com/storing-uuid-values-in-mysql-tables/
     * @see: convert hex to bin and bin to hex
     * https://stackoverflow.com/questions/28251144/inserting-and-selecting-uuids-as-binary16
     * @see: binary string shortcut
     * https://docs.benramsey.com/ramsey-uuid/3.7.3/Ramsey/Uuid/Uuid.html#method_getBytes
     * @see: use of string codec instead of uuid:
     * https://github.com/ramsey/uuid/blob/29fb62b48611761b4c0c4e8f4a428cad19c2b690/src/Codec/StringCodec.php#L61-L100
     */
    public function findOneByUuid(UuidInterface $uuid): ?TrickGroup
    {
        $queryBuilder = $this->createQueryBuilder('tg' );
        $result = $queryBuilder
            ->select('tg')
            ->where('tg.uuid = ?1')
            ->setParameter(1, $uuid->getBytes())
            ->getQuery()
            ->getResult()
        ;
        if(empty($result)) {
            return null;
        }
        return $result[0];
    }
}
