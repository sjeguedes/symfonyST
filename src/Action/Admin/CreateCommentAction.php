<?php

declare(strict_types=1);

namespace App\Action\Admin;

use App\Action\AbstractCommentListAction;
use App\Domain\Entity\Comment;
use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\CommentManager;
use App\Domain\ServiceLayer\MediaTypeManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Domain\ServiceLayer\UserManager;
use App\Responder\Redirection\RedirectionResponder;
use App\Responder\TemplateResponder;
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
class CreateCommentAction extends AbstractCommentListAction
{
    use RouterHelperTrait;
    use UuidHelperTrait;

    /**
     * @var CommentManager
     */
    protected $commentService;

    /**
     * @var MediaTypeManager
     */
    protected $mediaTypeService;

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
        parent::__construct($commentService, $mediaTypeService);
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
     * @param TemplateResponder    $responder
     * @param Request              $request
     *
     * @return Response
     *
     * @throws AccessDeniedException
     * @throws \Exception
     */
    public function __invoke(RedirectionResponder $redirectionResponder, TemplateResponder $responder, Request $request): Response
    {
        // Check access to creation form
        $currentTrick = $this->checkAccessToCreationAction($request);
        // Use current trick as form type options
        $options = ['trickToUpdate'  => $currentTrick];
        // Set form without initial model data and set the request by binding it
        $createTrickCommentForm = $this->formHandler->initForm(null, null, $options)->bindRequest($request);
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
        // Get comments list with ranks and comments total count
        $selectedTrickCommentsData = $this->prepareTrickCommentsListWithRanks(
            $currentTrick->getUuid(),
            0,
            Comment::COMMENT_NUMBER_PER_LOADING,
            Comment::COMMENT_LOADING_MODE
        );
        $data = [
            'createCommentForm'         => $createTrickCommentForm->createView(),
            // Offset and limit are not defined by default here!
            'commentAjaxLoadingPath'    => $this->router->generate(
                'load_trick_comments_offset_limit', [
                'trickEncodedUuid' => $request->attributes->get('encodedUuid')
            ]),
            // Get total trick comment count
            'commentCount'              => $selectedTrickCommentsData['commentsTotalCount'],
            'selectedTrickComments'     => $selectedTrickCommentsData['commentListWithRanks'],
            'trick'                     => $currentTrick,
            'trickCommentCreationError' => $this->formHandler->getCommentCreationError() ?? null,
            // Empty declared url is more explicit!
            'videoURLProxyPath'         => $this->trickService->generateURLFromRoute(
                'load_trick_video_url_check', ['url' => ''],
                UrlGeneratorInterface::ABSOLUTE_URL
            )
        ];
        // Get complementary needed comment list and medias data
        $data = array_merge($this->getCommentListData(), $this->getMediasData(), $data);
        return $responder($data, self::class);
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
    private function checkAccessToCreationAction(Request $request): Trick
    {
        // Check if a trick can be retrieved thanks to its uuid
        $trick = $this->trickService->findSingleToUpdateInFormByEncodedUuid($request->attributes->get('encodedUuid'));
        if (\is_null($trick)) {
            throw new NotFoundHttpException('Sorry, no trick was found due to wrong identifier!');
        }
        // Check access permissions to trick comment creation form
        $security = $this->userService->getSecurity();
        if (!$security->isGranted('ROLE_USER')) {
            throw new AccessDeniedException("Current user cannot create a trick comment!");
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
    private function manageCommentCreationResultRouting(Request $request): array
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
