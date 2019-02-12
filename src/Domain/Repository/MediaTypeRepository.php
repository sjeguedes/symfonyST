<?php
declare(strict_types=1);

namespace App\Domain\Repository;

use App\Domain\Entity\MediaType;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Symfony\Bridge\Doctrine\RegistryInterface;

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
     * @param RegistryInterface $registry
     *
     * @return void
     */
    public function __construct(RegistryInterface $registry)
    {
        parent::__construct($registry, MediaType::class);
    }
}
