<?php

declare(strict_types = 1);

namespace App\Responder;

use App\Service\Templating\TemplateBlockRendererInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AjaxTrickListResponder.
 *
 * Manage a response with html content block based on ajax data, to add more trick(s) to an existing list.
 */
final class AjaxTrickListResponder
{
    /**
     * @var TemplateBlockRendererInterface
     */
    private $renderer;

    /**
     * AjaxTrickListResponder constructor.
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
     * @param array $data
     *
     * @return Response
     *
     * @throws \Throwable
     */
    public function __invoke(array $data) : Response
    {
        // Render a template block
        $template = $this->renderer->getTemplateBlock(self::class)['template'];
        $block = $this->renderer->getTemplateBlock(self::class)['block'];
        $response = new Response($this->renderer->renderTemplateBlock($template, $block, $data));
        $response->headers->set('Content-Type', 'text/html');
        return $response;
    }
}