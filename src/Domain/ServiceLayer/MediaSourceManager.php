<?php

declare(strict_types=1);

namespace App\Domain\ServiceLayer;

use App\Domain\Entity\MediaSource;
use App\Domain\Repository\MediaSourceRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class MediaSourceManager.
 *
 * Manage media sources to handle, and retrieve as a "service layer".
 */
class MediaSourceManager extends AbstractServiceLayer
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var MediaSourceRepository
     */
    private $repository;

    /**
     * MediaSourceManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param MediaSourceRepository   $repository
     * @param LoggerInterface        $logger
     */
    public function __construct(EntityManagerInterface $entityManager, MediaSourceRepository $repository, LoggerInterface $logger)
    {
        parent::__construct($entityManager, $logger);
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->setLogger($logger);
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
     * Get MediaSource entity repository.
     *
     * @return MediaSourceRepository
     */
    public function getRepository(): MediaSourceRepository
    {
        return $this->repository;
    }

    /**
     * Create a MediaSource instance.
     *
     * @param object $source
     * @param bool   $isPersisted
     * @param bool   $isFlushed
     *
     * @return object|MediaSource|null
     *
     * @throws \Exception
     */
    public function createMediaSource(object $source, bool $isPersisted = false, bool $isFlushed = false): ?object
    {
        $newMediaSource = new MediaSource($source);
        $source->assignMediaSource($newMediaSource);
        $newMediaSource = $this->addAndSaveNewEntity($newMediaSource, $isPersisted, $isFlushed);
        return $newMediaSource;
    }

    /**
     * Remove a media source and all associated entities depending on cascade operations.
     *
     * @param MediaSource $mediaSource
     * @param bool        $isFlushed
     *
     * @return bool
     */
    public function removeMediaSource(MediaSource $mediaSource, bool $isFlushed = true): bool
    {
        // Proceed to removal in database
        return $this->removeAndSaveNoMoreEntity($mediaSource, $isFlushed);
    }
}
