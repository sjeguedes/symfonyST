<?php

declare(strict_types=1);

namespace App\Action;

use App\Domain\ServiceLayer\TrickManager;
use App\Responder\Redirection\RedirectionResponder;
use App\Responder\TemplateResponder;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class PaginatedTrickListAction.
 *
 * Manage complete trick list page display with pagination.
 */
class PaginatedTrickListAction
{
    use LoggerAwareTrait;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * PaginatedTrickListAction constructor.
     *
     * @param TrickManager    $trickService
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function __construct(TrickManager $trickService, LoggerInterface $logger) {
        $this->trickService = $trickService;
        $this->setLogger($logger);
    }

    /**
     * Show complete list directly on dedicated page "tricks".
     *
     * @Route({
     *     "en": "/{_locale<en>}/trick-list/page/{page<\d+>?}"
     * }, name="list_tricks", methods={"GET"})
     *
     * @param RedirectionResponder $redirectionResponder
     * @param TemplateResponder    $responder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function __invoke(RedirectionResponder $redirectionResponder, TemplateResponder $responder, Request $request): Response
    {
        // Make a redirection to page index "1" by default, if page attribute is empty (allowed in route)
        // CAUTION: be aware of defining route "page" attribute requirements and default value carefully!
        if (\is_null($request->attributes->get('page'))) {
            return $redirectionResponder('list_tricks', ['page' => 1]);
        }
        // Get necessary data to create pagination filtering wrong parameters if necessary
        $pageIndex = $this->trickService->filterPaginationRequestAttribute($request);
        $paginationParameters = $this->trickService->getTrickListPaginationParameters($pageIndex);
        if (\is_null($paginationParameters)) {
            $this->logger->error("[trace app SnowTricks] PaginatedTrickListAction/__invoke => pagination parameters: null");
            throw new NotFoundHttpException('Trick list page cannot be reached! Wrong parameter is used.');
        }
        $data = [
            'currentPage'      => $paginationParameters['currentPage'],
            'noList'           => 'Sorry, no trick was found!',
            'pageCount'        => $paginationParameters['pageCount'],
            'trickCount'       => $paginationParameters['trickCount'],
            'trickLoadingMode' => $paginationParameters['loadingMode'],
            'tricks'           => $this->trickService->getFilteredList($paginationParameters['currentOffset'], $paginationParameters['currentLimit'])
        ];
        return $responder($data, self::class);
    }
}
