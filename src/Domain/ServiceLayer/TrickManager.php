<?php
declare(strict_types=1);

namespace App\Domain\ServiceLayer;

use App\Domain\DTO\CreateTrickDTO;
use App\Domain\DTO\UpdateTrickDTO;
use App\Domain\Entity\Trick;
use App\Domain\Entity\User;
use App\Domain\Repository\TrickRepository;
use App\Service\Event\CustomEventFactoryInterface;
use App\Utils\Traits\RouterHelperTrait;
use App\Utils\Traits\SessionHelperTrait;
use App\Utils\Traits\StringHelperTrait;
use App\Utils\Traits\UuidHelperTrait;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\User\UserInterface;

/**
 * Class TrickManager.
 *
 * Manage tricks to handle, and retrieve them as a "service layer".
 */
class TrickManager extends AbstractServiceLayer
{
    use LoggerAwareTrait;
    use RouterHelperTrait;
    use SessionHelperTrait;
    use StringHelperTrait;
    use UuidHelperTrait;

    /**
     * Define a session key name to store current trick total count.
     */
    const TRICK_COUNT_SESSION_KEY = 'trickCount';

    /**
     * @var CustomEventFactoryInterface
     */
    private $customEventFactory;

    /**
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * @var TrickRepository
     */
    private $repository;

    /**
     * TrickManager constructor.
     *
     * @param CustomEventFactoryInterface $customEventFactory
     * @param EntityManagerInterface      $entityManager
     * @param TrickRepository             $repository
     * @param LoggerInterface             $logger
     * @param RouterInterface             $router
     * @param SessionInterface            $session
     *
     * @return void
     */
    public function __construct(
        CustomEventFactoryInterface $customEventFactory,
        EntityManagerInterface $entityManager,
        TrickRepository $repository,
        LoggerInterface $logger,
        RouterInterface $router,
        SessionInterface $session
    ) {
        $this->customEventFactory = $customEventFactory;
        parent::__construct($entityManager, $logger);
        $this->entityManager = $entityManager;
        $this->repository = $repository;
        $this->setLogger($logger);
        $this->setRouter($router);
        $this->setSession($session);
     }

    /**
     * Adapt Trick list parameters depending on particular conditions to adjust values for showing process
     * when it's possible only if no error is detected with incoherent passed values.
     *
     * @param array $condition  an array which must contain only 1 value which is a true condition name
     * @param array $parameters
     *
     * @return array
     */
    private function adaptTrickListParameters(array $condition, array $parameters): array
    {
        if (\count($condition) > 1) {
            throw new \RuntimeException("Condition can't be checked: only one condition must be passed at once!");
        }
        if (!empty($condition)) {
            switch ($condition) {
                // Error: check if $offset or $limit values are wrong:
                // so reset list to default parameters for descending order
                case \array_key_exists('descendingOrderAndWrongParameters', $condition):
                    $parameters['offset'] = $parameters['maxOffset'] + 1 - $this->getTrickListConfigParameters()['numberPerLoading'];
                    $parameters['limit'] = $this->getTrickListConfigParameters()['numberPerLoading'];
                    $parameters['error'] = true;
                    break;
                // Error: check if $offset or $limit values are wrong:
                // so reset list to default parameters for ascending order
                case \array_key_exists('ascendingOrderAndWrongParameters', $condition):
                    $parameters['offset'] = 0;
                    $parameters['limit'] = $this->getTrickListConfigParameters()['numberPerLoading'];
                    $parameters['error'] = true;
                    break;
                // Particular case: check if calculated $offset is not under $minOffset:
                // lowest $offset must be 0 for descending order
                case \array_key_exists('descendingOrderUnderMinimumOffset', $condition):
                    $modulo =  $parameters['countAll'] % $this->getTrickListConfigParameters()['numberPerLoading'];
                    $parameters['offset'] = $parameters['minOffset'];
                    $parameters['limit'] = 0 == $modulo ? 1 : $modulo; // recalculate $limit to show last tricks
                    $parameters['error'] = false;
                    break;
                // Particular case: check if calculated $offset + $limit is not over $maxOffset:
                // highest offset must be equal to (total count - 1) for ascending order
                case \array_key_exists('ascendingOrderOverMaxOffset', $condition):
                    $parameters['limit'] = $parameters['maxOffset'] + 1 - $parameters['offset']; // recalculate $limit to show last tricks
                    $parameters['error'] = false;
                    break;
            }
            $this->logger->error(
                sprintf("[trace app SnowTricks] TrickManager/filterParametersWithOrder - \"%s\" case => parameters: %s", key($condition), serialize($parameters))
            );
        }
        return $parameters;
    }

