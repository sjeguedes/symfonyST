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
     * @var string
     */
    private $userName;

    /**
     * @var string|null
     */
    private $passwords;

    /**
     * RenewPasswordDTO constructor.
     *
     * @param string      $userName
     * @param string|null $passwords
     */
    public function __construct(string $userName, ?string $passwords)
    {
        $this->userName = $userName;
        $this->passwords = $passwords;
    }

    /**
     * @return string
     */
    public function getUserName() : string
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
}
