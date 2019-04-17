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
     *
     * @return void
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Image::class);
    }
}