    /**
     * Add (persist if necessary) and save (flush if necessary) a (new) Trick entity in database.
     *
     * Please note combinations:
     * - $isPersisted = false, bool $isFlushed = false means Trick entity must be instantiated only.
     * - $isPersisted = true, bool $isFlushed = true means Trick entity is added to unit of work and saved in database.
     * - $isPersisted = true, bool $isFlushed = false means Trick entity is added to unit of work only.
     * - $isPersisted = false, bool $isFlushed = true means Trick entity is saved in database only with possible change(s) in unit of work.
     *
     * @param Trick $trick
     * @param bool  $isPersisted
     * @param bool  $isFlushed
     *
     * @return Trick|null
     *
     * @throws \Exception
     */
    public function addAndSaveTrick(Trick $trick, bool $isPersisted = false, bool $isFlushed = false): ?Trick
    {
        $object = $this->addAndSaveNewEntity($trick, $isPersisted, $isFlushed);
        return \is_null($object) ? null : $trick;
    }

    /**
     * Check if a form submitted trick name is the same or seems to be similar when compared.
     *
     * Please note this submitted name can be compared to all existing tricks names excepted current trick name,
     * or in the other hand to current trick (to update) name only.
     *
     * @param string $submittedName
     * @param Trick  $trick|null       a trick to update or null if it is a creation
     * @param bool   $isTrickChecked
     *
     * @return bool
     */
    public function checkSameOrSimilarTrickName(
        string $submittedName,
        Trick $trick = null,
        bool $isTrickChecked = false
    ): bool {
        // Prepare data to filter for trick creation by getting all tricks
        if (\is_null($trick)) {
            $dataToFilter = $this->getRepository()->findAll();
            // Prepare data to filter for trick update by excluding trick to update or checking it only
        } else {
            $dataToFilter = !$isTrickChecked
                ? $this->findOthersByExcludedUuid($trick->getUuid())
                : [$trick];
        }
        $cleanSubmittedName = preg_replace('/[\s_-]+/', '', $submittedName);
        // Filter comparison results with data
        $results = array_filter($dataToFilter, function ($item) use ($cleanSubmittedName) {
            /** @var Trick $item */
            $cleanExistingName = preg_replace('/[\s_-]+/', '', $item->getName());
            // Compare both cleaned names on each side with insensitive case
            if (strtolower($cleanExistingName) === strtolower($cleanSubmittedName)) {
                return true;
            }
            return false;
        });
        return empty($results) ? false : true;
    }

    /**
     * Count all tricks without filter.
     *
     * @return int
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     * @throws \UnexpectedValueException
     */
    public function countAll(): int
    {
        $result = $this->repository->countAll();
        if (\is_null($result)) {
            throw new \UnexpectedValueException('Trick total count error: list cannot be generated!');
        }
        return $result;
    }

    /**
     * Create an event related to trick and dispatch it.
     *
     * An auto configured User subscriber listens to that kind of event.
     *
     * @param string $eventContext
     * @param Trick  $trick
     * @param User   $authenticatedUser
     *
     * @return void
     *
     * @throws \Exception
     */
    public function createAndDispatchTrickEvent(string $eventContext, Trick $trick, User $authenticatedUser): void
    {
        $event = $this->customEventFactory->createFromContext(
            $eventContext,
            ['user' => $authenticatedUser, 'trick' => $trick]
        );
        if (\is_null($event)) {
            throw new \Exception('Event was not created due to wrong parameters!');
        }
        $eventName = $this->customEventFactory->getEventNameByContext($eventContext);
        /** @var EventDispatcherInterface $eventDispatcher */
        $eventDispatcher = $this->customEventFactory->getEventDispatcher();
        $eventDispatcher->dispatch($event, $eventName);
    }

    /**
     * Create a Trick entity with necessary data.
     *
     * @param CreateTrickDTO      $createTrickDTO
     * @param User|UserInterface  $authenticatedUser
     * @param bool                $isPersisted
     * @param bool                $isFlushed
     *
     * @return Trick
     *
     * @throws \Exception
     */
    public function createTrick(
        CreateTrickDTO $createTrickDTO,
        UserInterface $authenticatedUser,
        bool $isPersisted = false,
        bool $isFlushed = false
    ): ?Trick {
        $newTrick = new Trick(
            $createTrickDTO->getGroup(), // At this time an array of TrickGroup is returned to possibly manage several "categories".
            $authenticatedUser,
            $createTrickDTO->getName(),
            $createTrickDTO->getDescription(),
            $this->makeSlug($createTrickDTO->getName()), // At this time slug is not customized in form, so create it with trick name.
            $createTrickDTO->getIsPublished()
        );
        // Save data in database
        return $this->addAndSaveTrick($newTrick, $isPersisted, $isFlushed); // null or the entity
    }

