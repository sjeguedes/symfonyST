<?php

declare(strict_types = 1);

namespace App\Action\Admin;

use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\CommentManager;
use App\Domain\ServiceLayer\MediaTypeManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Domain\ServiceLayer\UserManager;
use App\Responder\Redirection\RedirectionResponder;
use App\Responder\SingleTrickResponder;
use App\Service\Form\Handler\FormHandlerInterface;
use App\Utils\Traits\RouterHelperTrait;
use App\Utils\Traits\UuidHelperTrait;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class CreateCommentAction.
 *
 * Manage trick comment creation form.
 */
class CreateCommentAction
{
    use RouterHelperTrait;
    use UuidHelperTrait;

    /**
     * @var CommentManager
     */
    private $commentService;

    /**
     * @var MediaTypeManager
     */
    private $mediaTypeService;

    /**
     * @var UserManager
     */
    private $userService;

    /**
     * @var TrickManager
     */
    private $trickService;

    /**
     * @var FormHandlerInterface
     */
    private $formHandler;

    /**
     * CreateCommentAction constructor.
     *
     * @param CommentManager       $commentService
     * @param MediaTypeManager     $mediaTypeService
     * @param UserManager          $userService
     * @param TrickManager         $trickService
     * @param FormHandlerInterface $formHandler
     * @param RouterInterface      $router
     */
    public function __construct(
        CommentManager $commentService,
        MediaTypeManager $mediaTypeService,
        UserManager $userService,
        TrickManager $trickService,
        FormHandlerInterface $formHandler,
        RouterInterface $router
    ) {
        $this->commentService = $commentService;
        $this->mediaTypeService = $mediaTypeService;
        $this->userService = $userService;
        $this->trickService = $trickService;
        $this->formHandler = $formHandler;
        $this->setRouter($router);
    }

    /**
     *  Manage trick comment creation form and validation errors.
     *
     * @Route({
     *     "en": "/{_locale<en>}/trick/{slug<[\w-]+>}-{encodedUuid<\w+>}"
     * }, name="create_trick_comment", methods={"POST"})
     *
     * @param RedirectionResponder $redirectionResponder
     * @param SingleTrickResponder $responder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws AccessDeniedException
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, SingleTrickResponder $responder, Request $request) : Response
    {
        // Check access to creation form
        $currentTrick = $this->checkAccessToCreationAction($request);
        // Use current trick as form type options
        $options = ['trickToUpdate'  => $currentTrick];
        // Set form without initial model data and set the request by binding it
        $createTrickCommentForm = $this->formHandler->initForm(null, null, $options)->bindRequest($request);
        // Get registered normal image type (corresponds particular dimensions)
        $trickNormalImageTypeValue = $this->mediaTypeService->getMandatoryDefaultTypes()['trickNormal'];
        $normalImageMediaType = $this->mediaTypeService->findSingleByUniqueType($trickNormalImageTypeValue);
        // Process only on submit
        if ($createTrickCommentForm->isSubmitted()) {
            // Constraints and custom validation: call actions to perform if necessary on success
            $isFormRequestValid = $this->formHandler->processFormRequest([
                'commentService' => $this->commentService,
                'trickToUpdate'  => $currentTrick,
                'userToUpdate'   => $this->userService->getAuthenticatedMember()
            ]);
            if ($isFormRequestValid) {
                // IMPORTANT! This redirection is always made despite trick comment creation result!
                $routingParameters = $this->manageCommentCreationResultRouting($request);
                // Get redirection routing parameters due to this type of responder
                return $redirectionResponder($routingParameters['routeName'], $routingParameters['routeParameters']);
            }
        }
        $data = [
            'createCommentForm'         => $createTrickCommentForm->createView(),
            'mediaError'                => 'Media loading error',
            'mediaTypesValues'          => $this->mediaTypeService->getMandatoryDefaultTypes(),
            'normalImageMediaType'      => $normalImageMediaType,
            'noList'                    => 'No comment exists for this trick at this time!',
            'trick'                     => $currentTrick,
            'trickCommentCreationError' => $this->formHandler->getCommentCreationError() ?? null,
            // Empty declared url is more explicit!
            'videoURLProxyPath'         => $this->trickService->generateURLFromRoute(
                'load_trick_video_url_check', ['url' => ''],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        ];
        return $responder($data);
    }

    /**
     * Check trick comment creation form access.
     *
     * @param Request $request
     *
     * @return Trick
     *
     * @throws AccessDeniedException
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws NotFoundHttpException
     */
    private function checkAccessToCreationAction(Request $request) : Trick
    {
        // Check if a trick can be retrieved thanks to its uuid
        $trick = $this->trickService->findSingleToUpdateInFormByEncodedUuid($request->attributes->get('encodedUuid'));
        if (\is_null($trick)) {
            throw new NotFoundHttpException('Sorry, no trick was found due to wrong identifier!');
        }
        // Check access permissions to trick comment creation form
        $security = $this->userService->getSecurity();
        if (!$security->isGranted('ROLE_USER')) {
            throw new AccessDeniedException("Current user can not create a trick comment!");
        }
        return $trick;
    }

    /**
     * Manage trick creation redirection routing parameters.
     *
     * @param Request $request
     *
     * @return array
     */
    private function manageCommentCreationResultRouting(Request $request) : array
    {
        // A new trick comment is created or null is returned if an issue occurred!
        // In both failure or success cases, the same redirection is made!
        // (redirect to empty trick comment creation form and show initial single trick page)
        $routeName = 'show_single_trick';
        $routeParameters = [
            'mainRoleLabel' => $request->attributes->get('mainRoleLabel'),
            'slug'          => $request->attributes->get('slug'),
            'encodedUuid'   => $request->attributes->get('encodedUuid')
        ];
        return ['routeName' => $routeName, 'routeParameters' => $routeParameters];
    }
}
