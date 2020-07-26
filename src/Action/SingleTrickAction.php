<?php

declare(strict_types = 1);

namespace App\Action;

use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\MediaTypeManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Responder\SingleTrickResponder;
use App\Service\Security\Voter\TrickVoter;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class SingleTrickAction.
 *
 * Manage single trick page display.
 */
class SingleTrickAction
{
    use LoggerAwareTrait;

    /**
     * @var MediaTypeManager
     */
    private $mediaTypeService;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * SingleTrickAction constructor.
     *
     * @param MediaTypeManager              $mediaTypeService
     * @param TrickManager                  $trickService
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param LoggerInterface               $logger
     *
     * @return void
     */
    public function __construct(
        MediaTypeManager $mediaTypeService,
        TrickManager $trickService,
        AuthorizationCheckerInterface $authorizationChecker,
        LoggerInterface $logger
    ) {
        $this->mediaTypeService = $mediaTypeService;
        $this->trickService = $trickService;
        $this->authorizationChecker = $authorizationChecker;
        $this->setLogger($logger);

    }

    /**
     * Show homepage with starting list of tricks.
     *
     * @Route({
     *     "en": "/{_locale<en>}/trick/{slug<[\w-]+>}-{encodedUuid<\w+>}"
     * }, name="show_single_trick", methods={"GET"})
     *
     * @param SingleTrickResponder $responder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    public function __invoke(SingleTrickResponder $responder, Request $request) : Response
    {
        // Check access to single page
        $trick = $this->checkAccessToSingleAction($request);
        // Get registered normal image type (corresponds particular dimensions)
        $trickNormalImageTypeValue = $this->mediaTypeService->getMandatoryDefaultTypes()['trickNormal'];
        $normalImageMediaType = $this->mediaTypeService->findSingleByUniqueType($trickNormalImageTypeValue);
        // Check wrong parameters!
        if (\is_null($normalImageMediaType) || \is_null($trick)) {
            $error = \is_null($normalImageMediaType) ? 'Trick normal image type' : 'Trick uuid';
            $this->logger->error("[trace app snowTricks] SingleTrickAction/__invoke => ' . $error . ': null");
            throw new NotFoundHttpException('Trick can not be found or correctly shown! Wrong parameters are used.');
        }
        $data = [
            'mediaError'           => 'Media loading error',
            'mediaTypesValues'     => $this->mediaTypeService->getMandatoryDefaultTypes(),
            'normalImageMediaType' => $normalImageMediaType,
            // Empty declared url is more explicit!
            'videoURLProxyPath'    => $this->trickService->generateURLFromRoute(
                'load_trick_video_url_check', ['url' => ''],
                UrlGeneratorInterface::ABSOLUTE_URL
            ),
            'trick'                => $trick
        ];
        return $responder($data);
    }

    /**
     * Check single trick page access.
     *
     * @param Request $request
     *
     * @return Trick
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     */
    private function checkAccessToSingleAction(Request $request) : Trick
    {
        // Check if a trick can be retrieved thanks to its uuid
        $trick = $this->trickService->findSingleToShowByEncodedUuid($request->attributes->get('encodedUuid'));
        if (\is_null($trick)) {
            throw new NotFoundHttpException('Sorry, no trick was found due to wrong identifier!');
        }
        // Check access permissions to view this trick
        if (!$this->authorizationChecker->isGranted(TrickVoter::AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS, $trick)) {
            throw new AccessDeniedException("Current user can not view this unpublished trick!");
        }
        return $trick;
    }
}
