<?php

namespace App\Responder\Templating;

use App\Utils\Traits\TwigHelperTrait;
use Twig\Environment;

/**
 * Class TwigTemplateRenderer.
 *
 * Render a Twig template or block
 */
final class TwigTemplateRenderer implements TemplateRendererInterface, TemplateBlockRendererInterface
{
    use TwigHelperTrait;

    /**
     * @var array
     */
    private $templates;

    /**
     * @var Environment
     */
    private $templateRenderer;

    /**
     * TwigTemplateRenderer constructor.
     *
     * @param Environment $twig
     *
     * @return void
     */
    public function __construct(Environment $twig)
    {
        $this->templateRenderer = $twig;
        $this->templates = [
            [
                'responder' => 'App\\Responder\\AjaxTrickListResponder',
                'name'      => 'home/trick_list.html.twig',
                'block'     => 'trick_cards'
            ],
            [
                'responder' => 'App\\Responder\\HomeTrickListResponder',
                'name'      => 'home/trick_list.html.twig'
            ],
            [
                'responder' => 'App\\Responder\\PaginatedTrickListResponder',
                'name'      => 'tricks/paginated_list.html.twig'
            ]
        ];
    }

    /**
     * Render a Twig engine template.
     *
     * @param string $template
     * @param array  $data
     *
     * @return string
     *
     * @throws \Twig_Error_Loader
     * @throws \Twig_Error_Runtime
     * @throws \Twig_Error_Syntax
     */
    public function renderTemplate(string $template, array $data) : string
    {
        return $this->templateRenderer->render($template, $data);
    }

    /**
     * Retrieve the Twig template name to show.
     *
     * @param string $className a fully qualified name based on responder class
     *
     * @return string
     *
     * @see https://stackoverflow.com/questions/22407370/how-to-check-if-class-exists-within-a-namespace
     * @see https://stackoverflow.com/questions/15839292/is-there-a-namespace-aware-alternative-to-phps-class-exists?noredirect=1&lq=1
     */
    public function getTemplate(string $className) : string
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException('Response can not be rendered: mandatory Responder does not exist!');
        }
        $isMatched = false;
        foreach ($this->templates as $template) {
            $hasBlock = !isset($template['block']) ? false : true;
            $isMatched = $className !== $template['responder'] ? false : true;
            if (true === $hasBlock || false === $isMatched) {
                continue;
            }
            return $template['name'];
        }
        if (false === $isMatched) {
            throw new \RuntimeException('No template name was found: try to use another rendering method!');
        }
    }

    /**
     * Render a Twig template block.
     *
     * @param string $template a template name
     * @param string $block a template block name
     * @param array  $data some data to pass to template
     *
     * @return string
     *
     * @throws \Throwable
     */
    public function renderTemplateBlock(string $template, string $block, array $data) : string
    {
        return $this->renderBlock($this->templateRenderer, $template, $block, $data);
    }

    /**
     * Retrieve the Twig template block name based on fully qualified class name
     * where template block rendering is called (e.g. Controller, Responder, or "Presenter" class name...)
     *
     * For instance, use an array to enable matching
     * between Responder (or other class) fully qualified class name (key) and template block name (value).
     *
     * @param string $className a fully qualified class name
     *
     * @return array an associative array which contains template name and template block name
     */
    public function getTemplateBlock(string $className) : array
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException('Response can not be rendered: mandatory Responder does not exist!');
        }
        $isMatched = false;
        foreach ($this->templates as $template) {
            $hasBlock = !isset($template['block']) ? false : true;
            $isMatched = $className !== $template['responder'] ? false : true;
            if (false === $hasBlock || false === $isMatched) {
                continue;
            }
            return [
                'template' => $template['name'],
                'block'    => $template['block']
            ];
        }
        if (false === $isMatched) {
            throw new \RuntimeException('No template block name was found: try to use another rendering method!');
        }
    }
}