<?php

declare(strict_types = 1);

namespace App\Domain\DTO;

/**
 * Class RenewPasswordDTO.
 *
 * Data Transfer Object used to renew a personal password.
 *
 * @see validation constraints RenewPasswordDTO.yaml file
 */
final class RenewPasswordDTO
{
    /**
     * @var string|null
     */
    private $userName;

    /**
     * @var string|null
     */
    private $passwords;

    /**
     * RenewPasswordDTO constructor.
     *
     * @param string|null $userName
     * @param string|null $passwords
     */
    public function __construct(string $userName = null, string $passwords = null)
    {
        $this->userName = $userName;
        $this->passwords = $passwords;
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
    public function getPasswords() : ?string
    {
        return $this->passwords;
    }

    /**
     * @param string|null $userName
     *
     * @return void
     */
    public function setUserName(?string $userName) : void
    {
        $this->userName = $userName;
    }

    /**
     * @param string|null $passwords
     *
     * @return void
     */
    public function setPasswords(?string $passwords) : void
    {
        $this->passwords = $passwords;
    }
}