    /**
     * Find all created tricks necessary data associated to a particular user author based on his uuid.
     *
     * Please note this is used to generate links to tricks update form.
     *
     * @param UuidInterface $userUuid
     *
     * @return array
     */
    public function findOnesByAuthor(UuidInterface $userUuid): array
    {
        return $this->repository->findAllByAuthor($userUuid);
    }

    /**
     * Find all other tricks necessary data by excluding a particular trick based on its uuid.
     *
     * @param UuidInterface $uuid
     *
     * @return mixed
     */
    public function findOthersByExcludedUuid(UuidInterface $uuid)
    {
        return $this->repository->findOthersByExcludedUuid($uuid);
    }

    /**
     * Find Trick by name string.
     *
     * @param string $name
     *
     * @return Trick|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findSingleByName(string $name): ?Trick
    {
        return $this->repository->findOneByName($name);
    }

    /**
     * Find Trick to show on single page by encoded uuid string.
     *
     * @param string $encodedUuid
     *
     * @return Trick|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findSingleToShowByEncodedUuid(string $encodedUuid): ?Trick
    {
        $uuid = $this->decode($encodedUuid);
        return $this->repository->findOneToShowByUuid($uuid);
    }

    /**
     * Find Trick to update in form by encoded uuid string.
     *
     * @param string $encodedUuid
     *
     * @return Trick|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function findSingleToUpdateInFormByEncodedUuid(string $encodedUuid): ?Trick
    {
        $uuid = $this->decode($encodedUuid);
        return $this->repository->findOneToUpdateInFormByUuid($uuid);
    }

    /**
     * Get default trick list.
     *
     * This is used for first load for instance.
     *
     * @return array
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getTrickListParameters(): array
    {
        $startOffset = $this->getStartOffset();
        $parameters = $this->filterParametersWithOrder($startOffset);
        return $parameters;
    }

    /**
     * Get filtered trick list depending on parameters.
     *
     * @param int $offset
     * @param int      $limit
     * @param string   $order
     *
     * @return array|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getFilteredList(
        int $offset, // can be adjusted to be coherent
        int $limit = Trick::TRICK_NUMBER_PER_LOADING, // can be updated after first load to be coherent
        string $order = Trick::TRICK_LOADING_MODE
    ): ?array {
        // Define init value to define starting rank in SQL query:
        //So in ASC order, first assigned rank in SQL query will start at "0", and in DESC order it will start at $this->countAll() - 1
        $init = ('DESC' === $order) ? $this->countAll() : -1;
        // Offset starts at 0 (i.e. the 15th Trick rank has a value of 14).
        $start = $offset;
        $end = $offset + $limit;
        return $this->repository->findByLimitOffsetWithOrder($order, $init, $start, $end);
    }

    /**
     * Get default parameters to show a trick list.
     *
     * (e.g. sort direction, trick number for "load more", ...)
     *
     * @return array
     */
    public function getTrickListConfigParameters(): array
    {
        return [
            'loadingMode'      => Trick::TRICK_LOADING_MODE,
            'numberPerLoading' => Trick::TRICK_NUMBER_PER_LOADING,
            'numberPerPage'    => Trick::TRICK_NUMBER_PER_PAGE
        ];
    }

