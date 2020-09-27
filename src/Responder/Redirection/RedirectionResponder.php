<?php

declare(strict_types=1);

namespace App\Responder\Redirection;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Class RedirectionResponder.
 *
 * Manage a redirect response.
 */
final class RedirectionResponder
{
    /**
     * @var UrlGeneratorInterface
     */
    private $urlGenerator;

    /**
     * RedirectionResponder constructor.
     *
     * @param UrlGeneratorInterface $urlGenerator
     *
     * @return void
     */
    public function __construct(UrlGeneratorInterface $urlGenerator)
    {
        $this->urlGenerator = $urlGenerator;
    }

    /**
     * Invokable Responder with Magic method.
     *
     * @param string $route
     * @param array  $parameters
     * @param int    $referenceType
     *
     * @return RedirectResponse
     */
    public function __invoke(
        string $route,
        array $parameters = [],
        $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ): RedirectResponse {
        return new RedirectResponse($this->urlGenerator->generate($route, $parameters, $referenceType));
    }
}
