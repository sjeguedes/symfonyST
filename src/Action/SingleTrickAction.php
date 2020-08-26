<?php

declare(strict_types = 1);

namespace App\Action;

use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\MediaTypeManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Responder\SingleTrickResponder;
use App\Service\Form\Handler\FormHandlerInterface;
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
     * @var FormHandlerInterface
     */
    private $formHandler;

    /**
     * @var AuthorizationCheckerInterface
     */
    private $authorizationChecker;

    /**
     * SingleTrickAction constructor.
     *
     * @param MediaTypeManager              $mediaTypeService
     * @param TrickManager                  $trickService
     * @param FormHandlerInterface          $formHandler
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param LoggerInterface               $logger
     *
     * @return void
     */
    public function __construct(
        MediaTypeManager $mediaTypeService,
        TrickManager $trickService,
        FormHandlerInterface $formHandler,
        AuthorizationCheckerInterface $authorizationChecker,
        LoggerInterface $logger
    ) {
        $this->mediaTypeService = $mediaTypeService;
        $this->trickService = $trickService;
        $this->authorizationChecker = $authorizationChecker;
        $this->setLogger($logger);

        $this->formHandler = $formHandler;
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
        $currentTrick = $this->checkAccessToSingleAction($request);
        // Get registered normal image type (corresponds particular dimensions)
        $trickNormalImageTypeValue = $this->mediaTypeService->getMandatoryDefaultTypes()['trickNormal'];
        $normalImageMediaType = $this->mediaTypeService->findSingleByUniqueType($trickNormalImageTypeValue);
        // Use current trick as form type options
        $options = ['trickToUpdate'  => $currentTrick];
        // Set trick comment form without initial model data and set the request by binding it
        $createTrickCommentForm = $this->formHandler->initForm(null, null, $options)->bindRequest($request);
        $data = [
            'createCommentForm'         => $createTrickCommentForm->createView(),
            'mediaError'                => 'Media loading error',
            'mediaTypesValues'          => $this->mediaTypeService->getMandatoryDefaultTypes(),
            'normalImageMediaType'      => $normalImageMediaType,
            'noList'                    => 'No comment exists for this trick at this time!',
            'trick'                     => $currentTrick,
            'trickCommentCreationError' => null,
            // Empty declared url is more explicit!
            'videoURLProxyPath'         => $this->trickService->generateURLFromRoute(
                'load_trick_video_url_check', ['url' => ''],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
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
