<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\Entity\MediaOwner;
use App\Domain\Repository\MediaOwnerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class MediaOwnerManager.
 *
 * Manage media owners to handle, and retrieve as a "service layer".
 */
class MediaOwnerManager extends AbstractServiceLayer
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var MediaOwnerRepository
     */
    private $repository;

    /**
     * MediaOwnerManager constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param MediaOwnerRepository   $repository
     * @param LoggerInterface        $logger
     */
    public function __construct(EntityManagerInterface $entityManager, MediaOwnerRepository $repository, LoggerInterface $logger)
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
    public function getEntityManager() : EntityManagerInterface
    {
        return $this->entityManager;
    }

    /**
     * Get MediaOwner entity repository.
     *
     * @return MediaOwnerRepository
     */
    public function getRepository() : MediaOwnerRepository
    {
        return $this->repository;
    }

    /**
     * Create a MediaOwner instance.
     *
     * @param object $owner
     * @param bool   $isPersisted
     * @param bool   $isFlushed
     *
     * @return object|MediaOwner|null
     *
     * @throws \Exception
     */
    public function createMediaOwner(object $owner, bool $isPersisted = false, bool $isFlushed = false) : ?object
    {
        // Bind associated MediaOwner entity if it is expected to ensure correct persistence!
        // This is needed without individual persistence by using cascade option.
        $newMediaOwner = new MediaOwner($owner);
        $owner->assignMediaOwner($newMediaOwner);
        $newMediaOwner = $this->addAndSaveNewEntity($newMediaOwner, $isPersisted, $isFlushed);
        return $newMediaOwner;
    }

    /**
     * Remove a media owner and all associated entities depending on cascade operations.
     *
     * @param MediaOwner $mediaOwner
     * @param bool       $isFlushed
     *
     * @return bool
     */
    public function removeMediaOwner(MediaOwner $mediaOwner, bool $isFlushed = true) : bool
    {
        // Proceed to removal in database
        if (0 === count($mediaOwner->getMedias())) {
            return $this->removeAndSaveNoMoreEntity($mediaOwner, $isFlushed);
        }
        return false;
    }
}
