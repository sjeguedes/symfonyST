<?php

declare(strict_types=1);

namespace App\Action;

use App\Domain\Entity\Comment;
use App\Domain\ServiceLayer\CommentManager;
use App\Domain\ServiceLayer\MediaTypeManager;
use App\Responder\AjaxCommentListResponder;
use App\Utils\Traits\UuidHelperTrait;
use Psr\Log\LoggerAwareTrait;
use Psr\Log\LoggerInterface;
use Ramsey\Uuid\UuidInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

/**
 * Class AjaxCommentListAction.
 *
 * Manage trick single page comment list display for "load more" functionality.
 */
class AjaxCommentListAction extends AbstractCommentListAction
{
    use LoggerAwareTrait;
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
     * AjaxCommentListAction constructor.
     *
     * @param MediaTypeManager $mediaTypeService,
     * @param CommentManager   $commentService
     * @param LoggerInterface  $logger
     *
     * @return void
     */
    public function __construct(CommentManager $commentService, MediaTypeManager $mediaTypeService, LoggerInterface $logger)
    {
        parent::__construct($commentService, $mediaTypeService);
        $this->mediaTypeService = $mediaTypeService;
        $this->commentService = $commentService;
        $this->setLogger($logger);

    }

    /**
     * Load comments from AJAX request.
     *
     * Please not url is always the same even if language changed.
     * This is a simple AJAX request and locale parameter is null.
     *
     * @Route(
     *     "/load-trick-comments/{trickEncodedUuid<\w+>}/{offset?<\d+>}/{limit?<\d+>?}",
     *     name="load_trick_comments_offset_limit", methods={"GET"}
     * )
     *
     * @param AjaxCommentListResponder $responder
     * @param Request                  $request
     *
     * @return Response
     *
     * CAUTION! Update any URI change in:
     * @see LoginFormAuthenticationManager::onAuthenticationSuccess()
     *
     * @throws \Throwable
     */
    public function __invoke(AjaxCommentListResponder $responder, Request $request): Response
    {
        // Filter AJAX request
        if (!$request->isXmlHttpRequest()) {
            throw new AccessDeniedException('Access is not allowed without AJAX request!');
        }
        // Get request data
        $trickUuid = $this->decode($request->attributes->get('trickEncodedUuid'));
        $offset = (int) $request->attributes->get('offset');
        $limit = (int) $request->attributes->get('limit');
        // Get comments list with ranks and comments total count
        $selectedTrickCommentsData = $this->prepareTrickCommentsListWithRanks(
            $trickUuid,
            $offset,
            $limit,
            Comment::COMMENT_LOADING_MODE
        );
        // List is not up to date so re-init default list!
        if (!\is_null($listError = $this->checkOutdatedCommentList(
            $trickUuid,
            $selectedTrickCommentsData['commentsTotalCount']
        ))) {
            $selectedTrickCommentsData = $this->prepareTrickCommentsListWithRanks(
                $trickUuid,
                0,
                $limit,
                Comment::COMMENT_LOADING_MODE
            );
        }
        $data = [
            'ajaxMode'              => true,
            // Get total trick comment count
            'commentCount'          => $selectedTrickCommentsData['commentsTotalCount'],
            // Get list error by checking outdated comment count to reinitialize list
            'listError'             => $listError,
            'selectedTrickComments' => $selectedTrickCommentsData['commentListWithRanks']
        ];
        // Get complementary needed comment list and medias (avatar) data
        $data = array_merge($this->getCommentListData(), $this->getMediasData(), $data);
        return $responder($data);
    }

    /**
     * Check outdated trick comment list during loading,
     * if new comments were created or existing ones were deleted in-between.
     *
     * @param UuidInterface $trickUuid
     * @param int           $commentCount
     *
     * @return string|null an error notification message as "$listError"
     */
    private function checkOutdatedCommentList(UuidInterface $trickUuid, int $commentCount): ?string
    {
        $listError = null;
        if ($this->commentService->isCountAllOutdated($trickUuid, $commentCount)) {
            $this->logger->error(
                "[trace app SnowTricks] AjaxCommentListAction/__invoke => commentCount: $commentCount"
            );
            $listError = 'Trick comment list was reinitialized!' . "\n" .
                'Wrong total count is used' . "\n" .
                'due to outdated or unexpected value.';

        }
        return $listError;
    }
}
