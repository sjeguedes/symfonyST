<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use App\Domain\Entity\Comment;

/**
 * Class CreateCommentDTO.
 *
 * Data Transfer Object used to create a trick comment.
 *
 * @see validation constraints CreateCommentDTO.yaml file
 */
final class CreateCommentDTO
{
    /**
     * @var Comment|null
     */
    private $parentComment;

    /**
     * @var string
     */
    private $content;

    /**
     * CreateCommentDTO constructor.
     *
     * @param Comment|null $parentComment
     * @param string|null  $content
     *
     * @return void
     */
    public function __construct(
        ?Comment $parentComment,
        ?string $content
    ) {
        $this->parentComment = $parentComment;
        $this->content = $content;
    }

    /**
     * @return Comment|null
     */
    public function getParentComment(): ?Comment
    {
        return $this->parentComment;
    }

    /**
     * @return string|null
     */
    public function getContent(): ?string
    {
        return $this->content;
    }
}
