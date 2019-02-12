<?php
declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

/**
 * Class MediaRepository.
 *
 * Manage Media entity data in database.
 */
class MediaRepository extends ServiceEntityRepository
{
    /**
     * MediaRepository constructor.
     *
     * @param RegistryInterface $registry
     *
     * @return void
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, Media::class);
    }
}
