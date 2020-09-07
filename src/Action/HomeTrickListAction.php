<?php

declare(strict_types=1);

namespace App\Action;

use App\Domain\ServiceLayer\TrickManager;
use App\Responder\HomeTrickListResponder;
use App\Utils\Traits\RouterHelperTrait;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class HomeTrickListAction.
 *
 * Manage homepage tricks starting list display.
 */
class HomeTrickListAction
{
    use LoggerAwareTrait;
    use RouterHelperTrait;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * HomeTrickListAction constructor.
     *
     * @param TrickManager    $trickService
     * @param RouterInterface $router
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function __construct(TrickManager $trickService, RouterInterface $router, LoggerInterface $logger) {
        $this->trickService = $trickService;
        $this->setRouter($router);
        $this->setLogger($logger);
    }

    /**
     * Show homepage with starting list of tricks.
     *
     * @Route({
     *     "en": "/en"
     * }, name="home", methods={"GET"})
     *
     * @param HomeTrickListResponder $responder
     * @param Request                $request
     *
     * @return Response
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Doctrine\ORM\NoResultException
     */
    public function __invoke(HomeTrickListResponder $responder, Request $request): Response
    {
        // Initialize default list.
        $parameters = $this->trickService->getTrickListParameters();
        // Check values which are not allowed!
        if (($parameters['error'])) {
            $this->logger->error(
                sprintf(
                    "[trace app SnowTricks] HomeTrickListAction/__invoke => parameters: %s",
                    serialize($parameters)
                )
            );
            throw new NotFoundHttpException('Trick list cannot be initialized! Wrong parameters are used.');
        }
        $data = [
            'listEnded'             => 'No more trick to load!',
            'noList'                => 'Sorry, no trick was found!',
            'technicalError'        => 'Sorry, something wrong happened' . "\n" .
                                       'during trick list loading!' . "\n" .
                                       'Please contact us or try again later.' . "\n",
            'trickAjaxLoadingPath'  => $this->router->generate('load_tricks_offset_limit'),
            'trickCount'            => $this->trickService->countAll(),
            'trickLoadingMode'      => $this->trickService->getTrickListConfigParameters()['loadingMode'],
            'trickNumberPerLoading' => $parameters['limit'],
            'tricks'                => $this->trickService->getFilteredList($parameters['offset'], $parameters['limit'])
        ];
        // Store trick total count in session for use of ajax request.
        $this->trickService->storeInSession(TrickManager::TRICK_COUNT_SESSION_KEY, $data['trickCount']);
        return $responder($data);
    }
}
