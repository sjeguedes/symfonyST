<?php

declare(strict_types = 1);

namespace App\Domain\DTO;

/**
 * Class RequestNewPasswordDTO.
 *
 * Data Transfer Object used to send password renewal token (by generating a personal back link) to user.
 *
 * @see validation constraints RequestNewPasswordDTO.yaml file
 */
final class RequestNewPasswordDTO
{
    /**
     * @var string|null
     */
    private $userName;

    /**
     * RequestNewPasswordDTO constructor.
     *
     * @param string|null $userName
     *
     * @return void
     */
    public function __construct(?string $userName)
    {
        $this->userName = $userName;
    }

    /**
     * @return string|null
     */
    public function getUserName() : ?string
    {
        return $this->userName;
    }
}
