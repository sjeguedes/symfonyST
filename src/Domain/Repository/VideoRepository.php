<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Video;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;

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
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Video::class);
    }

    /**
     * Find many Video entities uuid, source type (image or video at this time)
     * with one query based on their names.
     *
     * @see https://stackoverflow.com/questions/5929036/how-to-use-where-in-with-doctrine-2
     *
     * @param array $names
     *
     * @return array|null
     */
    public function findManyToShowInFomByNames(array $names): ?array
    {
        $results = $this->createQueryBuilder('v')
            ->select( 'v.uuid, v.name, mt.sourceType')
            ->leftJoin('v.mediaSource', 'ms', 'WITH', 'v.uuid = ms.video')
            ->leftJoin('ms.media', 'm', 'WITH', 'ms.uuid = m.mediaSource')
            ->leftJoin('m.mediaType', 'mt', 'WITH', 'm.mediaType = mt.uuid')
            ->where('v.name IN (:names)')
            ->setParameter('names', $names, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->getScalarResult();
        if (!\is_null($results)) {
            for ($i = 0; $i < \count($results); $i++) {
                // Redefine $key and value for each iteration and delete previous entry
                $newKey = $results[$i]['name'];
                $sourceType = $results[$i]['sourceType'];
                // Get uuid string values instead of binary string values
                $uuidValue = Uuid::fromBytes($results[$i]['uuid'])->toString();
                // Store images uuid and source type in new formatted results
                $results[$newKey] = ['sourceType' => $sourceType, 'uuid' => $uuidValue];
                unset($results[$i]);
            }
        }
        return $results;
    }
}
