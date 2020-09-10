<?php

declare(strict_types=1);

namespace App\Responder;

use App\Service\Templating\TemplateBlockRendererInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class TemplateBlockResponder.
 *
 * Manage a response with block html content based on ajax compiled data,
 * to add an HTML element to an existing list.
 */
final class TemplateBlockResponder
{
    /**
     * @var TemplateBlockRendererInterface
     */
    private $renderer;

    /**
     * TemplateBlockResponder constructor.
     *
     * @param TemplateBlockRendererInterface $renderer avoid coupling with template engine
     *
     * @return void
     */
    public function __construct(TemplateBlockRendererInterface $renderer)
    {
        $this->renderer = $renderer;
    }

    /**
     * Invokable Responder with Magic method.
     *
     * @param array  $data
     * @param string $actionClassName
     *
     * @return Response
     */
    public function __invoke(array $data, string $actionClassName): Response
    {
        // Render a template block
        $template = $this->renderer->getTemplateBlock($actionClassName)['template'];
        $block = $this->renderer->getTemplateBlock($actionClassName)['block'];
        $response = new Response($this->renderer->renderTemplateBlock($template, $block, $data));
        $response->headers->set('Content-Type', 'text/html');
        return $response;
    }
}
