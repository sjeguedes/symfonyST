<?php

declare(strict_types=1);

namespace App\Domain\DTO;

use App\Domain\DTOToEmbed\ImageToCropDTO;
use App\Domain\DTOToEmbed\VideoInfosDTO;
use App\Domain\Entity\TrickGroup;
use App\Service\Form\Collection\DTOCollection;

/**
 * Class UpdateTrickDTO.
 *
 * Data Transfer Object used to create a new trick.
 *
 * Please note slug property will be generated using name property.
 *
 * @see validation constraints UpdateTrickDTO.yaml file
 * @see Possible custom collection usage instead of array: https://dev.to/drearytown/collection-objects-in-php-1cbk
 * @see Another collection class example : https://www.sitepoint.com/collection-classes-in-php/
 */
final class UpdateTrickDTO extends AbstractReadableDTO
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
     * @var DTOCollection|ImageToCropDTO[]
     */
    private $images;

    /**
     * @var DTOCollection|VideoInfosDTO[]
     */
    private $videos;

    /**
     * @var bool|null
     */
    private $isPublished;

    /**
     * UpdateTrickDTO constructor.
     *
     * @param TrickGroup|null                $group       a trick group which is a kind of category
     * @param string|null                    $name        a unique trick title
     * @param string|null                    $description a text which describes the trick
     * @param DTOCollection|ImageToCropDTO[] $images      a collection of image data (crop JSON data, preview data URI, identifier reference...)
     * @param DTOCollection|VideoInfosDTO[]  $videos      a collection of video URL strings to be used in iframe HTML element
     *                                                    based on video type (Youtube, DailyMotion, Vimeo...)
     * @param bool|null                      $isPublished a publication state which an administrator can change
     *
     * @throws \Exception
     */
    public function __construct(
        ?TrickGroup $group,
        ?string $name,
        ?string $description,
        DTOCollection $images,
        DTOCollection $videos,
        ?bool $isPublished
    ) {
        $this->group = $group;
        $this->name = $name;
        $this->description = $description;
        $this->images = $images;
        $this->videos = $videos;
        $this->isPublished = $isPublished;
    }

    /**
     * @return TrickGroup|null
     */
    public function getGroup(): ?TrickGroup
    {
        return $this->group;
    }

    /**
     * @return string|null
     */
    public function getName(): ?string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getDescription(): ?string
    {
        return $this->description;
    }

    /**
     * @return DTOCollection|ImageToCropDTO[]
     */
    public function getImages(): DTOCollection
    {
        return $this->images;
    }

    /**
     * @return DTOCollection|VideoInfosDTO[]
     */
    public function getVideos(): DTOCollection
    {
        return $this->videos;
    }

    /**
     * @return bool|null
     */
    public function getIsPublished(): ?bool
    {
        return $this->isPublished;
    }

    /**
     * @param TrickGroup $group|null
     *
     * @return UpdateTrickDTO
     */
    public function setGroup(?TrickGroup $group): self
    {
        $this->group = $group;
        return $this;
    }

    /**
     * @param string $name|null
     *
     * @return UpdateTrickDTO
     */
    public function setName(?string $name): self
    {
        $this->name = $name;
        return $this;
    }

    /**
     * @param string $description|null
     *
     * @return UpdateTrickDTO
     */
    public function setDescription(?string $description): self
    {
        $this->description = $description;
        return $this;
    }

    /**
     * @param DTOCollection|ImageToCropDTO[] $images
     *
     * @return UpdateTrickDTO
     *
     * @throws \Exception
     */
    public function setImages(DTOCollection $images): self
    {
        $this->images = $images;
        return $this;
    }

    /**
     * @param DTOCollection|VideoInfosDTO[] $videos
     *
     * @return UpdateTrickDTO
     *
     * @throws \Exception
     */
    public function setVideos(DTOCollection $videos): self
    {
        $this->videos = $videos;
        return $this;
    }

    /**
     * @param bool|null $isPublished
     *
     * @return UpdateTrickDTO
     */
    public function setIsPublished(?bool $isPublished): self
    {
        $this->isPublished = $isPublished;
        return $this;
    }
}
