<?php

declare(strict_types=1);

namespace App\Domain\DTO;

/**
 * Class UpdateProfileInfosDTO.
 *
 * Data Transfer Object used to update user profile infos (without avatar).
 *
 * @see validation constraints UpdateProfileInfosDTO.yaml file
 */
final class UpdateProfileInfosDTO extends AbstractReadableDTO
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
     * RegisterUserDTO constructor.
     *
     * @param string|null       $familyName
     * @param string|null       $firstName
     * @param string|null       $userName
     * @param string|null       $email
     * @param string|null       $passwords
     */
    public function __construct(
        ?string $familyName,
        ?string $firstName,
        ?string $userName,
        ?string $email,
        string $passwords = null
    ) {
        $this->familyName = $familyName;
        $this->firstName = $firstName;
        $this->userName = $userName;
        $this->email = $email;
        $this->passwords = $passwords;
    }

    /**
     * @return string|null
     */
    public function getFamilyName(): ?string
    {
        return $this->familyName;
    }

    /**
     * @return string|null
     */
    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    /**
     * @return string|null
     */
    public function getUserName(): ?string
    {
        return $this->userName;
    }

    /**
     * @return string|null
     */
    public function getEmail(): ?string
    {
        return $this->email;
    }

    /**
     * @return string|null
     */
    public function getPasswords(): ?string
    {
        return $this->passwords;
    }

    /**
     * @param string|null $familyName
     *
     * @return UpdateProfileInfosDTO
     */
    public function setFamilyName(?string $familyName): self
    {
        $this->familyName = $familyName;
        return $this;
    }

    /**
     * @param string|null $firstName
     *
     * @return UpdateProfileInfosDTO
     */
    public function setFirstName(?string $firstName): self
    {
        $this->firstName = $firstName;
        return $this;
    }

    /**
     * @param string|null $nickName
     *
     * @return UpdateProfileInfosDTO
     */
    public function setUserName(?string $nickName): self
    {
        $this->userName = $nickName;
        return $this;
    }

    /**
     * @param string|null $email
     *
     * @return UpdateProfileInfosDTO
     */
    public function setEmail(?string $email): self
    {
        $this->email = $email;
        return $this;
    }

    /**
     * @param string|null $passwords
     *
     * @return UpdateProfileInfosDTO
     */
    public function setPasswords(?string $passwords): self
    {
        $this->passwords = $passwords;
        return $this;
    }
}
