<?php

declare(strict_types = 1);

namespace App\Form\Handler;


interface FormHandlerInterface
{
    /**
     * Check if a CSRF token is valid for a dedicated form.
     *
     * @param string $tokenId
     * @param string $token
     *
     * @return bool
     */
    public function isCSRFTokenValid(string $tokenId, string $token) : bool;
}