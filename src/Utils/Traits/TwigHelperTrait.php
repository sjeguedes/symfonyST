<?php

declare(strict_types = 1);

namespace App\Utils\Traits;

use Twig\Environment;

/**
 * Trait TwigHelperTrait.
 *
 * Enable use of Twig particular templating.
 */
trait TwigHelperTrait
{
    /**
     * Enable Twig block template rendering.
     *
     * @param Environment $twig
     * @param string      $template
     * @param string      $block
     * @param array       $params
     *
     * @return string
     *
     * @throws \Throwable
     */
    public function renderBlock(Environment $twig, string $template, string $block, array $params) : string
    {
        // Get \Twig_TemplateWrapper $twigTemplate instance with Twig_Environment $twig
        $twigTemplate = $twig->load($template);
        return $twigTemplate->renderBlock($block, $params);
    }
}
