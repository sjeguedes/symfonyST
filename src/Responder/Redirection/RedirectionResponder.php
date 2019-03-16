<?php

declare(strict_types=1);

namespace App\Responder\Redirection;

use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/**
 * Class RedirectionResponder.
 *
 * Manage a redirect response.
 */
final class RedirectionResponder
{
    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * RedirectionResponder constructor.
     *
     * @param RouterInterface $router
     *
     * @return void
     */
    public function __construct(RouterInterface $router)
    {
        $this->router = $router;
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
    ) : RedirectResponse {
        return new RedirectResponse($this->router->generate($route, $parameters, $referenceType));
    }
}