<?php

declare(strict_types = 1);

namespace App\Domain\DTO;

use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Class UpdateProfileDTO.
 *
 * Data Transfer Object used to register a new user.
 *
 * @see validation constraints UpdateProfileDTO.yaml file
 */
final class UpdateProfileDTO extends AbstractReadableDTO
{
    /**
     * @var string|null
     */
    private $familyName;

    /**
     * @var string|null
     */
    private $firstName;

    /**
     * @var string|null
     */
    private $userName;

    /**
     * @var string|null
     */
    private $email;

    /**
     * @var string|null
     */
    private $passwords;

    /**
     * @var UploadedFile|null
     */
    private $avatar;

    /**
     * @var bool
     */
    private $removeAvatar;

    /**
     * RegisterUserDTO constructor.
     *
     * @param string|null       $familyName
     * @param string|null       $firstName
     * @param string|null       $userName
     * @param string|null       $email
     * @param string|null       $passwords
     * @param UploadedFile|null $avatar
     * @param bool              $removeAvatar
     */
    public function __construct(
        ?string $familyName,
        ?string $firstName,
        ?string $userName,
        ?string $email,
        string $passwords = null,
        UploadedFile $avatar = null,
        bool $removeAvatar = false
    ) {
        $this->familyName = $familyName;
        $this->firstName = $firstName;
        $this->userName = $userName;
        $this->email = $email;
        $this->passwords = $passwords;
        $this->avatar = $avatar;
        $this->removeAvatar = $removeAvatar;
    }

    /**
     * @return string|null
     */
    public function getFamilyName() : ?string
    {
        return $this->familyName;
    }

    /**
     * @return string|null
     */
    public function getFirstName() : ?string
    {
        return $this->firstName;
    }

    /**
     * @return string|null
     */
    public function getUserName() : ?string
    {
        return $this->userName;
    }

    /**
     * @return string|null
     */
    public function getEmail() : ?string
    {
        return $this->email;
    }

    /**
     * @return string|null
     */
    public function getPasswords() : ?string
    {
        return $this->passwords;
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
     * @param string|null $familyName
     *
     * @return UpdateProfileDTO
     */
    public function setFamilyName(?string $familyName) : self
    {
        $this->familyName = $familyName;
        return $this;
    }

    /**
     * @param string|null $firstName
     *
     * @return UpdateProfileDTO
     */
    public function setFirstName(?string $firstName) : self
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @param string|null $nickName
     *
     * @return UpdateProfileDTO
     */
    public function setUserName(?string $nickName) : self
    {
        $this->userName = $nickName;
        return $this;
    }

    /**
     * @param string|null $email
     *
     * @return UpdateProfileDTO
     */
    public function setEmail(?string $email) : self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @param string|null $passwords
     *
     * @return UpdateProfileDTO
     */
    public function setPasswords(?string $passwords) : self
    {
        $this->passwords = $passwords;
        return $this;
    }

    /**
     * @param UploadedFile $avatar
     *
     * @return UpdateProfileDTO
     */
    public function setAvatar(?UploadedFile $avatar) : self
    {
        $this->avatar = $avatar;
        return $this;
    }

    /**
     * @param bool $removeAvatar
     *
     * @return UpdateProfileDTO
     */
    public function setRemoveAvatar(bool $removeAvatar) : self
    {
        $this->removeAvatar = $removeAvatar;
        return $this;
    }
}
