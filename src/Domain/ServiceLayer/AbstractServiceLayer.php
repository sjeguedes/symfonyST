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
 */
abstract  class AbstractServiceLayer
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
     * Add (persist) and possibly save selected entity in database.
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
        // Create a new entity manager instance, if previous has detach managed entities with close() method!
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = $this->entityManager->create(
                $this->entityManager->getConnection(),
                $this->entityManager->getConfiguration()
            );
        }
        // Nothing to do!
        if (!$isPersisted && !$isFlushed) {
            return $entity;
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
            return null;
        }
        return $entity;
    }

    /**
     * Delete (remove) and possible save removal on selected entity in database.
     *
     * * Please note combination:
     * - $isFlushed = true means selected entity is saved in database only with possible change(s) in unit of work.
     *
     * @param object $entity
     * @param bool   $isFlushed
     *
     * @see https://www.thinktocode.com/2019/01/24/doctrine-repositories-should-be-collections-without-flush/
     * @see https://stackoverflow.com/questions/51761129/entitymanager-is-closed-do-i-have-to-check-all-before
     *
     * @return bool
     */
    public function removeAndSaveNoMoreEntity(object $entity, bool $isFlushed = true) : bool
    {
        // Create a new entity manager instance, if previous has detach managed entities with close() method!
        if (!$this->entityManager->isOpen()) {
            $this->entityManager = $this->entityManager->create(
                $this->entityManager->getConnection(),
                $this->entityManager->getConfiguration()
            );
        }
        try {
            $this->entityManager->remove($entity);
            //  Proceed to removal in database
            if ($isFlushed) {
                $this->entityManager->flush();
            }
        } catch (\Exception $exception) {
            return false;
        }
        return true;
    }
}
