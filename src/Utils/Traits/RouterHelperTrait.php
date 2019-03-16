<?php

declare(strict_types=1);

namespace App\Utils\Traits;

use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Routing\RouterInterface;

/*
 * Trait RouteHelperTrait.
 *
 * Enable route management with router.
 */
trait RouterHelperTrait
{

    /**
     * @var RouterInterface
     */
    private $router;

    /**
     * Enable use of router.
     *
     * @param RouterInterface $router
     *
     * @return void
     */
    public function setRouter(RouterInterface $router)
    {
        $this->router = $router;
    }

    /**
     * Generate URL with route and parameters.
     *
     * @param string $route
     * @param array  $parameters
     * @param int    $referenceType
     *
     * @return string
     */
    public function generateURLFromRoute(
        string $route,
        array $parameters = [],
        $referenceType = UrlGeneratorInterface::ABSOLUTE_PATH
    ) : string {
        return $this->router->generate($route, $parameters, $referenceType);
    }
}
