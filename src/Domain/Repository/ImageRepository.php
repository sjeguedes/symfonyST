<?php

declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Doctrine\Persistence\ManagerRegistry;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;

/**
 * Class ImageRepository.
 *
 * Manage Image entity data in database.
 */
class ImageRepository extends ServiceEntityRepository
{
    /**
     * ImageRepository constructor.
     *
     * @param ManagerRegistry $registry
     */
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Image::class);
    }

    /**
     * Find a Trick entity with query based on its uuid.
     *
     * @param UuidInterface $uuid
     *
     * @return Image|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByUuid(UuidInterface $uuid): ?Image
    {
        return $this->createQueryBuilder('i')
            ->where('i.uuid = :uuid')
            ->setParameter('uuid', $uuid->getBytes())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find many Image entities uuid, source type (image or video at this time) and format (extension)
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
        $results = $this->createQueryBuilder('i')
            ->select( 'i.uuid, i.name, i.format, mt.sourceType')
            ->leftJoin('i.mediaSource', 'ms', 'WITH', 'i.uuid = ms.image')
            ->leftJoin('ms.media', 'm', 'WITH', 'ms.uuid = m.mediaSource')
            ->leftJoin('m.mediaType', 'mt', 'WITH', 'm.mediaType = mt.uuid')
            ->where('i.name IN (:names)')
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
                // Store images uuid, source type and format in new formatted results
                $imageExtension = $results[$i]['format'];
                $results[$newKey] = ['sourceType' => $sourceType, 'uuid' => $uuidValue, 'format' => $imageExtension];
                unset($results[$i]);
            }
        }
        return $results;
    }
}
