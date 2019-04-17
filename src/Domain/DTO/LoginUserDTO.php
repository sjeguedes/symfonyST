<?php

declare(strict_types = 1);

namespace App\Domain\DTO;

/**
 * Class LoginUserDTO.
 *
 * Data Transfer Object used to search and connect an authenticated user.
 */
final class LoginUserDTO
{
    /**
     * @var string
     */
    private $userName;

    /**
     * @var string
     */
    private $password;

    /**
     * @var bool
     */
    private $rememberMe;

    /**
     * LoginUserDTO constructor.
     *
     * @param string $userName
     * @param string $password
     * @param bool   $rememberMe
     *
     * @return void
     */
    public function __construct(
        string $userName,
        string $password,
        bool $rememberMe
    ) {
        $this->userName = $userName;
        $this->password = $password;
        $this->rememberMe = $rememberMe;
    }

    /**
     * @return string
     */
    public function getUserName() : string
    {
        return $this->userName;
    }

    /**
     * @return string
     */
    public function getPassword() : string
    {
        return $this->password;
    }

    /**
     * @return bool
     */
    public function getRememberMe() : bool
    {
        return $this->rememberMe;
    }
}