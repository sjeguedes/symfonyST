<?php

namespace App\Utils\Traits;

trait TwigHelpersTrait
{
    /**
     * @param $template
     * @param $block
     * @param array $params
     *
     * @return string
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function renderBlock(string $template, $block, $params = array())
    {
        /** @var \Twig_Environment $twig */
        $twig = $this->get('twig');
        /** @var \Twig_Template $template */
        $template = $twig->loadTemplate($template);

        return $template->renderBlock($block, $twig->mergeGlobals($params));
    }
}
