<?php

declare(strict_types = 1);

namespace App\Action;

use App\Domain\Entity\Comment;
use App\Domain\ServiceLayer\CommentManager;
use Ramsey\Uuid\UuidInterface;

/**
 * Class AbstractCommentListAction.
 *
 * Manage trick single page comment list for initial display and AJAX "load more" functionality.
 */
abstract class AbstractCommentListAction
{
    /**
     * @var CommentManager
     */
    protected $commentService;

    /**
     * @var array
     */
    private $commentListData;

    /**
     * AbstractCommentListAction constructor.
     *
     * @param CommentManager $commentService
     */
    public function __construct(CommentManager $commentService)
    {
        $this->commentService = $commentService;
        $this->commentListData = [
            'commentLoadingMode'        => Comment::COMMENT_LOADING_MODE,
            'commentNumberPerLoading'   => Comment::COMMENT_NUMBER_PER_LOADING,
            'listEnded'                 => 'No more comment to load!',
            'noList'                    => 'No comment exists for this trick at this time!',
            'technicalError'            => 'Sorry, something wrong happened' . "\n" .
                                           'during comment list loading!' . "\n" .
                                           'Please contact us or try again later.' . "\n",
            'trickCommentCreationError' => null
        ];
    }

    /**
     * Get comment list necessary data.
     */
    protected function getCommentListData() : array
    {
        return $this->commentListData;
    }

    /**
     * Retrieve trick comments list to show.
     *
     * @param $trickUuid
     * @param $offset
     * @param $limit
     * @param $commentLoadingMode
     *
     * @return array
     *
     * @throws \Exception
     */
    protected function prepareTrickCommentsListWithRanks(
        UuidInterface $trickUuid,
        int $offset,
        int $limit,
        string $commentLoadingMode
    ) : ?array
    {
        // Get comments filtered list
        $selectedTrickComments = $this->commentService->findOnesByTrickWithOffsetLimit(
            $trickUuid,
            $offset,
            $limit,
            $commentLoadingMode,
            true
        );
        // No results were found!
        if (0 === $selectedTrickComments->count()) {
            throw new \UnexpectedValueException('Trick comments list can not be retrieved due to wrong parameters!');
        }
        // Get uuid data with a second simple query to use it for comparison
        $trickCommentsUuidData = $this->commentService->findOnesByTrick($trickUuid, $commentLoadingMode, true);
        // Add comment ranks to filtered comments
        $selectedTrickComments = $this->commentService->addRanksToTrickComments(
            $trickCommentsUuidData,
            $selectedTrickComments,
            $commentLoadingMode
        );
        // Return needed data
        return [
            'commentsTotalCount'   => \count($trickCommentsUuidData),
            'commentListWithRanks' => $selectedTrickComments
        ];
    }
}
