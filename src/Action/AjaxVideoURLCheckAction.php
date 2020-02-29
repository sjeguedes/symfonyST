<?php

declare(strict_types = 1);

namespace App\Action;

use App\Responder\AjaxVideoURLCheckResponder;
use App\Service\Medias\VideoURLProxyChecker;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

/**
 * Class AjaxVideoURLCheckAction.
 *
 * Verify if single trick video URL can be loaded using ajax.
 */
class AjaxVideoURLCheckAction
{
    use LoggerAwareTrait;

    /**
     * @var VideoURLProxyChecker
     */
    private $trickVideoChecker;

    /**
     * AjaxTrickListAction constructor.
     *
     * @param VideoURLProxyChecker $trickVideoChecker
     *
     * @param LoggerInterface $logger
     *
     * @return void
     */
    public function __construct(VideoURLProxyChecker $trickVideoChecker, LoggerInterface $logger)
    {
       $this->trickVideoChecker = $trickVideoChecker;
       $this->setLogger($logger);
    }

    /**
     * Check if single trick video URL can be loaded from ajax request.
     *
     * @Route("/{_locale}/load-trick-video/{url}", name="load_trick_video_url_check", requirements={"url"="(.+)?"})
     * @Route("/{_locale}/load-trick-video", name="load_trick_video_url_query_check")
     *
     * @param AjaxVideoURLCheckResponder $responder
     * @param Request                    $request
     *
     * @return Response
     *
     * @see https://symfony.com/doc/current/routing/slash_in_parameter.html
     */
    public function __invoke(AjaxVideoURLCheckResponder $responder, Request $request) : Response
    {
        // Check if URL value is null!
        $url = $this->trickVideoChecker->filterURLAttribute($request);
        if (\is_null($url)) {
            $this->logger->error("[trace app snowTricks] AjaxVideoURLCheckAction/__invoke => url: null");
        }
        $data = $this->trickVideoChecker->verify($url);
        return $responder($data);
    }
}