    /**
     * Get pagination parameters to manage page links.
     *
     * This is used on complete trick list access page "tricks".
     *
     * @param int $pageIndex
     *
     * @return array|null
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getTrickListPaginationParameters(int $pageIndex): ?array
    {
        // Count all tricks
        $countAll = $this->countAll();
        $listDefaultParameters = $this->getTrickListConfigParameters();
        $loadingMode = $listDefaultParameters['loadingMode'];
        $trickNumberPerPage = $listDefaultParameters['numberPerPage'];
        $trickNumberToShowModulo = $countAll % $trickNumberPerPage;
        $trickNumberOnLastPage = 0 === $trickNumberToShowModulo ? 1 : $trickNumberToShowModulo;
        $calculatedPageCount = $countAll / $trickNumberPerPage;
        $pageCount = (0 === $trickNumberToShowModulo) ? $calculatedPageCount : (int) floor($calculatedPageCount) + 1;
        // Page doesn't exist and obviously cannot be reached! This will throw a not found exception.
        if ($pageIndex <= 0 || $pageIndex > $pageCount) {
            return null;
        }
        // Adjust offset and limit value depending on loading mode
        if ('DESC' === $loadingMode) {
            // Define offset
            $offset = ($countAll - $pageIndex * $trickNumberPerPage < 0) ? 0 : $countAll - $pageIndex * $trickNumberPerPage;
             // Check if last page is reached (thanks to offset value under min value) or not to adjust limit!
            $isLastPage = 0 === $offset;
            $limit = $isLastPage ? $trickNumberOnLastPage : $trickNumberPerPage;
        } else {
            // Define offset
            $offset = (1 === $pageIndex) ? 0 : ($pageIndex - 1) * $trickNumberPerPage;
            // Check if last page is reached (thanks to offset value over max offset value) or not to adjust limit!
            $isLastPage = 0 === $offset + $trickNumberPerPage > $countAll - 1;
            $limit = $isLastPage ? $trickNumberOnLastPage : $trickNumberPerPage;
        }
        // Particular case for both cases: show all tricks directly if $countAll === $trickNumberPerPage
        if ($countAll === $trickNumberPerPage) {
            // Adjust limit again!
            $limit = $trickNumberPerPage;
        }
        return [
            'currentPage'   => $pageIndex,
            'currentOffset' => $offset,
            'currentLimit'  => $limit,
            'pageCount'     => $pageCount,
            'loadingMode'   => $loadingMode,
            'trickCount'    => $countAll
        ];
    }

    /**
     * Define start offset accordingly to sort direction.
     *
     * @return int
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getStartOffset(): int
    {
        // Get tricks total number
        $countAll = $this->countAll();
        $startOffset = ('DESC' === $this->getTrickListConfigParameters()['loadingMode'])
            ? $countAll - $this->getTrickListConfigParameters()['numberPerLoading'] : 0;
        return $startOffset;
    }

    /**
     * Filter allowed $offset and $limit values depending on sort direction,
     * and redefine them if it is possible, to avoid issues.
     *
     * @param int $offset
     * @param int $limit
     * @param string $order
     *
     * @return array
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
      */
    public function filterParametersWithOrder(
        int $offset,
        int $limit = Trick::TRICK_NUMBER_PER_LOADING,
        string $order = Trick::TRICK_LOADING_MODE
    ): array {
        $count = $this->repository->countAll();
        $minOffset = 0;
        $maxOffset = $count - 1;
        // Check a valid start offset for both modes
        // In "DESC" mode, the lowest valid offset value is: -$limit
        // In "ASC" mode, the highest valid offset value is: $maxOffset + $limit + 1
        // This is the client side version to declare it in the same way but it can be simplified on server side by:
        //$validOffset = ($offset >= $minOffset) && ($offset < $maxOffset + 1);
        $validOffset = ($offset >= -$limit) && ($offset < $maxOffset + $limit + 1);
        // Check a valid limit for both modes
        $validLimit = ($limit >= 1); // && ($limit < $maxOffset + 1); will throw an exception by defining a limit max value
        // Check correctly defined order
        $validOrder = ('ASC' === $order) || ('DESC' === $order);
        // Define parameters some of whom can be adjusted!
        $parameters = [
            'order'     => $order,
            'countAll'  => $count,
            'minOffset' => $minOffset,
            'maxOffset' => $maxOffset,
            'offset'    => $offset,
            'limit'     => $limit,
            'error'     => false
        ];
        // Define evaluated conditions
        $conditions = [
            'descendingOrderAndWrongParameters' => 'DESC' === $order && (!$validOffset || !$validLimit),
            'ascendingOrderAndWrongParameters'  => 'ASC'  === $order && (!$validOffset || !$validLimit),
            'descendingOrderUnderMinimumOffset' => 'DESC' === $order && ($offset < $minOffset),
            'ascendingOrderOverMaxOffset'       => 'ASC'  === $order && ($offset + $limit > $maxOffset)
        ];
        // Filter the condition evaluated to true
        if (!$validOrder) {
            // Reset list to descending order initial state by default if $order is wrong.
            $condition = ['descendingOrderAndWrongParameters' => true];
        } else {
            $condition = array_filter($conditions, function ($value) {
                return true === $value;
            });
        }
        // Update parameters by possibly bubbling up error
        $parameters = $this->adaptTrickListParameters($condition, $parameters);
        return $parameters;
    }

