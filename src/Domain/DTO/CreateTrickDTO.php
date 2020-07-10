<?php

declare(strict_types = 1);

namespace App\Domain\DTO;

use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\DTOToEmbed\VideoInfosDTO;
use App\Domain\Entity\TrickGroup;
use App\Service\Form\Collection\DTOCollection;

/**
 * Class CreateTrickDTO.
 *
 * Data Transfer Object used to create a new trick.
 *
 * Please note slug property will be generated using name property.
 *
 * @see validation constraints CreateTrickDTO.yaml file
 * @see Possible custom collection usage instead of array: https://dev.to/drearytown/collection-objects-in-php-1cbk
 * @see Another collection class example : https://www.sitepoint.com/collection-classes-in-php/
 */
final class CreateTrickDTO
{
    /**
     * @var TrickGroup|null
     */
    private $group;

    /**
     * @var string|null
     */
    private $name;

    /**
     * @var string|null
     */
    private $description;

    /**
     * @var DTOCollection|ImageToCropDTO[]|null
     */
    private $images;

    /**
     * @var DTOCollection|VideoInfosDTO[]|null
     */
    private $videos;

    /**
     * @var bool|null
     */
    private $isPublished;

    /**
     * CreateTrickDTO constructor.
     *
     * @param TrickGroup|null             $group       a trick group which is a kind of category
     * @param string|null                 $name        a unique trick title
     * @param string|null                 $description a text which describes the trick
     * @param array|ImageToCropDTO[]|null $images      a collection of image data (crop JSON data, preview data URI, identifier reference...)
     * @param array|VideoInfosDTO[]|null  $videos      a collection of video URL strings to be used in iframe HTML element based on video type (Youtube, DailyMotion, Vimeo...)
     * @param bool|null                   $isPublished a publication state which an administrator can change
     *
     * @throws \Exception
     */
    public function __construct(
        ?TrickGroup $group,
        ?string $name,
        ?string $description,
        ?array $images,
        ?array $videos,
        ?bool $isPublished = false
    ) {
        $this->group = $group;
        $this->name = $name;
        $this->description = $description;
        $this->images = new DTOCollection($images);
        $this->videos = new DTOCollection($videos);
        $this->isPublished = $isPublished;
    }

    /**
     * @return TrickGroup|null
     */
    public function getGroup() : ?TrickGroup
    {
        return $this->group;
    }

    /**
     * @return string|null
     */
    public function getName() : ?string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getDescription() : ?string
    {
        return $this->description;
    }

    /**
     * @return DTOCollection|ImageToCropDTO[]|null
     */
    public function getImages() : ?DTOCollection
    {
        return $this->images;
    }

    /**
     * @return DTOCollection|VideoInfosDTO[]|null
     */
    public function getVideos() : ?DTOCollection
    {
        return $this->videos;
    }

    /**
     * @return bool|null
     */
    public function getIsPublished() : ?bool
    {
        return $this->isPublished;
    }

    // TODO: complete setters for Trick update

    /**
     * @param array|null $images
     *
     * @return CreateTrickDTO
     *
     * @throws \Exception
     */
    public function setImages(?array $images) : self
    {
        $this->images = new DTOCollection($images);
        return $this;
    }

    /**
     * @param array|null $videos
     *
     * @return CreateTrickDTO
     *
     * @throws \Exception
     */
    public function setVideos(?array $videos) : self
    {
        $this->videos = new DTOCollection($videos);
        return $this;
    }
}
