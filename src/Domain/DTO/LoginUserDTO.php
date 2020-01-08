<?php

declare(strict_types = 1);

namespace App\Domain\DTO;

/**
 * Class LoginUserDTO.
 *
 * Data Transfer Object used to search and connect an authenticated user.
 *
 * @see validation constraints LoginUserDTO.yaml file
 */
final class LoginUserDTO
{
    /**
     * @var string|null
     */
    private $userName;

    /**
     * @var string|null
     */
    private $password;

    /**
     * @var bool
     */
    private $rememberMe;

    /**
     * LoginUserDTO constructor.
     *
     * @param string|null $userName
     * @param string|null $password
     * @param bool        $rememberMe
     *
     * @return void
     */
    public function __construct(
        ?string $userName,
        ?string $password,
        bool $rememberMe
    ) {
        $this->userName = $userName;
        $this->password = $password;
        $this->rememberMe = $rememberMe;
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
    public function getPassword() : ?string
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
