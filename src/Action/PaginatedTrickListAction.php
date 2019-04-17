<?php

declare(strict_types = 1);

namespace App\Action;

use App\Domain\Service\TrickManager;
use App\Responder\PaginatedTrickListResponder;
use App\Responder\Redirection\RedirectionResponder;
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
     * @Route("/{_locale}/trick-list/{page}", name="tricks", defaults={"page"=1}, requirements={"page"="\d+"})
     *
     * @param PaginatedTrickListResponder $responder
     * @param RedirectionResponder        $redirectionResponder
     * @param Request                     $request
     *
     * @return Response
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function __invoke(PaginatedTrickListResponder $responder, RedirectionResponder $redirectionResponder, Request $request) : Response
    {
        // Particular redirection (optional)
        if ('/' . $request->get('_locale') . '/trick-list/1' === $request->getPathInfo()) {
            return $redirectionResponder('tricks');
        }
        // Get necessary data to create pagination filtering wrong parameters if necessary
        $pageIndex = $this->trickService->filterPaginationRequestAttribute($request, 'tricks');
        $paginationParameters = $this->trickService->getPaginationParameters($pageIndex);
        if (\is_null($paginationParameters)) {
            $this->logger->error("[trace app snowTricks] PaginatedTrickListAction/__invoke => pagination parameters: null");
            throw new NotFoundHttpException('Trick list page can not be reached! Wrong parameter is used.');
        }
        $data = [
            'currentPage'      => $paginationParameters['currentPage'],
            'pageCount'        => $paginationParameters['pageCount'],
            'trickCount'       => $paginationParameters['trickCount'],
            'trickLoadingMode' => $paginationParameters['loadingMode'],
            'tricks'           => $this->trickService->getFilteredList($paginationParameters['currentOffset'], $paginationParameters['currentLimit'])
        ];
        return $responder($data);
    }
}
