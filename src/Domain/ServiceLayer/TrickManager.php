<?php
declare(strict_types = 1);

namespace App\Domain\ServiceLayer;

use App\Domain\Entity\Trick;
use App\Domain\Repository\TrickRepository;
use App\Utils\Traits\RouterHelperTrait;
use App\Utils\Traits\SessionHelperTrait;
use App\Utils\Traits\UuidHelperTrait;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class TrickManager.
 *
 * Manage tricks to handle, and retrieve them as a "service layer".
 */
class TrickManager
{
    use LoggerAwareTrait;
    use RouterHelperTrait;
    use SessionHelperTrait;
    use UuidHelperTrait;

    /**
     * @var TrickRepository
     */
    private $repository;

    /**
     * TrickManager constructor.
     *
     * @param TrickRepository  $repository
     * @param LoggerInterface  $logger
     * @param RouterInterface  $router
     * @param SessionInterface $session
     *
     * @return void
     */
    public function __construct(
        TrickRepository $repository,
        LoggerInterface $logger,
        RouterInterface $router,
        SessionInterface $session
    ) {
        $this->repository = $repository;
        $this->setLogger($logger);
        $this->setRouter($router);
        $this->setSession($session);
    }

    /**
     * Find Trick by name string.
     *
     * @param string $name
     *
     * @return Trick|null
     */
    public function findSingleByName(string $name) : ?Trick
    {
        return $this->repository->findOneByName($name);
    }

