<?php

declare(strict_types=1);

namespace App\Responder;

use App\Service\Templating\TemplateBlockRendererInterface;
use Symfony\Component\HttpFoundation\Response;

/**
 * Class AbstractAjaxListResponder.
 *
 * Manage a response with block html content based on ajax data.
 *
 * Please note this can be used to add more trick(s), comment(s), etc... to an existing list.
 */
abstract class AbstractAjaxListResponder
{
    /**
     * @var TemplateBlockRendererInterface
     */
    protected $renderer;

    /**
     * AbstractAjaxListResponder constructor.
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
     * set a response with a HTML template block.
     *
     * @param array  $data
     * @param string $className a F.Q.C.N from child class
     *
     * @return Response
     */
    public function setHTMLBlockResponse(array $data, string $className): Response
    {
        // Render a template block
        $template = $this->renderer->getTemplateBlock($className)['template'];
        $block = $this->renderer->getTemplateBlock($className)['block'];
        $response = new Response($this->renderer->renderTemplateBlock($template, $block, $data));
        $response->headers->set('Content-Type', 'text/html');
        return $response;
    }
}
