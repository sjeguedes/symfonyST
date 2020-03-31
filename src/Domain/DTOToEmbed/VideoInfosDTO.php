<?php

declare(strict_types = 1);

namespace App\Domain\DTOToEmbed;

use App\Domain\DTO\AbstractReadableDTO;

/**
 * Class VideoInfosDTO.
 *
 * Data Transfer Object used to create a new video.
 *
 * Please note this DTO purpose is used in Collection as data class.
 *
 * @see validation constraints VideoInfosDTO.yaml file
 */
final class VideoInfosDTO extends AbstractReadableDTO
{
    /**
     * @var string|null
     */
    private $url;

    /**
     * @var string|null
     */
    private $description;

    /**
     * @var int
     */
    private $showListRank;

    /**
     * VideoInfosDTO constructor.
     *
     * @param string|null $url
     * @param string|null $description
     * @param int         $showListRank
     */
    public function __construct(
        ?string $url,
        ?string $description,
        int $showListRank
    ) {
        $this->url = $url;
        $this->description = $description;
        $this->showListRank = $showListRank;
    }

    /**
     * @return string|null
     */
    public function getUrl() : ?string
    {
        return $this->url;
    }

    /**
     * @return string|null
     */
    public function getDescription() : ?string
    {
        return $this->description;
    }

    /**
     * @return int
     */
    public function getShowListRank() : int
    {
        return $this->showListRank;
    }
}
