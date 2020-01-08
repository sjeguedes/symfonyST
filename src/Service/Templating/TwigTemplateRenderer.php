<?php

declare(strict_types = 1);

namespace App\Service\Templating;

use App\Utils\Traits\TwigHelperTrait;
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
     * @param Environment $twig
     *
     * @return void
     */
    public function __construct(Environment $twig)
    {
        $this->templateRenderer = $twig;
        $this->templates = [
            [
                'class' => 'App\\Responder\\AjaxTrickListResponder',
                'name'  => 'home/trick_list.html.twig',
                'block' => 'trick_cards'
            ],
            [
                'class' => 'App\\Responder\\HomeTrickListResponder',
                'name'  => 'home/trick_list.html.twig'
            ],
            [
                'class' => 'App\\Responder\\PaginatedTrickListResponder',
                'name'  => 'tricks/paginated_list.html.twig'
            ],
            [
                'class' => 'App\\Responder\\SingleTrickResponder',
                'name'  => 'single-trick/trick.html.twig'
            ],
            [
                'class' => 'App\\Responder\\Admin\\LoginResponder',
                'name'  => 'admin/login.html.twig'
            ],
            [
                'class' => 'App\\Responder\\Admin\\RequestNewPasswordResponder',
                'name'  => 'admin/request_new_password.html.twig'
            ],
            [
                'class' => 'App\\Responder\\Admin\\RenewPasswordResponder',
                'name'  => 'admin/renew_password.html.twig'
            ],
            [
                'class' => 'App\\Responder\\Admin\\RegisterResponder',
                'name'  => 'admin/register.html.twig'
            ],
            [
                'class' => 'App\\Responder\\Admin\\UpdateProfileResponder',
                'name'  => 'admin/update_profile.html.twig'
            ],
            // Emails
            [
                'class' => 'App\\Action\\Admin\\RequestNewPasswordAction',
                'name'  => 'admin/mailing/mail_request_new_password.html.twig'
            ],
            [
                'class' => 'App\\Action\\Admin\\RenewPasswordAction',
                'name'  => 'admin/mailing/mail_renew_password.html.twig'
            ],
            [
                'class' => 'App\\Action\\Admin\\RegisterAction',
                'name'  => 'admin/mailing/mail_register.html.twig'
            ]
        ];
    }

    /**
     * Render a Twig engine template.
     *
     * @param string $template
     * @param array $data
     *
     * @return string
     *
     * @throws \Twig\Error\LoaderError
     * @throws \Twig\Error\RuntimeError
     * @throws \Twig\Error\SyntaxError
     */
    public function renderTemplate(string $template, array $data) : string
    {
        return $this->templateRenderer->render($template, $data);
    }

    /**
     * Retrieve the Twig template name to show.
     *
     * @param string $className a fully qualified name based on action (for email templates) or responder class
     *
     * @return string
     *
     * @see https://stackoverflow.com/questions/22407370/how-to-check-if-class-exists-within-a-namespace
     * @see https://stackoverflow.com/questions/15839292/is-there-a-namespace-aware-alternative-to-phps-class-exists?noredirect=1&lq=1
     */
    public function getTemplate(string $className) : string
    {
        if (!class_exists($className)) {
            throw new \InvalidArgumentException('Template can not be rendered: mandatory class does not exist!');
        }
        $data = '';
        $i = 0;
        foreach ($this->templates as $template) {
            ++ $i;
            $hasBlock = !isset($template['block']) ? false : true;
            $isMatched = $className !== $template['class'] ? false : true;
            if ($i === \count($this->templates) && false === $isMatched) {
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
            throw new \InvalidArgumentException('Template block can not be rendered: mandatory class does not exist!');
        }
        $data = [];
        $i = 0;
        foreach ($this->templates as $template) {
            ++ $i;
            $hasBlock = !isset($template['block']) ? false : true;
            $isMatched = $className !== $template['class'] ? false : true;
            if ($i === \count($this->templates) && false === $isMatched) {
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
