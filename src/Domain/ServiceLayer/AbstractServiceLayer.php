<?php

declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;

/**
 * Class AbstractServiceLayer.
 *
 * Define Entity "service layers" essential responsibilities.
 *
 * For information, for entity persistence management, a better way with Doctrine transactions:
 * @link https://www.thinktocode.com/2019/03/28/abstracting-the-doctrine-orm-flush/
 */
abstract class AbstractServiceLayer
{
    use LoggerAwareTrait;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * AbstractServiceLayer constructor.
     *
     * @param EntityManagerInterface $entityManager
     * @param LoggerInterface        $logger
     */
    public function __construct(
        EntityManagerInterface $entityManager,
        LoggerInterface $logger
    )
    {
        $this->entityManager = $entityManager;
        $this->setLogger($logger);
    }

    /**
     * Add (persist) and possibly save (flush) selected entity in database.
     *
     * * Please note combinations:
     * - $isPersisted = false, $isFlushed = false means selected entity must be instantiated only.
     * - $isPersisted = true, $isFlushed = true means selected entity is added to unit of work and saved in database.
     * - $isPersisted = true, $isFlushed = false means selected entity is added to unit of work only.
     * - $isPersisted = false, $isFlushed = true means selected entity is saved in database only with possible change(s) in unit of work.
     *
     * @param object $entity
     * @param bool   $isPersisted
     * @param bool   $isFlushed
     *
     * @see https://www.thinktocode.com/2019/01/24/doctrine-repositories-should-be-collections-without-flush/
     * @see https://stackoverflow.com/questions/51761129/entitymanager-is-closed-do-i-have-to-check-all-before
     *
     * @return object|null
     */
    public function addAndSaveNewEntity(object $entity, bool $isPersisted = false, bool $isFlushed = false) : ?object
    {
        // Nothing to do!
        if (!$isPersisted && !$isFlushed) {
            return $entity;
        }
        // Create a new entity manager instance, if previous has detached managed entities with close() method!
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = $this->entityManager->create(
                $this->entityManager->getConnection(),
                $this->entityManager->getConfiguration()
            );
        }
        try {
            // Must persist data in unit of work only
            if ($isPersisted && !$isFlushed) {
                $this->entityManager->persist($entity);
            // Save data and possibly associated entities data in database only
            } elseif (!$isPersisted && $isFlushed) {
                $this->entityManager->flush();
            // both operations
            } else {
                $this->entityManager->persist($entity);
                $this->entityManager->flush();
            }
        } catch (\Exception $exception) {
            dd($exception->getMessage());
            return null;
        }
        return $entity;
    }

    /**
     * Delete (remove) and possible save (flush) removal on selected entity in database.
     *
     * * Please note combination:
     * - $isFlushed = true means selected entity is saved in database only with possible change(s) in unit of work.
     *
     * @param object $entity
     * @param bool   $isFlushed
     *
     * @see https://www.thinktocode.com/2019/01/24/doctrine-repositories-should-be-collections-without-flush/
     * @see https://stackoverflow.com/questions/51761129/entitymanager-is-closed-do-i-have-to-check-all-before
     * @see https://stackoverflow.com/questions/23459470/detached-entity-error-in-doctrine
     *
     * @return bool
     */
    public function removeAndSaveNoMoreEntity(object $entity, bool $isFlushed = true) : bool
    {
        // Create a new entity manager instance, if previous has detached managed entities with close() method!
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = $this->entityManager->create(
                $this->entityManager->getConnection(),
                $this->entityManager->getConfiguration()
            );
        }
        // Manage entity removal only if its is attached to unit of work!
        try {
            $mergedEntity = $entity;
            if (!$this->entityManager->contains($entity)) {
                // Try to find entity
                $mergedEntity = $this->entityManager->getRepository(\get_class($entity))
                                                    ->findOneBy(['uuid' => $entity->getUuid()]);
            }
            // Prepare removal in database (must be completed with flush action later)
            // by taking into account cascade option on relationships.
            if (!\is_null($mergedEntity)) {
                $this->entityManager->remove($mergedEntity);
                // Proceed to removal in database if global flush action is set!
                if ($isFlushed) {
                    $this->entityManager->flush();
                }
            }
            // Will return true even if nothing is done (Entity was possibly never referenced in unit of work!)
            $isRemoved = true;
        } catch (\Exception $exception) {
            $isRemoved = false;
        }
        return $isRemoved;
    }
}
