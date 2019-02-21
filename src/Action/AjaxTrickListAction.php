<?php
declare(strict_types = 1);

namespace App\Action;

use App\Domain\Service\TrickManager;
use App\Responder\AjaxTrickListResponder;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AjaxTrickListAction.
 *
 * Manage homepage trick list initial display for "load more" functionality.
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
     * load tricks from ajax request.
     *
     * @Route("/{_locale}/home-load-tricks/{offset}/{limit}", name="home_load_tricks_offset_limit", requirements={"offset":"\d+","limit":"\d+"})
     * @Route("/{_locale}/home-load-tricks/{offset}", name="home_load_tricks_offset_only", requirements={"offset"="\d+"})
     *
     * @param AjaxTrickListResponder $responder
     * @param Request                $request
     *
     * @return \Symfony\Component\HttpFoundation\Response
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Throwable
     */
    public function __invoke(AjaxTrickListResponder $responder, Request $request) : Response
    {
        // Get current total count
        $trickCount = $this->trickService->countAll();
        // Total count has changed during trick list ajax loading!
        if ($this->trickService->isCountAllOutdated($trickCount)) {
            $this->trickService->storeInSession('trickCount', $trickCount);
            $parameters = $this->trickService->getDefaultTrickList();
            $this->logger->error("[trace app snowTricks] AjaxTrickListAction/__invoke => trickCount: " . $trickCount);
            $listError = 'Trick list was reinitialized!<br>Wrong total count is used<br>due to outdated value.';
        } else {
            // Filter request attributes (offset, limit, ...)
            $attributes = $this->trickService->filterListRequestAttributes($request);
            // Check/filter wrong parameters or particular cases
            $parameters = $this->trickService->filterParametersWithOrder($attributes['offset'], $attributes['limit']);
            // Check values which are not allowed!
            if (($parameters['error'])) {
                $this->logger->error("[trace app snowTricks] AjaxTrickListAction/__invoke => parameters: " . serialize($parameters));
                $listError = 'Trick list was reinitialized!<br>Wrong parameters are used.';
            }
        }
        $data = [
            'ajaxMode'         => true,
            'listError'        => $listError ?? null,
            'trickCount'       => $trickCount,
            'trickLoadingMode' => $this->trickService->getListDefaultParameters()['loadingMode'],
            'tricks'           => $this->trickService->getFilteredList($parameters['offset'], $parameters['limit'])
        ];
        return $responder($data);
    }
}