    /**
     * Find Trick by encoded uuid string.
     *
     * @param string $encodedUuid
     *
     * @return Trick|null
     */
    public function findSingleByEncodedUuid(string $encodedUuid) : ?Trick
    {
        $uuid = $this->decode($encodedUuid);
        return $this->repository->findOneByUuid($uuid);
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
    public function getDefaultTrickList() : array
    {
        $startOffset = $this->getStartOffset();
        $parameters = $this->filterParametersWithOrder($startOffset);
        return $parameters;
    }

    /**
     * Get filtered trick list depending on parameters.
     *
     * @param int|null $offset
     * @param int      $limit
     * @param string   $order
     *
     * @return array
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function getFilteredList(
        int $offset = null,
        int $limit = Trick::TRICK_NUMBER_PER_LOADING,
        string $order = Trick::TRICK_LOADING_MODE
    ) : array {
        // Init value to define starting rank
        $init = ('DESC' === $order) ? $this->countAll() : -1;
        // Offset starts at 0 (i.e. the 15th Trick rank has a value of 14)
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
    public function getListDefaultParameters() : array
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
    public function getPaginationParameters(int $pageIndex) : ?array
    {
        $countAll = $this->countAll();
        $listDefaultParameters = $this->getListDefaultParameters();
        $trickNumberPerPage = $listDefaultParameters['numberPerPage'];
        $pageCount = $countAll % $trickNumberPerPage == 0
            ? $countAll / $trickNumberPerPage
            : (int) floor($countAll / $trickNumberPerPage) + 1;
        $loadingMode = $listDefaultParameters['loadingMode'];
        if ($pageIndex <= 0 || $pageIndex > $pageCount) {
            return null;
        }
        if ('DESC' === $loadingMode) {
            $offset = $countAll - $pageIndex * $trickNumberPerPage < 0
                ? 0 : $countAll - $pageIndex * $trickNumberPerPage;
            $limit = $offset === 0
                ? $countAll % $trickNumberPerPage : $trickNumberPerPage;

        } else {
            $offset = $pageIndex === 1
                ? 0 : ($pageIndex - 1) * $trickNumberPerPage;
            $limit = $offset + $trickNumberPerPage > $countAll - 1
                ? $countAll % $trickNumberPerPage : $trickNumberPerPage;
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
    public function getStartOffset() : int
    {
        // Get tricks total number
        $countAll = $this->countAll();
        $startOffset = ('DESC' === $this->getListDefaultParameters()['loadingMode'])
            ? $countAll - $this->getListDefaultParameters()['numberPerLoading'] : 0;
        return $startOffset;
    }

    /**
     * Count all tricks without filter.
     *
     * @return int
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \UnexpectedValueException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function countAll() : int
    {
        $result = $this->repository->countAll();
        if (\is_null($result)) {
            throw new \UnexpectedValueException('Trick total count error: list can not be generated!');
        }
        return $result;
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
    ) : array {
        $count = $this->repository->countAll();
        $minOffset = 0;
        $maxOffset = $count - 1;
        $validOffset = ($offset >= - $limit) && ($offset < $maxOffset + $limit + 1);
        $validLimit = $limit >= 1;
        $validOrder = ('ASC' === $order) || ('DESC' === $order);
        $parameters = ['offset' => $offset, 'limit' => $limit, 'error' => false];
        $conditions = [
            'descendingOrderAndWrongParameters' => 'DESC' === $order && (!$validOffset || !$validLimit),
            'ascendingOrderAndWrongParameters'  => 'ASC'  === $order && (!$validOffset || !$validLimit),
            'descendingOrderUnderMinimumOffset' => 'DESC' === $order && ($offset <= $minOffset),
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
        if (!empty($condition)) {
            switch ($condition) {
                // Error: check if $offset or $limit values are wrong:
                // so reset list to default parameters for descending order
                case \array_key_exists('descendingOrderAndWrongParameters', $condition):
                    $parameters['offset'] = $maxOffset + 1 - $this->getListDefaultParameters()['numberPerLoading'];
                    $parameters['limit'] = $this->getListDefaultParameters()['numberPerLoading'];
                    $parameters['error'] = true;
                    $this->logger->error("[trace app snowTricks] TrickManager/filterParametersWithOrder - descendingOrderAndWrongParameters => parameters: " . serialize($parameters));
                    break;
                // Error: check if $offset or $limit values are wrong:
                // so reset list to default parameters for ascending order
                case \array_key_exists('ascendingOrderAndWrongParameters', $condition):
                    $parameters['offset'] = 0;
                    $parameters['limit'] = $this->getListDefaultParameters()['numberPerLoading'];
                    $parameters['error'] = true;
                    $this->logger->error("[trace app snowTricks] TrickManager/filterParametersWithOrder - ascendingOrderAndWrongParameters => parameters: " . serialize($parameters));
                    break;
                // Particular case: check if calculated $offset is not under $minOffset:
                // lowest $offset must be 0 for descending order
                case \array_key_exists('descendingOrderUnderMinimumOffset', $condition):
                    $modulo = $count % $this->getListDefaultParameters()['numberPerLoading'];
                    $parameters['offset'] = $minOffset;
                    $parameters['limit'] = $modulo == 0 ? 1 : $modulo; // recalculate $limit to show last tricks
                    $parameters['error'] = false;
                    $this->logger->info("[trace app snowTricks] TrickManager/filterParametersWithOrder - descendingOrderUnderMinimumOffset => parameters: " . serialize($parameters));
                    break;
                // Particular case: check if calculated $offset + $limit is not over $maxOffset:
                // highest offset must be equal to (total count - 1) for ascending order
                case \array_key_exists('ascendingOrderOverMaxOffset', $condition):
                    $parameters['limit'] = $maxOffset + 1 - $offset; // recalculate $limit to show last tricks
                    $this->logger->info("[trace app snowTricks] TrickManager/filterParametersWithOrder - ascendingOrderOverMaxOffset => parameters: " . serialize($parameters));
                    $parameters['error'] = false;
                    break;
            }
        }
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
     * @throws \UnexpectedValueException
     */
    public function filterPaginationRequestAttribute(Request $request, string $route) : int
    {
        // Wrong parameters
        $issue1 = $request->attributes->has('page') && (int) $request->attributes->get('page') <= 0;
        $issue2 = $route !== $request->attributes->get('_route');
        if ($issue1 || $issue2) {
            $paginationError = true === $issue1 ? $issue1 : $issue2;
            $this->logger->error("[trace app snowTricks] TrickManager/filterPaginationRequestAttribute => pagination error: " . serialize($paginationError));
            throw new \UnexpectedValueException('Trick list pagination parameters error: list can not be generated!');
        }
        // Prevent issue if "page" placeholder has no default value
        return $request->attributes->has('page')
            ? (int) $request->attributes->get('page')
            : 1;
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
    public function filterListRequestAttributes(Request $request) : array
    {
        if (!$request->attributes->has('offset')) {
            $this->logger->error("[trace app snowTricks] TrickManager/filterListRequestAttributes => list error: at least, offset route placeholder is expected!");
            throw new \UnexpectedValueException('Trick list parameters error: list can not be generated!');
        }
        // Get starting rank
        $offset = (int)$request->attributes->get('offset');
        // Check if limit parameter exists
        $limit = $request->attributes->has('limit')
            ? (int)$request->attributes->get('limit')
            : $this->getListDefaultParameters()['numberPerLoading'];
        return [
            'offset' => $offset,
            'limit' => $limit
        ];
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
    public function isCountAllOutdated(int $count) : bool
    {
        if ($this->session->has('trickCount') && $this->session->get('trickCount') !== $count) {
            $this->session->remove('trickCount');
            $this->session->set('trickCount', $count);
            return true;
        }
        return false;
    }
}
