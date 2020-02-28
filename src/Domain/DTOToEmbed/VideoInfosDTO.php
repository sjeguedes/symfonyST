<?php

declare(strict_types = 1);

namespace App\Domain\DTOToEmbed;

/**
 * Class VideoInfosDTO.
 *
 * Data Transfer Object used to create a new video.
 *
 * Please note this DTO purpose is used in Collection as data class.
 *
 * @see validation constraints VideoInfosDTO.yaml file
 */
final class VideoInfosDTO
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
     * VideoInfosDTO constructor.
     *
     * @param string|null $url
     * @param string|null $description
     */
    public function __construct(
        string $url = null,
        string $description = null
    ) {
        $this->url = $url;
        $this->description = $description;
    }

    /**
     * @return string|null
     */
    public function getImage() : ?string
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
}
