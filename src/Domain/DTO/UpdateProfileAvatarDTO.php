<?php

declare(strict_types = 1);

namespace App\Domain\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class UpdateProfileAvatarDTO.
 *
 * Data Transfer Object used to update a user profile avatar.
 *
 * @see validation constraints UpdateProfileAvatarDTO.yaml file
 */
final class UpdateProfileAvatarDTO extends AbstractReadableDTO
{
    /**
     * @var UploadedFile|null
     */
    private $avatar;

    /**
     * @var bool
     */
    private $removeAvatar;

    /**
     * @var string|null
     */
    private $cropJSONData;

    /**
     * RegisterUserDTO constructor.
     *
     * @param UploadedFile|null $avatar
     * @param bool              $removeAvatar
     * @param string|null       $cropJSONData
     */
    public function __construct(
        UploadedFile $avatar = null,
        bool $removeAvatar = false,
        string $cropJSONData = null
    ) {
        $this->avatar = $avatar;
        $this->removeAvatar = $removeAvatar;
        $this->cropJSONData = $cropJSONData;
    }

    /**
     * @return UploadedFile|null
     */
    public function getAvatar() : ?UploadedFile
    {
        return $this->avatar;
    }

    /**
     * @return bool
     */
    public function getRemoveAvatar() : bool
    {
        return $this->removeAvatar;
    }

    /**
     * @return string|null
     */
    public function getCropJSONData() : ?string
    {
        return $this->cropJSONData;
    }

    /**
     * @param UploadedFile|null $avatar
     *
     * @return UpdateProfileAvatarDTO
     */
    public function setAvatar(?UploadedFile $avatar) : self
    {
        $this->avatar = $avatar;
        return $this;
    }

    /**
     * @param bool $removeAvatar
     *
     * @return UpdateProfileAvatarDTO
     */
    public function setRemoveAvatar(bool $removeAvatar) : self
    {
        $this->removeAvatar = $removeAvatar;
        return $this;
    }

    /**
     * @param string|null
     *
     * @return UpdateProfileAvatarDTO
     */
    public function setCropJSONData(?string $cropJSONData) : self
    {
        $this->cropJSONData = $cropJSONData;
        return $this;
    }
}
