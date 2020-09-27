<?php

declare(strict_types=1);

namespace App\Action;

use App\Domain\Entity\Comment;
use App\Domain\Entity\Trick;
use App\Domain\ServiceLayer\CommentManager;
use App\Domain\ServiceLayer\MediaTypeManager;
use App\Domain\ServiceLayer\TrickManager;
use App\Responder\TemplateResponder;
use App\Service\Form\Handler\FormHandlerInterface;
use App\Service\Security\Voter\TrickVoter;
use App\Utils\Traits\RouterHelperTrait;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class SingleTrickAction.
 *
 * Manage single trick page display.
 */
class SingleTrickAction extends AbstractCommentListAction
{
    use LoggerAwareTrait;
    use RouterHelperTrait;

    /**
     * @var CommentManager
     */
    protected $commentService;

    /**
     * @var MediaTypeManager
     */
    protected $mediaTypeService;

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
     * @param CommentManager                $commentService
     * @param MediaTypeManager              $mediaTypeService
     * @param TrickManager                  $trickService
     * @param AuthorizationCheckerInterface $authorizationChecker
     * @param FormHandlerInterface          $formHandler
     * @param RouterInterface               $router
     * @param LoggerInterface               $logger
     *
     * @return void
     */
    public function __construct(
        CommentManager $commentService,
        MediaTypeManager $mediaTypeService,
        TrickManager $trickService,
        AuthorizationCheckerInterface $authorizationChecker,
        FormHandlerInterface $formHandler,
        RouterInterface $router,
        LoggerInterface $logger
    ) {
        parent::__construct($commentService, $mediaTypeService);
        $this->commentService = $commentService;
        $this->mediaTypeService = $mediaTypeService;
        $this->trickService = $trickService;
        $this->authorizationChecker = $authorizationChecker;
        $this->formHandler = $formHandler;
        $this->setRouter($router);
        $this->setLogger($logger);
    }

    /**
     * Show homepage with starting list of tricks.
     *
     * @Route({
     *     "en": "/{_locale<en>}/trick/{slug<[\w-]+>}-{encodedUuid<\w+>}"
     * }, name="show_single_trick", methods={"GET"})
     *
     * @param TemplateResponder $responder
     * @param Request           $request
     *
     * @return Response
     *
     * @throws \Doctrine\ORM\NonUniqueResultException
     * @throws \Exception
     */
    public function __invoke(TemplateResponder $responder, Request $request): Response
    {
        // Check access to single page
        $currentTrick = $this->checkAccessToSingleAction($request);
        // Use current trick as form type options
        $options = ['trickToUpdate'  => $currentTrick];
        // Set trick comment form without initial model data and set the request by binding it
        $createTrickCommentForm = $this->formHandler->initForm(null, null, $options)->bindRequest($request);
        // Get comments list with ranks and comments total count
        $selectedTrickCommentsData = $this->prepareTrickCommentsListWithRanks(
            $currentTrick->getUuid(),
            0,
            Comment::COMMENT_NUMBER_PER_LOADING,
            Comment::COMMENT_LOADING_MODE
        );
        $data = [
            // Offset and limit are not defined by default here!
            'commentAjaxLoadingPath'       => $this->router->generate(
                'load_trick_comments_offset_limit', [
                'trickEncodedUuid' => $request->attributes->get('encodedUuid')
            ]),
            'createCommentForm'            => $createTrickCommentForm->createView(),
            // Get all trick comments total count
            'commentsTotalCount'           => $selectedTrickCommentsData['commentsTotalCount'],
            // Get only first level trick comments total count
            'firstLevelCommentsTotalCount' => $selectedTrickCommentsData['firstLevelCommentsTotalCount'],
            'selectedTrickComments'        => $selectedTrickCommentsData['commentListWithRanks'],
            'trick'                        => $currentTrick,
            'trickCommentCreationError'    => null,
            // Empty declared url is more explicit!
            'videoURLProxyPath'            => $this->router->generate(
                'load_trick_video_url_check', [
                    'url' => ''
            ])
        ];
        // Store trick comments total count in session for use of ajax request.
        $this->commentService->storeInSession(
            CommentManager::COMMENT_COUNT_SESSION_KEY_PREFIX . $currentTrick->getUuid()->toString(),
            $selectedTrickCommentsData['commentsTotalCount']
        );
        // Get complementary needed comment list and medias data
        $data = array_merge($this->getCommentListData(), $this->getMediasData(), $data);
        return $responder($data, self::class);
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
    private function checkAccessToSingleAction(Request $request): Trick
    {
        // Check if a trick can be retrieved thanks to its uuid
        $trick = $this->trickService->findSingleToShowByEncodedUuid($request->attributes->get('encodedUuid'));
        if (\is_null($trick)) {
            throw new NotFoundHttpException('Sorry, no trick was found due to wrong identifier!');
        }
        // Check access permissions to view this trick
        if (!$this->authorizationChecker->isGranted(TrickVoter::AUTHOR_OR_ADMIN_CAN_VIEW_UNPUBLISHED_TRICKS, $trick)) {
            throw new AccessDeniedException("Current user cannot view this unpublished trick!");
        }
        return $trick;
    }
}
