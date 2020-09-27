<?php

declare(strict_types=1);

namespace App\Service\Templating;

use App\Utils\Traits\TwigHelperTrait;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Yaml\Yaml;
use Twig\Environment;

/**
 * Class TwigTemplateRenderer.
 *
 * Render a Twig template or block.
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
     * @param ParameterBagInterface $parameterBag
     * @param Environment           $twig
     *
     */
    public function __construct(ParameterBagInterface $parameterBag, Environment $twig)
    {
        $this->templateRenderer = $twig;
        // Get template list data in particular .yaml file
        $yamlFilePath = $parameterBag->get('app_template_list_yaml_dir');
        $this->templates = Yaml::parseFile( $yamlFilePath . 'template_list.yaml');
    }

    /**
     * Render a Twig engine template.
     *
     * @param string $template
     * @param array  $data
     * @param bool   $isEmail
     *
     * @return string
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function renderTemplate(string $template, array $data, bool $isEmail = false): string
    {
        return $this->templateRenderer->render($template, $data);
    }

    /**
     * Retrieve the Twig template name to show.
     *
     * @param string $actionClassName a fully qualified name based on action class
     * @param bool   $isEmail         an indicator to distinct email templates
     *
     * @return string
     *
     * @see https://stackoverflow.com/questions/22407370/how-to-check-if-class-exists-within-a-namespace
     * @see https://stackoverflow.com/questions/15839292/is-there-a-namespace-aware-alternative-to-phps-class-exists?noredirect=1&lq=1
     */
    public function getTemplate(string $actionClassName, bool $isEmail = false): string
    {
        if (!class_exists($actionClassName)) {
            throw new \InvalidArgumentException('Template cannot be rendered: mandatory class does not exist!');
        }
        $data = '';
        $i = 0;
        // Check template type to find (email or page/block template)
        $templates = !$isEmail ? $this->templates['page_templates'] : $this->templates['email_templates'];
        foreach ($templates as $template) {
            ++$i;
            $hasBlock = !isset($template['block']) ? false : true;
            $isMatched = $actionClassName !== $template['class'] ? false : true;
            if ($i === \count($templates) && false === $isMatched) {
                throw new \RuntimeException('No template name was found: try to use another rendering method!');
            }
            if (true === $hasBlock || false === $isMatched) {
                continue;
            }
            $data = $template['name'];
            break;
        }
        return $data;
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
    public function renderTemplateBlock(string $template, string $block, array $data): string
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
     * @param string $actionClassName a fully qualified class name base on action class
     *
     * @return array an associative array which contains template name and template block name
     */
    public function getTemplateBlock(string $actionClassName): array
    {
        if (!class_exists($actionClassName)) {
            throw new \InvalidArgumentException('Template block cannot be rendered: mandatory class does not exist!');
        }
        $data = [];
        $i = 0;
        $templates = $this->templates['page_templates'];
        foreach ($templates as $template) {
            ++$i;
            $hasBlock = !isset($template['block']) ? false : true;
            $isMatched = $actionClassName !== $template['class'] ? false : true;
            if ($i === \count($templates) && false === $isMatched) {
                throw new \RuntimeException('No template block name was found: try to use another rendering method!');
            }
            if (false === $hasBlock || false === $isMatched) {
                continue;
            }
            $data = [
                'template' => $template['name'],
                'block' => $template['block']
            ];
            break;
        }
        return $data;
    }
}
