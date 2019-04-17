<?php

declare(strict_types = 1);

namespace App\Action;

use App\Domain\Service\TrickManager;
use App\Responder\HomeTrickListResponder;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class HomeTrickListAction.
 *
 * Manage homepage tricks starting list display.
 */
class HomeTrickListAction
{
    use LoggerAwareTrait;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * HomeTrickListAction constructor.
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
     * Show homepage with starting list of tricks.
     *
     * @Route("/{_locale}", name="home")
     *
     * @param HomeTrickListResponder $responder
     * @param Request                $request
     *
     * @return Response
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function __invoke(HomeTrickListResponder $responder, Request $request) : Response
    {
        // Initialize default list.
        $parameters = $this->trickService->getDefaultTrickList();
        // Check values which are not allowed!
        if (($parameters['error'])) {
            $this->logger->error("[trace app snowTricks] HomeTrickListAction/__invoke => parameters: " . serialize($parameters));
            throw new NotFoundHttpException('Trick list can not be initialized! Wrong parameters are used.');
        }
        $data = [
            'listEnded'             => 'No more trick to load!',
            'technicalError'        => 'Sorry, something wrong happened<br>during trick list loading!<br>Please contact us or try again later.<br>',
            'trickAjaxLoadingPath'  => $this->trickService->generateURLFromRoute('home_load_tricks_offset_only', []),
            'trickCount'            => $this->trickService->countAll(),
            'trickLoadingMode'      => $this->trickService->getListDefaultParameters()['loadingMode'],
            'trickNumberPerLoading' => $parameters['limit'],
            'tricks'                => $this->trickService->getFilteredList($parameters['offset'], $parameters['limit'])
        ];
        // Store trick total count in session for use of ajax request.
        $this->trickService->storeInSession('trickCount', $data['trickCount']);
        return $responder($data);
    }
}
