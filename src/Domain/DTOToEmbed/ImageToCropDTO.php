<?php

declare(strict_types = 1);

namespace App\Domain\DTOToEmbed;

use App\Domain\DTO\AbstractReadableDTO;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class ImageToCropDTO.
 *
 * Data Transfer Object used to create a new cropped image.
 *
 * Please note this DTO purpose is used in Collection as data class.
 *
 * @see validation constraints ImageToCropDTO.yaml file
 */
final class ImageToCropDTO extends AbstractReadableDTO
{
    /**
     * @var UploadedFile|null
     */
    private $image;

    /**
     * @var string|null
     */
    private $description;

    /**
     * @var string|null
     */
    private $cropJSONData;

    /**
     * @var string|null
     */
    private $imagePreviewDataURI;

    /**
     * @var string|null
     */
    private $savedImageName;

    /**
     * @var int
     */
    private $showListRank;

    /**
     * @var bool
     */
    private $isMain;

    /**
     * ImageToCropDTO constructor.
     *
     * @param UploadedFile|null $image
     * @param string|null       $description
     * @param string|null       $cropJSONData
     * @param string|null       $imagePreviewDataURI
     * @param string|null       $savedImageName
     * @param int               $showListRank
     * @param bool              $isMain
     */
    public function __construct(
        ?UploadedFile $image,
        ?string $description,
        ?string $cropJSONData,
        ?string $imagePreviewDataURI,
        ?string $savedImageName,
        int $showListRank,
        bool $isMain = false
    ) {
        $this->image = $image;
        $this->description = $description;
        $this->imagePreviewDataURI = $imagePreviewDataURI;
        $this->cropJSONData = $cropJSONData;
        $this->savedImageName = $savedImageName;
        $this->showListRank = $showListRank;
        $this->isMain = $isMain;
    }

    /**
     * @return UploadedFile|null
     */
    public function getImage() : ?UploadedFile
    {
        return $this->image;
    }

    /**
     * @return string|null
     */
    public function getDescription() : ?string
    {
        return $this->description;
    }

    /**
     * @return string|null
     */
    public function getCropJSONData() : ?string
    {
        return $this->cropJSONData;
    }

    /**
     * @return string|null
     */
    public function getImagePreviewDataURI() : ?string
    {
        return $this->imagePreviewDataURI;
    }

    /**
     * @return string|null
     */
    public function getSavedImageName() : ?string
    {
        return $this->savedImageName;
    }

    /**
     * @return int
     */
    public function getShowListRank() : int
    {
        return $this->showListRank;
    }

    /**
     * @return bool
     */
    public function getIsMain() : bool
    {
        return $this->isMain;
    }
}
