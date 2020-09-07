<?php

declare(strict_types=1);

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
     * @var string|null
     */
    private $savedVideoName;

    /**
     * @var int
     */
    private $showListRank;

    /**
     * VideoInfosDTO constructor.
     *
     * @param string|null $url
     * @param string|null $description
     * @param string|null $savedVideoName
     * @param int         $showListRank
     */
    public function __construct(
        ?string $url,
        ?string $description,
        ?string $savedVideoName,
        int $showListRank
    ) {
        $this->url = $url;
        $this->description = $description;
        $this->savedVideoName = $savedVideoName;
        $this->showListRank = $showListRank;
    }

    /**
     * @return string|null
     */
    public function getUrl(): ?string
    {
        return $this->url;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getSavedVideoName(): ?string
    {
        return $this->savedVideoName;
    }

    /**
     * @return int
     */
    public function getShowListRank(): int
    {
        return $this->showListRank;
    }

    /**
     * @param string|null $url
     *
     * @return VideoInfosDTO
     */
    public function setUrl(?string $url): self
    {
        $this->url = $url;
        return $this;
    }

    /**
     * @param string|null $description
     *
     * @return VideoInfosDTO
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @param string|null $savedVideoName
     *
     * @return VideoInfosDTO
     */
    public function setSavedVideoName(?string $savedVideoName): self
    {
        $this->savedVideoName = $savedVideoName;
        return $this;
    }

    /**
     * @param int $showListRank
     *
     * @return VideoInfosDTO
     */
    public function setShowListRank(int $showListRank): self
    {
        $this->showListRank = $showListRank;
        return $this;
    }
}
