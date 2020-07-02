<?php

declare(strict_types = 1);

namespace App\Domain\Repository;

use App\Domain\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Connection;
use Ramsey\Uuid\Uuid;
use Ramsey\Uuid\UuidInterface;
use Symfony\Bridge\Doctrine\RegistryInterface;

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
     * @param RegistryInterface $registry
     */
    public function __construct(RegistryInterface $registry)
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
    public function findOneByUuid(UuidInterface $uuid) : ?Image
    {
        return $this->createQueryBuilder('i')
            ->where('i.uuid = :uuid')
            ->setParameter('uuid', $uuid->getBytes())
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * Find many Image entities uuid with query based on their name.
     *
     * @link https://stackoverflow.com/questions/5929036/how-to-use-where-in-with-doctrine-2
     *
     * @param array $names
     *
     * @return array|null
     */
    public function findManyUuidByNames(array $names) : ?array
    {
        $results = $this->createQueryBuilder('i')
            ->select('i.uuid, i.name')
            ->where('i.name IN (:names)')
            ->setParameter('names', $names, Connection::PARAM_STR_ARRAY)
            ->getQuery()
            ->getScalarResult();
        // Get uuid string values instead of binary string values
        if (!\is_null($results)) {
            for ($i = 0; $i < \count($results); $i ++) {
                // Redefine $key and value for each iteration and delete previous entry
                $newKey = $results[$i]['name'];
                $newValue = Uuid::fromBytes($results[$i]['uuid'])->toString();
                $results[$newKey] = $newValue;
                unset($results[$i]);
            }
        }
        return $results;
    }
}
