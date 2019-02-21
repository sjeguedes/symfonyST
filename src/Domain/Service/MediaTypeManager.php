<?php

declare(strict_types = 1);

namespace App\Domain\Service;

use App\Domain\Entity\MediaType;
use App\Domain\Repository\MediaTypeRepository;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class MediaTypeManager.
 *
 * Manage media types to retrieve as a "service layer".
 */
class MediaTypeManager
{
    use LoggerAwareTrait;

    /**
     * @var MediaTypeRepository
     */
    private $repository;

    /**
     * MediaTypeManager constructor.
     *
     * @param MediaTypeRepository $repository
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function __construct(MediaTypeRepository $repository, LoggerInterface $logger)
    {
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
     * Get mandatory default types.
     *
     * @return array
     */
    public function getMandatoryDefaultTypes() : array
    {
        return MediaType::TYPE_CHOICES;
    }
}