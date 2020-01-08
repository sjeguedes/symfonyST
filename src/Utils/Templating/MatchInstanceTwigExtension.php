<?php

declare(strict_types = 1);

namespace App\Utils\Templating;

use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

/*
 * Class MatchInstanceTwigExtension.
 *
 * Create a Twig function extension to match an expected object instance in template.
 *
 * // TODO: caution!! This is not used in app yet - make a decision to keep or remove class!
 */
class MatchInstanceTwigExtension extends AbstractExtension
{
    /**
     * Get Twig function.
     *
     * @return array
     */
    public function getFunctions() : array
    {
        return [
            new TwigFunction(
                'match_instance',
                [$this, 'matchInstance']
            ),
        ];
    }

    /**
     * Match a particular class name.
     *
     * @param $object an instance of objects declared in expected array
     *
     * @return string|null
     *
     * @throws \ReflectionException
     */
    public function matchInstance($object) : ?string
    {
        $className = (new \ReflectionClass($object))->getName();
        // Declare tested classes here!
        $expectedInstances = [
            'InvalidCsrfTokenException' => 'Symfony\\Component\\Security\\Core\\Exception\\InvalidCsrfTokenException'
        ];
        foreach ($expectedInstances as $name => $instance) {
            if ($className === $instance) {
                return $name;
            }
        }
        return null;
    }
}
