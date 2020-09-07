<?php

declare(strict_types=1);

namespace App\Domain\ServiceLayer;

use App\Domain\Entity\MediaType;
use App\Domain\Repository\MediaTypeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class MediaTypeManager.
 *
 * Manage media types to handle, and retrieve as a "service layer".
 */
class MediaTypeManager
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    private $entityManager;

    /**
     * @var MediaTypeRepository
     */
    private $repository;

    /**
     * MediaTypeManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param MediaTypeRepository    $repository
     * @param LoggerInterface        $logger
     *
     * @return void
     */
    public function __construct(EntityManagerInterface $entityManager, MediaTypeRepository $repository, LoggerInterface $logger)
    {
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->setLogger($logger);
    }

    /**
     * Find MediaType by type.
     *
     * @param string $type
     *
     * @return MediaType|null
     */
    public function findSingleByUniqueType(string $type): ?MediaType
    {
        return $this->repository->findOneByType($type);
    }

    /**
     * Get entity manager.
     *
     * @return EntityManagerInterface
     */
    public function getEntityManager(): EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Get mandatory default types.
     *
     * @return array
     */
    public function getMandatoryDefaultTypes(): array
    {
        return MediaType::TYPE_CHOICES;
    }

    /**
     * Get MediaType entity repository.
     *
     * @return MediaTypeRepository
     */
    public function getRepository(): MediaTypeRepository
    {
        return $this->repository;
    }

    /**
     * Get a particular media type based on its key.
     *
     * @param string $mediaTypeKey
     *
     * @return string|null
     */
    public function getType(string $mediaTypeKey): ?string
    {
        $type = $this->getMandatoryDefaultTypes()[$mediaTypeKey] ?? null;
        return $type;
    }
}
