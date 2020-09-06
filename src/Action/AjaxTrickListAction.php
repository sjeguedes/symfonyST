<?php
declare(strict_types = 1);

namespace App\Action;

use App\Domain\ServiceLayer\TrickManager;
use App\Responder\AjaxTrickListResponder;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class AjaxTrickListAction.
 *
 * Manage homepage trick list display for "load more" functionality.
 */
class AjaxTrickListAction
{
    use LoggerAwareTrait;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * AjaxTrickListAction constructor.
     *
     * @param TrickManager    $trickService
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function __construct(TrickManager $trickService, LoggerInterface $logger)
    {
       $this->trickService = $trickService;
       $this->setLogger($logger);
    }

    /**
     * Load tricks from AJAX request.
     *
     * Please not url is always the same even if language changed.
     * This is a simple AJAX request and locale parameter is null.
     *
     * @Route("/load-tricks/{offset?<\d+>}/{limit?<\d+>?}", name="load_tricks_offset_limit", methods={"GET"})
     *
     * @param AjaxTrickListResponder $responder
     * @param Request                $request
     *
     * @return Response
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Throwable
     */
    public function __invoke(AjaxTrickListResponder $responder, Request $request) : Response
    {
        // Filter AJAX request
        if (!$request->isXmlHttpRequest()) {
            throw new AccessDeniedException('Access is not allowed without AJAX request!');
        }
        // Get current total count
        $trickCount = $this->trickService->countAll();
        // Get parameters or/and list error notification message
        $infos = $this->checkOutdatedTrickList($request, $trickCount);
        $data = [
            'ajaxMode'         => true,
            // Get list error by checking outdated trick count to reinitialize list
            'listError'        => $infos['listError'] ?? null,
            'trickCount'       => $trickCount,
            'trickLoadingMode' => $this->trickService->getTrickListConfigParameters()['loadingMode'],
            'tricks'           => $this->trickService->getFilteredList(
                $infos['parameters']['offset'],
                $infos['parameters']['limit']
            )
        ];
        return $responder($data);
    }

    /**
     * Check outdated trick list during loading,
     * if new tricks were created or existing ones were deleted in-between.
     *
     * @param Request $request
     * @param int     $trickCount
     *
     * @return array an error notification message as "$listError", and "$parameters" parameters.
     *
     * @throws \Doctrine\ORM\NoResultException
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function checkOutdatedTrickList(Request $request, int $trickCount) : array
    {
        // Total count has changed during trick list ajax loading!
        if ($this->trickService->isCountAllOutdated($trickCount)) {
            $parameters = $this->trickService->getTrickListParameters();
            $this->logger->error(
                sprintf(
                    "[trace app snowTricks] AjaxTrickListAction/__invoke => trickCount: %s",
                    $trickCount
                )
            );
            $listError = 'Trick list was reinitialized!' . "\n" .
                'Wrong total count is used' . "\n" .
                'due to outdated or unexpected value.';
            $data['parameters'] = $parameters;
            $data['listError'] = $listError;
        } else {
            // Filter request attributes (offset, limit, ...)
            $attributes = $this->trickService->filterListRequestAttributes($request);
            // Check/filter wrong parameters or particular cases
            $parameters = $this->trickService->filterParametersWithOrder($attributes['offset'], $attributes['limit']);
            $data['parameters'] = $parameters;
            // Check values which are not allowed!
            if (($parameters['error'])) {
                $this->logger->error(
                    sprintf(
                        "[trace app snowTricks] AjaxTrickListAction/__invoke => parameters: %s",
                        serialize($parameters)
                    )
                );
                $listError = 'Trick list was reinitialized!' . "\n" . 'Wrong parameters are used.';
                $data['listError'] = $listError;
            }
        }
        return $data;
    }
}
