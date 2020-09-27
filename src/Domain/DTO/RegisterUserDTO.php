<?php

declare(strict_types=1);

namespace App\Domain\DTO;

/**
 * Class RegisterUserDTO.
 *
 * Data Transfer Object used to register a new user.
 *
 * @see validation constraints RegisterUserDTO.yaml file
 */
final class RegisterUserDTO
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
     * @var bool
     */
    private $agreement;

    /**
     * RegisterUserDTO constructor.
     *
     * @param string|null       $familyName
     * @param string|null       $firstName
     * @param string|null       $userName
     * @param string|null       $email
     * @param string|null       $passwords
     * @param bool              $agreement
     */
    public function __construct(
        ?string $familyName,
        ?string $firstName,
        ?string $userName,
        ?string $email,
        ?string $passwords,
        bool $agreement
    ) {
        $this->familyName = $familyName;
        $this->firstName = $firstName;
        $this->userName = $userName;
        $this->email = $email;
        $this->passwords = $passwords;
        $this->agreement = $agreement;
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
     * @return bool
     */
    public function getAgreement(): bool
    {
        return $this->agreement;
    }
}
