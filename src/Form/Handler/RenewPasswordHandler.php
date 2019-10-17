<?php

declare(strict_types = 1);

namespace App\Form\Handler;

use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;

/**
 * Class RenewPasswordHandler.
 *
 * Handle the request to apply additional actions.
 */
class RenewPasswordHandler implements FormHandlerInterface
{
    /**
     * @var csrfTokenManagerInterface
     */
    private $csrfTokenManager;


    /**
     * RenewPasswordHandler constructor.
     *
     * @param CsrfTokenManagerInterface $csrfTokenManager
     *
     */
    public function __construct(csrfTokenManagerInterface $csrfTokenManager)
    {
        $this->csrfTokenManager = $csrfTokenManager;
    }

    /**
     * {@inheritdoc}
     *
     * Token id is the value used in the template to generate the token.
     */
    public function isCSRFTokenValid(string $tokenId, ?string $token) : bool
    {
        // Check validity
        $csrfToken = new CsrfToken($tokenId, $token);
        return $this->csrfTokenManager->isTokenValid($csrfToken);
    }
}
