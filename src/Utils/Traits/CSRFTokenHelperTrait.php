<?php

declare(strict_types = 1);

namespace App\Utils\Traits;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Trait CSRFTokenHelperTrait.
 *
 * Enable use of CSRF token validation.
 */
trait CSRFTokenHelperTrait
{
    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;

    /**
     * Check if a CSRF token is valid for a dedicated form.
     *
     * @param string      $tokenId
     * @param string|null $token
     *
     * @return bool
     */
    public function isCSRFTokenValid(string $tokenId, ?string $token): bool
    {
        // Check validity
        $csrfToken = new CsrfToken($tokenId, $token);
        return $this->csrfTokenManager->isTokenValid($csrfToken);
    }
}