    /*
     * Filter complete trick list pagination request attribute.
     *
     * @param Request $request
     * @param string  $route
     *
     * @return int
     *
     * @throws \Exception
     */
    public function filterPaginationRequestAttribute(Request $request): int
    {
        // Prevent issue if "page" attribute has no default value defined (no redirection is made)
        $page = \is_null($request->attributes->get('page')) ? '' : $request->attributes->get('page');
        // This regex below represents "<\d+>?1" (requirements and/or default) declared "page" attribute combinations or empty attribute.
        $isDefaultParameter = preg_match("/^(<\\d\+>)?1?$/", $page);
        $isPageParameterCorrect = \ctype_digit((string) $page) && $page >= 1;
        // Wrong parameters
        // This is an optional check thanks to requirements and default empty parameters on route at this time
        if (!$isPageParameterCorrect && !$isDefaultParameter) {
            $this->logger->error(
                sprintf("[trace app SnowTricks] TrickManager/filterPaginationRequestAttribute => pagination error with parameter: %s", $page)
            );
            throw new \UnexpectedValueException('Trick list pagination parameters error: list cannot be generated!');
        }
        // Adjust Default parameter
        return $isPageParameterCorrect && !$isDefaultParameter ? (int) $page : 1;
    }

    /**
     * Filter trick list request attributes preparing parameters to check.
     *
     * @param Request $request
     *
     * @return array
     *
     * @throws \UnexpectedValueException
     */
    public function filterListRequestAttributes(Request $request): array
    {
        if (!$request->attributes->has('offset') || !\ctype_digit((string) $request->attributes->get('offset'))) {
            $this->logger->error("[trace app SnowTricks] TrickManager/filterListRequestAttributes => list error: at least, offset route placeholder is expected!");
            throw new \UnexpectedValueException('Trick list parameters error: list cannot be generated!');
        }
        // Get starting rank
        $offset = (int) $request->attributes->get('offset');
        // Check if limit parameter exists or use default limit value
        $limit = \ctype_digit((string) $request->attributes->get('limit'))
            ? (int) $request->attributes->get('limit')
            : $this->getTrickListConfigParameters()['numberPerLoading'];
        return [
            'offset' => $offset,
            'limit' => $limit
        ];
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
     * Get Trick entity repository.
     *
     * @return TrickRepository
     */
    public function getRepository(): TrickRepository
    {
        return $this->repository;
    }

    /**
     * Check if total count is outdated.
     *
     * For instance, this can happen when entities are added or removed and an ajax request is performed.
     *
     * @param int $count
     *
     * @return bool
     */
    public function isCountAllOutdated(int $count): bool
    {
        $keyName = self::TRICK_COUNT_SESSION_KEY;
        if ($this->session->has($keyName) && $this->session->get($keyName) !== $count) {
            // Update trick total count stored in session
            $this->session->set($keyName, $count);
            return true;
        }
        return false;
    }

    /**
     * Remove a trick and all associated entities depending on cascade operations.
     *
     * @param Trick $trick
     * @param bool  $isFlushed
     *
     * @return bool
     */
    public function removeTrick(Trick $trick, bool $isFlushed = true): bool
    {
        // Proceed to removal in database
        return $this->removeAndSaveNoMoreEntity($trick, $isFlushed);
    }

    /**
     * Update a Trick entity with necessary data.
     *
     * @param UpdateTrickDTO      $updateTrickDTO
     * @param Trick               $trickToUpdate
     * @param User|UserInterface  $authenticatedUser
     * @param bool                $mustAuthorBeReplaced
     * @param bool                $isFlushed
     *
     * @return Trick
     *
     * @throws \Exception
     */
    public function updateTrick(
        UpdateTrickDTO $updateTrickDTO,
        Trick $trickToUpdate,
        UserInterface $authenticatedUser,
        bool $mustAuthorBeReplaced = false,
        bool $isFlushed = false
    ): Trick {
        // Must author be replaced (updated)?
        // CAUTION! This is the case when an author account is deleted, then a particular anonymous becomes trick author.
        // At this time user author is not changed on update to keep the original author.
        !$mustAuthorBeReplaced ?: $trickToUpdate->modifyUser($authenticatedUser);
        // Update trick simple data
        $trickToUpdate
            ->modifyTrickGroup($updateTrickDTO->getGroup()) // At this time only one TrickGroup is returned.
            ->modifyName($updateTrickDTO->getName())
            ->modifyDescription($updateTrickDTO->getDescription())
            ->customizeSlug($this->makeSlug($updateTrickDTO->getName())) // At this time slug is not customized in form, so create it with trick name.
            ->modifyUpdateDate(new \DateTime('now'))
            ->modifyIsPublished($updateTrickDTO->getIsPublished()); // This can change with administrator account!
        // Save data in database if expected
        $trickToUpdate = $this->addAndSaveTrick($trickToUpdate, false, $isFlushed);
        return $trickToUpdate;
    }
}
