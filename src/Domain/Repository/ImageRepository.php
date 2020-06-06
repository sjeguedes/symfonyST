<?php

declare(strict_types = 1);

namespace App\Domain\Repository;

use App\Domain\Entity\Image;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
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
     * Find a Image entity with query based on its name.
     *
     * @param string $name
     *
     * @return Image|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findOneByName(string $name) : ?Image
    {
        return $this->createQueryBuilder('i')
            ->where('i.name = :name')
            ->setParameter('name', $name)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
